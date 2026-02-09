/**
 * Refactored payroll API adapter service
 *
 * Simplifies observable chains by:
 * - Reducing from 6+ operators to 3-4
 * - Extracting complex transformations to methods
 * - Removing side effects from middle of chain
 * - Clear, descriptive method names
 *
 * This is a cleaner alternative to the existing payrollApiAdapter.service.ts
 */

import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { map, switchMap } from 'rxjs/operators';
import { IrisPayslip, EmployeeAllocation, EmployeeName } from '@app/_models';
import { QBPayrollService, PayrollTransactionsService } from '@app/_services';

/**
 * Result of payroll adaptation
 */
export interface AdaptedPayrollResult {
  payslips: IrisPayslip[];
  total: IrisPayslip;
  payrollDate: string;
}

@Injectable({
  providedIn: 'root',
})
export class PayrollAdapterService {
  private readonly qbPayrollService = inject(QBPayrollService);
  private readonly payrollTransactionsService = inject(
    PayrollTransactionsService
  );

  /**
   * Adapt Staffology payslips to QuickBooks format
   *
   * Simplified flow:
   * 1. Enrich payslips with employee and allocation data
   * 2. Check QB flags (charity)
   * 3. Check QB flags (shop)
   * 4. Notify services and return result
   *
   * @param payslips$ Observable of Staffology payslips
   * @param employees Array of employee names with QB IDs
   * @param allocations Array of cost allocations
   * @returns Observable of adapted result
   */
  adaptStaffologyToQuickBooks(
    payslips$: Observable<IrisPayslip[]>,
    employees: EmployeeName[],
    allocations: EmployeeAllocation[]
  ): Observable<AdaptedPayrollResult> {
    return payslips$.pipe(
      // Step 1: Enrich payslips and calculate total
      map((payslips) => this.enrichPayslips(payslips, employees, allocations)),

      // Step 2: Check QB flags for charity
      switchMap((result) =>
        this.addCharityFlags(result.payslips, result.payrollDate).pipe(
          map((payslips) => ({ ...result, payslips }))
        )
      ),

      // Step 3: Check QB flags for shop
      switchMap((result) =>
        this.addShopFlags(result.payslips, result.payrollDate).pipe(
          map((payslips) => ({ ...result, payslips }))
        )
      ),

      // Step 4: Notify services (side effects at end of chain)
      map((result) => {
        this.notifyServices(result.payslips);
        return result;
      })
    );
  }

  // ==========================================
  // PRIVATE TRANSFORMATION METHODS
  // ==========================================

  /**
   * Enrich payslips with employee and allocation data
   *
   * Extracted from complex map operator
   */
  private enrichPayslips(
    payslips: IrisPayslip[],
    employees: EmployeeName[],
    allocations: EmployeeAllocation[]
  ): AdaptedPayrollResult {
    // Calculate total
    let total = new IrisPayslip();
    const payrollDate = payslips[0]?.payrollDate || '';

    // Enrich each payslip
    const enrichedPayslips = payslips.map((payslip) => {
      // Accumulate total
      total = total.add(payslip);

      // Add employee data
      this.enrichWithEmployeeData(payslip, employees);

      // Add allocation data
      this.enrichWithAllocationData(payslip, allocations);

      return payslip;
    });

    return {
      payslips: enrichedPayslips,
      total,
      payrollDate,
    };
  }

  /**
   * Enrich payslip with employee data from QuickBooks
   */
  private enrichWithEmployeeData(
    payslip: IrisPayslip,
    employees: EmployeeName[]
  ): void {
    const employee = employees.find(
      (emp) => emp.payrollNumber === payslip.payrollNumber
    );

    if (employee) {
      payslip.employeeMissingFromQBO = false;
      payslip.quickbooksId = employee.quickbooksId;
    } else {
      payslip.employeeMissingFromQBO = true;
    }
  }

  /**
   * Enrich payslip with allocation data
   */
  private enrichWithAllocationData(
    payslip: IrisPayslip,
    allocations: EmployeeAllocation[]
  ): void {
    // Note: use == instead of === due to type difference (string vs number)
    const employeeAllocations = allocations.filter(
      (alloc) => alloc.payrollNumber == payslip.payrollNumber
    );

    if (employeeAllocations && employeeAllocations.length > 0) {
      payslip.isShopEmployee = employeeAllocations[0].isShopEmployee;
      payslip.allocationsMissingFromQBO = false;
    } else {
      payslip.allocationsMissingFromQBO = true;
    }
  }

  /**
   * Add charity QuickBooks flags to payslips
   *
   * Checks if transactions already exist in QB
   */
  private addCharityFlags(
    payslips: IrisPayslip[],
    payrollDate: string
  ): Observable<IrisPayslip[]> {
    return this.qbPayrollService.payslipFlagsForCharity(payslips, payrollDate);
  }

  /**
   * Add shop QuickBooks flags to payslips
   *
   * Checks if transactions already exist in QB for shop employees
   */
  private addShopFlags(
    payslips: IrisPayslip[],
    payrollDate: string
  ): Observable<IrisPayslip[]> {
    return this.qbPayrollService.payslipFlagsForShop(payslips, payrollDate);
  }

  /**
   * Notify other services of updated payslips
   *
   * Side effects isolated at end of chain
   */
  private notifyServices(payslips: IrisPayslip[]): void {
    this.qbPayrollService.sendPayslips(payslips);
    this.payrollTransactionsService.createTransactions();
  }
}
