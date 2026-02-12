/**
 * Facade service for payroll operations
 *
 * Orchestrates complete payroll workflow:
 * 1. Fetch payslips from Staffology
 * 2. Adapt data for QuickBooks
 * 3. Check for missing employees/allocations
 * 4. Create QB transactions
 *
 * Uses:
 * - PayrollStateService for state management
 * - PayrollCalculationService for business logic
 * - PayrollErrorHandler for error handling
 */

import { Injectable, inject } from '@angular/core';
import { Observable, throwError, of, EMPTY } from 'rxjs';
import {
  catchError,
  map,
  tap,
  finalize,
  shareReplay,
  switchMap,
  retry,
} from 'rxjs/operators';
import {
  GrossToNetService,
  PayRunService,
  TaxYearService,
} from '@app/_services/payroll';
import {
  PayrollApiAdapterService,
  QBPayrollService,
  LoadingIndicatorService,
  AlertService,
} from '@app/_services';
import { PayrollStateService } from '../state/payroll-state.service';
import {
  PayrollErrorHandler,
  PayrollError,
} from '../models/errors.model';
import { IrisPayslip, EmployeeAllocation, EmployeeName } from '@app/_models';
import { VALIDATION_RULES } from '../config/payroll.constants';

/**
 * Parameters for loading payslips
 */
export interface LoadPayslipsParams {
  employerId: string;
  taxYear: string;
  month: number;
  payrollDate: string;
  sortBy: string;
  sortDescending: boolean;
}

/**
 * Parameters for creating transactions
 */
export interface CreateTransactionsParams {
  employerId: string;
  taxYear: string;
  month: number;
}

/**
 * Result of payslip loading
 */
export interface LoadPayslipsResult {
  payslips: IrisPayslip[];
  total: IrisPayslip;
  payrollDate: string;
  payslipsWithMissingData: IrisPayslip[];
}

@Injectable({
  providedIn: 'root',
})
export class PayrollFacadeService {
  // Inject services
  private readonly stateService = inject(PayrollStateService);
  private readonly grossToNetService = inject(GrossToNetService);
  private readonly payrollApiAdapter = inject(PayrollApiAdapterService);
  private readonly qbPayrollService = inject(QBPayrollService);
  private readonly loadingIndicator = inject(LoadingIndicatorService);
  private readonly alertService = inject(AlertService);

  // Error handler
  private readonly errorHandler = new PayrollErrorHandler(
    VALIDATION_RULES.MAX_API_RETRIES,
    VALIDATION_RULES.API_RETRY_DELAY_MS
  );

  // ==========================================
  // PUBLIC API
  // ==========================================

  /**
   * Load payslips from Staffology and prepare for QuickBooks
   *
   * Complete workflow:
   * 1. Set loading state
   * 2. Fetch from Staffology
   * 3. Adapt to QuickBooks format
   * 4. Check for missing employees/allocations
   * 5. Update state
   * 6. Show success/error messages
   */
  loadPayslips(
    params: LoadPayslipsParams,
    employees: EmployeeName[],
    allocations: EmployeeAllocation[]
  ): Observable<LoadPayslipsResult> {
    // Set loading state
    this.stateService.setLoading('downloadButton', true);
    this.stateService.clearError();

    // Fetch from Staffology
    const grossToNetReport$ = this.grossToNetService.getAll(
      params.employerId,
      params.taxYear,
      params.month,
      params.payrollDate,
      params.sortBy,
      params.sortDescending
    );

    // Adapt and process
    return this.payrollApiAdapter
      .adaptStaffologyToQuickBooks(grossToNetReport$, employees, allocations)
      .pipe(
        // Extract and update state
        map((result) => {
          // Update state with payroll data
          this.stateService.setPayslips(result.payslips);
          this.stateService.setTotal(result.total);
          this.stateService.setPayrollDate(result.payrollDate);

          // Check for missing employees/allocations
          const payslipsWithMissingData = result.payslips.filter(
            (payslip) =>
              payslip.employeeMissingFromQBO ||
              payslip.allocationsMissingFromQBO
          );

          this.stateService.setPayslipsWithMissingData(payslipsWithMissingData);

          // Show create transactions button if no missing data
          this.stateService.setShowCreateTransactionsButton(
            payslipsWithMissingData.length === 0
          );

          return {
            payslips: result.payslips,
            total: result.total,
            payrollDate: result.payrollDate,
            payslipsWithMissingData,
          };
        }),

        // Loading indicator
        this.loadingIndicator.createObserving({
          loading: () =>
            'Querying QuickBooks to see if transactions already entered.',
          success: () => 'Successfully loaded QuickBooks transactions.',
          error: (err) => `${err}`,
        }),

        // Share result
        shareReplay(1),

        // Error handling
        catchError((error) => this.handleError(error)),

        // Cleanup
        finalize(() => {
          this.stateService.setLoading('downloadButton', false);
        })
      );
  }

