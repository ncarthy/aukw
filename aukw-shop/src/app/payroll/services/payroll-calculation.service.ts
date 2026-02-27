/**
 * Payroll calculation service
 *
 * Provides simplified, testable calculation logic for:
 * - Allocation calculations (splitting amounts by percentage)
 * - Rounding strategies
 * - Validation of allocation percentages
 *
 * Replaces complex nested RxJS operators with clear, synchronous calculations
 */

import { Injectable } from '@angular/core';
import { EmployeeAllocation, IrisPayslip } from '@app/_models';
import {
  VALIDATION_RULES,
  shouldUseRemainder,
} from '../config/payroll.constants';
import {
  createValidationError,
  ValidationErrorCode,
} from '../models/errors.model';

/**
 * Result of allocation calculation
 */
export interface AllocationResult {
  quickbooksId: number;
  name: string;
  account: string;
  accountName: string;
  class: string;
  className: string;
  amount: number;
  payrollNumber: number;
  isShopEmployee: boolean;
  percentage: number;
}

/**
 * Line item detail (matches existing model)
 */
export class LineItemDetail {
  quickbooksId: number = 0;
  name: string = '';
  account: string = '';
  accountName: string = '';
  class: string = '';
  className: string = '';
  amount: number = 0;
  payrollNumber: number = 0;
  isShopEmployee: boolean = false;

  constructor(data?: Partial<LineItemDetail>) {
    if (data) {
      Object.assign(this, data);
    }
  }
}

@Injectable({
  providedIn: 'root',
})
export class PayrollCalculationService {
  /**
   * Calculate allocations for a payslip property across multiple allocations
   *
   * This replaces the complex nested RxJS operators with clear, testable logic
   *
   * @param payslips Array of payslips to process
   * @param allocations Array of allocation rules
   * @param propertyGetter Function to get the amount from payslip (e.g., p => p.employerNI)
   * @returns Array of line items with calculated amounts
   */
  calculateAllocations(
    payslips: IrisPayslip[],
    allocations: EmployeeAllocation[],
    propertyGetter: (p: IrisPayslip) => number,
  ): LineItemDetail[] {
    const results: LineItemDetail[] = [];

    // Process each payslip
    for (const payslip of payslips) {
      const amount = propertyGetter(payslip);

      // Skip if amount is zero
      if (Math.abs(amount) < VALIDATION_RULES.AMOUNT_ZERO_THRESHOLD) {
        continue;
      }

      // Get allocations for this employee
      const employeeAllocations = allocations.filter(
        (alloc) => alloc.payrollNumber === payslip.payrollNumber,
      );

      if (employeeAllocations.length === 0) {
        continue;
      }

      // Calculate allocations for this payslip
      const allocatedLines = this.allocateByRules(
        amount,
        employeeAllocations,
        payslip.payrollNumber,
      );

      results.push(...allocatedLines);
    }

    return results;
  }

  /**
   * Allocate an amount across multiple allocation rules
   *
   * Strategy:
   * - All but last allocation: use percentage-based calculation with rounding
   * - Last allocation: gets the remainder to ensure total equals original amount
   * - Edge case: if calculated amount is within £1 of remainder, use remainder
   *
   * @param totalAmount Total amount to allocate
   * @param allocations Array of allocation rules for one employee
   * @param payrollNumber Employee payroll number
   * @returns Array of line items with allocated amounts
   */
  allocateByRules(
    totalAmount: number,
    allocations: EmployeeAllocation[],
    payrollNumber: number,
  ): LineItemDetail[] {
    // Filter out zero-percentage allocations
    const validAllocations = allocations.filter(
      (alloc) =>
        Math.abs(alloc.percentage) >= VALIDATION_RULES.AMOUNT_ZERO_THRESHOLD,
    );

    if (validAllocations.length === 0) {
      return [];
    }

    // Validate percentages sum to 100%
    const percentageSum = validAllocations.reduce(
      (sum, alloc) => sum + alloc.percentage,
      0,
    );

    if (
      Math.abs(percentageSum - 100) >
      VALIDATION_RULES.AMOUNT_ZERO_THRESHOLD * 100
    ) {
      throw createValidationError(
        ValidationErrorCode.PERCENTAGE_SUM,
        `Allocation percentages must sum to 100%. Current sum: ${percentageSum}%`,
        'allocations',
        percentageSum,
        {
          payrollNumber,
          expectedSum: 100,
          actualSum: percentageSum,
          allocations: validAllocations.map((a) => ({
            name: a.name,
            percentage: a.percentage,
          })),
        },
      );
    }

    const results: LineItemDetail[] = [];
    let remainingAmount = totalAmount;

    // Process all but last allocation
    for (let i = 0; i < validAllocations.length - 1; i++) {
      const allocation = validAllocations[i];

      // Calculate amount based on percentage
      let allocatedAmount = this.calculatePercentageAmount(
        totalAmount,
        allocation.percentage,
      );

      // Ensure allocated amount doesn't exceed remaining amount
      allocatedAmount = this.constrainToRemainder(
        allocatedAmount,
        remainingAmount,
        totalAmount < 0,
      );

      // Edge case: if calculated amount is within £1 of remainder, use remainder
      if (shouldUseRemainder(remainingAmount, allocatedAmount)) {
        allocatedAmount = this.roundToTwoDecimals(remainingAmount);
      }

      // Create line item
      results.push(
        this.createLineItem(allocation, allocatedAmount, payrollNumber),
      );

      // Update remaining amount
      remainingAmount -= allocatedAmount;
    }

    // Last allocation gets the remainder (ensures perfect balance)
    const lastAllocation = validAllocations[validAllocations.length - 1];
    const lastAmount = this.roundToTwoDecimals(remainingAmount);

    results.push(
      this.createLineItem(lastAllocation, lastAmount, payrollNumber),
    );

    return results;
  }

