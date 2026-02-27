/**
 * State management service for payroll component
 *
 * Provides centralized state management with:
 * - Single source of truth
 * - Readonly observables for consumers
 * - Mutation methods for state updates
 * - State snapshots for non-reactive access
 *
 * Eliminates race conditions and competing state variables
 */

import { Injectable } from '@angular/core';
import { BehaviorSubject, Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { IrisPayslip, EmployeeAllocation, EmployeeName } from '@app/_models';

/**
 * Complete state for the payroll component
 */
export interface PayrollState {
  // Payslip data
  payslips: IrisPayslip[];
  total: IrisPayslip;
  payslipsWithMissingEmployeesOrAllocations: IrisPayslip[];

  // Employee and allocation data
  allocations: EmployeeAllocation[];
  employees: EmployeeName[];

  // Date information
  payrollDate: string;

  // UI state
  loading: LoadingState;
  showCreateTransactionsButton: boolean;
  activeTab: number;

  // Error state
  error: string | null;

  // Transaction data
  totalCostsByClass: [string, string, number][];
}

/**
 * Loading state for different operations
 */
export interface LoadingState {
  downloadButton: boolean;
  reloadButton: boolean;
  createTransactions: boolean;
}

/**
 * Initial state factory
 */
function createInitialState(): PayrollState {
  return {
    payslips: [],
    total: new IrisPayslip(),
    payslipsWithMissingEmployeesOrAllocations: [],
    allocations: [],
    employees: [],
    payrollDate: '',
    loading: {
      downloadButton: false,
      reloadButton: false,
      createTransactions: false,
    },
    showCreateTransactionsButton: false,
    activeTab: 1,
    error: null,
    totalCostsByClass: [],
  };
}

@Injectable({
  providedIn: 'root',
})
export class PayrollStateService {
  /**
   * Private state subject - single source of truth
   */
  private readonly stateSubject = new BehaviorSubject<PayrollState>(
    createInitialState(),
  );

  /**
   * Public readonly state observable
   */
  readonly state$: Observable<PayrollState> = this.stateSubject.asObservable();

  // ==========================================
  // OBSERVABLE SELECTORS
  // ==========================================

  /**
   * Payslips observable
   */
  readonly payslips$: Observable<IrisPayslip[]> = this.state$.pipe(
    map((state) => state.payslips),
  );

  /**
   * Total payslip observable
   */
  readonly total$: Observable<IrisPayslip> = this.state$.pipe(
    map((state) => state.total),
  );

  /**
   * Payslips with missing data observable
   */
  readonly payslipsWithMissingData$: Observable<IrisPayslip[]> =
    this.state$.pipe(
      map((state) => state.payslipsWithMissingEmployeesOrAllocations),
    );

  /**
   * Allocations observable
   */
  readonly allocations$: Observable<EmployeeAllocation[]> = this.state$.pipe(
    map((state) => state.allocations),
  );

  /**
   * Employees observable
   */
  readonly employees$: Observable<EmployeeName[]> = this.state$.pipe(
    map((state) => state.employees),
  );

  /**
   * Payroll date observable
   */
  readonly payrollDate$: Observable<string> = this.state$.pipe(
    map((state) => state.payrollDate),
  );

  /**
   * Loading state observable
   */
  readonly loading$: Observable<LoadingState> = this.state$.pipe(
    map((state) => state.loading),
  );

  /**
   * Show create transactions button observable
   */
  readonly showCreateTransactionsButton$: Observable<boolean> =
    this.state$.pipe(map((state) => state.showCreateTransactionsButton));

  /**
   * Active tab observable
   */
  readonly activeTab$: Observable<number> = this.state$.pipe(
    map((state) => state.activeTab),
  );

  /**
   * Error observable
   */
  readonly error$: Observable<string | null> = this.state$.pipe(
    map((state) => state.error),
  );

  /**
   * Total costs by class observable
   */
  readonly totalCostsByClass$: Observable<[string, string, number][]> =
    this.state$.pipe(map((state) => state.totalCostsByClass));

  /**
   * Is loading (any operation) observable
   */
  readonly isLoading$: Observable<boolean> = this.loading$.pipe(
    map(
      (loading) =>
        loading.downloadButton ||
        loading.reloadButton ||
        loading.createTransactions,
    ),
  );

  /**
   * Has payslips observable
   */
  readonly hasPayslips$: Observable<boolean> = this.payslips$.pipe(
    map((payslips) => payslips.length > 0),
  );

  /**
   * Has missing data observable
   */
  readonly hasMissingData$: Observable<boolean> =
    this.payslipsWithMissingData$.pipe(map((payslips) => payslips.length > 0));

  // ==========================================
  // STATE MUTATION METHODS
  // ==========================================

  /**
   * Set payslips
   */
  setPayslips(payslips: IrisPayslip[]): void {
    this.updateState({ payslips });
  }

  /**
   * Set total payslip
   */
  setTotal(total: IrisPayslip): void {
    this.updateState({ total });
  }

  /**
   * Set payslips with missing data
   */
  setPayslipsWithMissingData(payslips: IrisPayslip[]): void {
    this.updateState({ payslipsWithMissingEmployeesOrAllocations: payslips });
  }

  /**
   * Set allocations
   */
  setAllocations(allocations: EmployeeAllocation[]): void {
    this.updateState({ allocations });
  }

  /**
   * Set employees
   */
  setEmployees(employees: EmployeeName[]): void {
    this.updateState({ employees });
  }

  /**
   * Set payroll date
   */
  setPayrollDate(payrollDate: string): void {
    this.updateState({ payrollDate });
  }

  /**
   * Set loading state for a specific operation
   */
  setLoading(operation: keyof LoadingState, isLoading: boolean): void {
    const currentLoading = this.snapshot().loading;
    this.updateState({
      loading: {
        ...currentLoading,
        [operation]: isLoading,
      },
    });
  }

  /**
   * Set loading state for multiple operations
   */
  setLoadingMultiple(loadingState: Partial<LoadingState>): void {
    const currentLoading = this.snapshot().loading;
    this.updateState({
      loading: {
        ...currentLoading,
        ...loadingState,
      },
    });
  }

  /**
   * Set all loading states to false
   */
  clearLoading(): void {
    this.updateState({
      loading: {
        downloadButton: false,
        reloadButton: false,
        createTransactions: false,
      },
    });
  }

  /**
   * Set show create transactions button flag
   */
  setShowCreateTransactionsButton(show: boolean): void {
    this.updateState({ showCreateTransactionsButton: show });
  }

  /**
   * Set active tab
   */
  setActiveTab(tab: number): void {
    this.updateState({ activeTab: tab });
  }

  /**
   * Set error message
   */
  setError(error: string | null): void {
    this.updateState({ error });
  }

  /**
   * Clear error
   */
  clearError(): void {
    this.setError(null);
  }

  /**
   * Set total costs by class
   */
  setTotalCostsByClass(costs: [string, string, number][]): void {
    this.updateState({ totalCostsByClass: costs });
  }

  /**
   * Update payroll data (payslips, total, allocations, employees)
   */
  setPayrollData(data: {
    payslips: IrisPayslip[];
    total: IrisPayslip;
    allocations: EmployeeAllocation[];
    employees: EmployeeName[];
  }): void {
    this.updateState({
      payslips: data.payslips,
      total: data.total,
      allocations: data.allocations,
      employees: data.employees,
    });
  }

  /**
   * Reset state to initial values
   */
  reset(): void {
    this.stateSubject.next(createInitialState());
  }

  /**
   * Reset only data (keep UI state)
   */
  resetData(): void {
    this.updateState({
      payslips: [],
      total: new IrisPayslip(),
      payslipsWithMissingEmployeesOrAllocations: [],
      allocations: [],
      employees: [],
      payrollDate: '',
      totalCostsByClass: [],
      error: null,
    });
  }

  // ==========================================
  // SNAPSHOT ACCESS
  // ==========================================

  /**
   * Get current state snapshot (non-reactive)
   */
  snapshot(): PayrollState {
    return this.stateSubject.getValue();
  }

  /**
   * Get specific property from current state
   */
  get<K extends keyof PayrollState>(key: K): PayrollState[K] {
    return this.snapshot()[key];
  }

  // ==========================================
  // PRIVATE HELPERS
  // ==========================================

  /**
   * Update state with partial updates
   */
  private updateState(partialState: Partial<PayrollState>): void {
    const currentState = this.snapshot();
    const newState = {
      ...currentState,
      ...partialState,
    };
    this.stateSubject.next(newState);
  }
}