  /**
   * Reload payslips (convenience method)
   */
  reloadPayslips(
    params: LoadPayslipsParams,
    employees: EmployeeName[],
    allocations: EmployeeAllocation[]
  ): Observable<LoadPayslipsResult> {
    this.stateService.setLoading('reloadButton', true);

    return this.loadPayslips(params, employees, allocations).pipe(
      finalize(() => {
        this.stateService.setLoading('reloadButton', false);
      })
    );
  }

  /**
   * Create QuickBooks transactions for all payslips
   */
  createTransactions(
    params: CreateTransactionsParams
  ): Observable<any> {
    const state = this.stateService.snapshot();

    // Validate state
    if (state.payslips.length === 0) {
      const error: PayrollError = {
        errorCode: 'VALIDATION_MISSING_REQUIRED_FIELD',
        message: 'No payslips loaded. Please load payslips first.',
        retryable: false,
      };
      this.handleError(error);
      return EMPTY;
    }

    if (state.payslipsWithMissingEmployeesOrAllocations.length > 0) {
      const error: PayrollError = {
        errorCode: 'VALIDATION_MISSING_REQUIRED_FIELD',
        message:
          'Cannot create transactions. Some employees are missing from QuickBooks or do not have allocations.',
        retryable: false,
        context: {
          missingCount: state.payslipsWithMissingEmployeesOrAllocations.length,
        },
      };
      this.handleError(error);
      return EMPTY;
    }

    if (state.allocations.length === 0) {
      const error: PayrollError = {
        errorCode: 'VALIDATION_MISSING_REQUIRED_FIELD',
        message: 'No allocations available. Please ensure allocations are loaded.',
        retryable: false,
      };
      this.handleError(error);
      return EMPTY;
    }

    // Set loading state
    this.stateService.setLoading('createTransactions', true);
    this.stateService.clearError();

    // Create all QuickBooks transactions
    return this.qbPayrollService
      .createQBOEntries(
        state.payslips,
        state.allocations,
        state.payrollDate
      )
      .pipe(
        // Loading indicator
        this.loadingIndicator.createObserving({
          loading: () => 'Creating QuickBooks transactions...',
          success: (result) =>
            `Successfully created all payroll transactions in QuickBooks.`,
          error: (err) => `Failed to create transactions: ${err}`,
        }),

        // Update state on success
        tap((result) => {
          // Show success message
          this.alertService.success(
            'All payroll transactions created successfully in QuickBooks!',
            { autoClose: true, keepAfterRouteChange: false }
          );

          // Optionally update flags to indicate transactions are in QBO
          // This would require updating the payslip flags in the state
          // For now, we'll leave this for the component to handle via reload
        }),

        // Share result
        shareReplay(1),

        // Error handling
        catchError((error) => this.handleError(error)),

        // Cleanup
        finalize(() => {
          this.stateService.setLoading('createTransactions', false);
        })
      );
  }

  /**
   * Calculate total costs by class
   */
  calculateTotalCostsByClass(): Observable<[string, string, number][]> {
    const state = this.stateService.snapshot();

    if (state.payslips.length === 0) {
      return of([]);
    }

    // This would typically call a calculation service
    // For now, return empty array as placeholder
    const costs: [string, string, number][] = [];

    this.stateService.setTotalCostsByClass(costs);

    return of(costs);
  }

  /**
   * Reset all payroll data
   */
  reset(): void {
    this.stateService.reset();
  }

  /**
   * Reset only data (keep UI state)
   */
  resetData(): void {
    this.stateService.resetData();
  }

  // ==========================================
  // ERROR HANDLING
  // ==========================================

  /**
   * Handle errors consistently
   */
  private handleError(error: any): Observable<never> {
    // Transform error
    const payrollError = this.errorHandler.transformHttpError(error);

    // Update error state
    this.stateService.setError(payrollError.message);

    // Format for display
    const displayError = this.errorHandler.formatForDisplay(payrollError);

    // Show error alert
    this.alertService.error(displayError.message, {
      autoClose: false,
      keepAfterRouteChange: true,
    });

    // Log error for debugging
    console.error('Payroll error:', {
      error: payrollError,
      display: displayError,
      original: error,
    });

    return throwError(() => payrollError);
  }

  // ==========================================
  // STATE ACCESS
  // ==========================================

  /**
   * Get state service (for components that need direct access)
   */
  getStateService(): PayrollStateService {
    return this.stateService;
  }

  /**
   * Get current state snapshot
   */
  getState() {
    return this.stateService.snapshot();
  }
}