  /**
   * Calculate amount from percentage
   *
   * @param totalAmount Total amount
   * @param percentage Percentage (0-100)
   * @returns Calculated amount rounded to 2 decimals
   */
  private calculatePercentageAmount(
    totalAmount: number,
    percentage: number,
  ): number {
    const amount = (totalAmount * percentage) / 100;
    return this.roundToTwoDecimals(amount);
  }

  /**
   * Constrain allocated amount to not exceed remainder
   *
   * For positive amounts: min(calculated, remainder)
   * For negative amounts: max(calculated, remainder)
   *
   * @param calculatedAmount Calculated allocation
   * @param remainder Remaining amount to allocate
   * @param isNegative Whether dealing with negative amounts
   * @returns Constrained amount
   */
  private constrainToRemainder(
    calculatedAmount: number,
    remainder: number,
    isNegative: boolean,
  ): number {
    if (isNegative) {
      // For negative amounts, use max (less negative)
      return Math.max(calculatedAmount, remainder);
    } else {
      // For positive amounts, use min (smaller)
      return Math.min(calculatedAmount, remainder);
    }
  }

  /**
   * Round to two decimal places
   *
   * @param value Value to round
   * @returns Rounded value
   */
  private roundToTwoDecimals(value: number): number {
    return Number(value.toFixed(2));
  }

  /**
   * Create line item from allocation
   *
   * @param allocation Allocation rule
   * @param amount Calculated amount
   * @param payrollNumber Employee payroll number
   * @returns Line item detail
   */
  private createLineItem(
    allocation: EmployeeAllocation,
    amount: number,
    payrollNumber: number,
  ): LineItemDetail {
    return new LineItemDetail({
      quickbooksId: Number(allocation.quickbooksId),
      name: allocation.name,
      account: String(allocation.account),
      accountName: allocation.accountName,
      class: allocation.class,
      className: allocation.className,
      amount: amount,
      payrollNumber: Number(payrollNumber),
      isShopEmployee: allocation.isShopEmployee,
    });
  }

  /**
   * Validate allocations for an employee
   *
   * @param allocations Array of allocations
   * @returns True if valid
   * @throws ValidationError if invalid
   */
  validateAllocations(allocations: EmployeeAllocation[]): boolean {
    if (!allocations || allocations.length === 0) {
      throw createValidationError(
        ValidationErrorCode.MISSING_REQUIRED_FIELD,
        'At least one allocation is required',
        'allocations',
        allocations,
      );
    }

    // Check percentages sum to 100%
    const sum = allocations.reduce(
      (total, alloc) => total + alloc.percentage,
      0,
    );

    if (Math.abs(sum - 100) > VALIDATION_RULES.AMOUNT_ZERO_THRESHOLD * 100) {
      throw createValidationError(
        ValidationErrorCode.PERCENTAGE_SUM,
        `Allocation percentages must sum to 100%. Current sum: ${sum}%`,
        'allocations',
        sum,
        {
          expectedSum: 100,
          actualSum: sum,
          allocations: allocations.map((a) => ({
            name: a.name,
            percentage: a.percentage,
          })),
        },
      );
    }

    // Check each percentage is valid
    for (const allocation of allocations) {
      if (allocation.percentage < 0 || allocation.percentage > 100) {
        throw createValidationError(
          ValidationErrorCode.INVALID_AMOUNT,
          `Allocation percentage must be between 0 and 100. Got: ${allocation.percentage}%`,
          'percentage',
          allocation.percentage,
          {
            allocationName: allocation.name,
          },
        );
      }
    }

    return true;
  }

  /**
   * Calculate total for a specific property across all payslips
   *
   * @param payslips Array of payslips
   * @param propertyGetter Function to get amount from payslip
   * @returns Total amount
   */
  calculateTotal(
    payslips: IrisPayslip[],
    propertyGetter: (p: IrisPayslip) => number,
  ): number {
    const total = payslips.reduce((sum, payslip) => {
      return sum + propertyGetter(payslip);
    }, 0);

    return this.roundToTwoDecimals(total);
  }

  /**
   * Group allocations by class and calculate totals
   *
   * @param lineItems Array of line items
   * @returns Array of [className, classId, total] tuples
   */
  calculateTotalsByClass(
    lineItems: LineItemDetail[],
  ): [string, string, number][] {
    const classTotals = new Map<string, { name: string; total: number }>();

    for (const item of lineItems) {
      const classId = item.class;
      const className = item.className;

      if (!classTotals.has(classId)) {
        classTotals.set(classId, { name: className, total: 0 });
      }

      const entry = classTotals.get(classId)!;
      entry.total += item.amount;
    }

    // Convert to array format
    const results: [string, string, number][] = [];
    for (const [classId, data] of classTotals.entries()) {
      results.push([data.name, classId, this.roundToTwoDecimals(data.total)]);
    }

    // Sort by class name
    results.sort((a, b) => a[0].localeCompare(b[0]));

    return results;
  }
}
