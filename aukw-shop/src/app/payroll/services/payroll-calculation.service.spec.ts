/**
 * Unit tests for PayrollCalculationService
 *
 * Tests:
 * - Allocation calculations
 * - Rounding strategies
 * - Percentage validation
 * - Edge cases
 * - Total calculations
 *
 * Converted to Vitest - no TestBed needed for pure service
 */

import { describe, it, expect, beforeEach } from 'vitest';
import {
  PayrollCalculationService,
  LineItemDetail,
} from './payroll-calculation.service';
import { IrisPayslip, EmployeeAllocation } from '@app/_models';
import { ValidationErrorCode } from '../models/errors.model';

describe('PayrollCalculationService', () => {
  let service: PayrollCalculationService;

  beforeEach(() => {
    // No TestBed needed - this is a pure service with no dependencies
    service = new PayrollCalculationService();
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });

  // ==========================================
  // ALLOCATION CALCULATION TESTS
  // ==========================================

  describe('calculateAllocations', () => {
    it('should allocate amount across multiple allocations', () => {
      const payslips = [createPayslip(1, 1000)];
      const allocations = [
        createAllocation(1, '100', 'Admin', 50),
        createAllocation(1, '200', 'Operations', 50),
      ];

      const results = service.calculateAllocations(
        payslips,
        allocations,
        (p) => p.totalPay,
      );

      expect(results.length).toBe(2);
      expect(results[0].amount).toBe(500);
      expect(results[1].amount).toBe(500);
    });

    it('should handle multiple payslips', () => {
      const payslips = [createPayslip(1, 1000), createPayslip(2, 2000)];
      const allocations = [
        createAllocation(1, '100', 'Admin', 100),
        createAllocation(2, '100', 'Admin', 100),
      ];

      const results = service.calculateAllocations(
        payslips,
        allocations,
        (p) => p.totalPay,
      );

      expect(results.length).toBe(2);
      expect(results[0].amount).toBe(1000);
      expect(results[1].amount).toBe(2000);
    });

    it('should skip zero amounts', () => {
      const payslips = [createPayslip(1, 0)];
      const allocations = [createAllocation(1, '100', 'Admin', 100)];

      const results = service.calculateAllocations(
        payslips,
        allocations,
        (p) => p.totalPay,
      );

      expect(results.length).toBe(0);
    });

    it('should skip employees without allocations', () => {
      const payslips = [createPayslip(1, 1000), createPayslip(2, 2000)];
      const allocations = [createAllocation(1, '100', 'Admin', 100)];

      const results = service.calculateAllocations(
        payslips,
        allocations,
        (p) => p.totalPay,
      );

      expect(results.length).toBe(1);
      expect(results[0].payrollNumber).toBe(1);
    });
  });

  // ==========================================
  // ALLOCATION BY RULES TESTS
  // ==========================================

  describe('allocateByRules', () => {
    it('should allocate using percentages', () => {
      const allocations = [
        createAllocation(1, '100', 'Admin', 60),
        createAllocation(1, '200', 'Operations', 40),
      ];

      const results = service.allocateByRules(1000, allocations, 1);

      expect(results.length).toBe(2);
      expect(results[0].amount).toBe(600);
      expect(results[1].amount).toBe(400);
    });

    it('should give remainder to last allocation', () => {
      const allocations = [
        createAllocation(1, '100', 'Admin', 33.33),
        createAllocation(1, '200', 'Operations', 33.33),
        createAllocation(1, '300', 'Support', 33.34),
      ];

      const results = service.allocateByRules(100, allocations, 1);

      // First two get percentage-based
      expect(results[0].amount).toBe(33.33);
      expect(results[1].amount).toBe(33.33);

      // Last gets remainder to ensure perfect balance
      expect(results[2].amount).toBe(33.34);

      // Total should equal original
      const total = results.reduce((sum, r) => sum + r.amount, 0);
      expect(total).toBe(100);
    });

    it('should handle negative amounts (credits)', () => {
      const allocations = [
        createAllocation(1, '100', 'Admin', 50),
        createAllocation(1, '200', 'Operations', 50),
      ];

      const results = service.allocateByRules(-1000, allocations, 1);

      expect(results.length).toBe(2);
      expect(results[0].amount).toBe(-500);
      expect(results[1].amount).toBe(-500);
    });

    it('should filter out zero-percentage allocations', () => {
      const allocations = [
        createAllocation(1, '100', 'Admin', 50),
        createAllocation(1, '200', 'Operations', 0), // Zero - should skip
        createAllocation(1, '300', 'Support', 50),
      ];

      const results = service.allocateByRules(1000, allocations, 1);

      expect(results.length).toBe(2);
      expect(results[0].amount).toBeGreaterThan(0);
      expect(results[1].amount).toBeGreaterThan(0);
    });
  });

  // ==========================================
  // ROUNDING TESTS
  // ==========================================

  describe('Rounding', () => {
    it('should round to two decimal places', () => {
      const allocations = [
        createAllocation(1, '100', 'Admin', 33.33),
        createAllocation(1, '200', 'Operations', 66.67),
      ];

      const results = service.allocateByRules(100.33, allocations, 1);

      // Check all amounts are rounded to 2 decimals
      results.forEach((result) => {
        expect(
          result.amount.toString().split('.')[1]?.length || 0,
        ).toBeLessThanOrEqual(2);
      });
    });

    it('should ensure perfect balance after rounding', () => {
      const allocations = [
        createAllocation(1, '100', 'Admin', 33.33),
        createAllocation(1, '200', 'Operations', 33.33),
        createAllocation(1, '300', 'Support', 33.34),
      ];

      const results = service.allocateByRules(1000.17, allocations, 1);

      const total = results.reduce((sum, r) => sum + r.amount, 0);
      expect(total).toBeCloseTo(1000.17, 2);
    });
  });

  // ==========================================
  // VALIDATION TESTS
  // ==========================================

  describe('validateAllocations', () => {
    it('should accept valid 100% allocations', () => {
      const allocations = [
        createAllocation(1, '100', 'Admin', 50),
        createAllocation(1, '200', 'Operations', 50),
      ];

      expect(() => service.validateAllocations(allocations)).not.toThrow();
    });

    it('should reject allocations not summing to 100%', () => {
      const allocations = [
        createAllocation(1, '100', 'Admin', 50),
        createAllocation(1, '200', 'Operations', 30), // Only 80%
      ];

      expect(() => service.validateAllocations(allocations)).toThrowError(
        /must sum to 100%/,
      );
    });

    it('should reject empty allocations', () => {
      expect(() => service.validateAllocations([])).toThrowError(
        /at least one allocation/i,
      );
    });

    it('should reject negative percentages', () => {
      const allocations = [
        createAllocation(1, '100', 'Admin', 150),
        createAllocation(1, '200', 'Operations', -50),
      ];

      expect(() => service.validateAllocations(allocations)).toThrowError(
        /between 0 and 100/,
      );
    });

    it('should reject percentages over 100', () => {
      const allocations = [createAllocation(1, '100', 'Admin', 150)];

      // Note: Single allocation of 150% fails sum check (150 != 100) not range check
      expect(() => service.validateAllocations(allocations)).toThrowError(
        /must sum to 100%/i,
      );
    });
  });

  // ==========================================
  // TOTAL CALCULATION TESTS
  // ==========================================

  describe('calculateTotal', () => {
    it('should sum property across payslips', () => {
      const payslips = [
        createPayslip(1, 1000),
        createPayslip(2, 2000),
        createPayslip(3, 1500),
      ];

      const total = service.calculateTotal(payslips, (p) => p.totalPay);

      expect(total).toBe(4500);
    });

    it('should round total to two decimals', () => {
      const payslips = [createPayslip(1, 33.333), createPayslip(2, 66.667)];

      const total = service.calculateTotal(payslips, (p) => p.totalPay);

      expect(total).toBe(100);
    });

    it('should handle empty payslips', () => {
      const total = service.calculateTotal([], (p) => p.totalPay);

      expect(total).toBe(0);
    });
  });

  // ==========================================
  // TOTALS BY CLASS TESTS
  // ==========================================

  describe('calculateTotalsByClass', () => {
    it('should group by class and sum amounts', () => {
      const lineItems = [
        createLineItem('100', 'Admin', 500),
        createLineItem('100', 'Admin', 300),
        createLineItem('200', 'Operations', 1000),
      ];

      const totals = service.calculateTotalsByClass(lineItems);

      expect(totals.length).toBe(2);

      const adminTotal = totals.find((t) => t[1] === '100');
      expect(adminTotal![2]).toBe(800);

      const opsTotal = totals.find((t) => t[1] === '200');
      expect(opsTotal![2]).toBe(1000);
    });

    it('should sort results by class name', () => {
      const lineItems = [
        createLineItem('200', 'Zebra', 1000),
        createLineItem('100', 'Admin', 500),
      ];

      const totals = service.calculateTotalsByClass(lineItems);

      expect(totals[0][0]).toBe('Admin');
      expect(totals[1][0]).toBe('Zebra');
    });

    it('should handle empty line items', () => {
      const totals = service.calculateTotalsByClass([]);

      expect(totals.length).toBe(0);
    });
  });

  // ==========================================
  // EDGE CASES
  // ==========================================

  describe('Edge Cases', () => {
    it('should handle very small amounts', () => {
      const allocations = [
        createAllocation(1, '100', 'Admin', 50),
        createAllocation(1, '200', 'Operations', 50),
      ];

      const results = service.allocateByRules(0.03, allocations, 1);

      expect(results.length).toBe(2);
      const total = results.reduce((sum, r) => sum + r.amount, 0);
      expect(total).toBeCloseTo(0.03, 2);
    });

    it('should handle single allocation of 100%', () => {
      const allocations = [createAllocation(1, '100', 'Admin', 100)];

      const results = service.allocateByRules(1000, allocations, 1);

      expect(results.length).toBe(1);
      expect(results[0].amount).toBe(1000);
    });
  });

  // ==========================================
  // HELPER FUNCTIONS
  // ==========================================

  function createPayslip(payrollNumber: number, totalPay: number): IrisPayslip {
    const payslip = new IrisPayslip();
    payslip.payrollNumber = payrollNumber;
    payslip.totalPay = totalPay;
    return payslip;
  }

  function createAllocation(
    payrollNumber: number,
    classId: string,
    className: string,
    percentage: number,
  ): EmployeeAllocation {
    return {
      payrollNumber,
      quickbooksId: 123,
      name: 'Test Employee',
      percentage,
      class: classId,
      className,
      account: 200,
      accountName: 'Salaries',
      isShopEmployee: false,
    } as EmployeeAllocation;
  }

  function createLineItem(
    classId: string,
    className: string,
    amount: number,
  ): LineItemDetail {
    return new LineItemDetail({
      class: classId,
      className,
      amount,
      quickbooksId: 123,
      account: '200',
      accountName: 'Salaries',
      name: 'Test',
      payrollNumber: 1,
      isShopEmployee: false,
    });
  }
});
