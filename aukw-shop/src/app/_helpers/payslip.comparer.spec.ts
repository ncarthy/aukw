/**
 * Unit tests for payslip.comparer.ts
 *
 * Tests:
 * - isEqualPay:        compares 8 payslip fields within a 0.005 tolerance
 * - isEqualPension:    compares employer pension — returns true if iris value is
 *                      near zero, regardless of the QB value (intentional shortcut)
 * - isEqualEmployerNI: same pattern as isEqualPension, for employer NI
 * - isEqualShopPay:    compares totalPay, employerNI and employerPension together;
 *                      returns true if all three iris values are near zero, OR if
 *                      all three match QB within tolerance
 */

import { IrisPayslip } from '@app/_models';
import {
  isEqualPay,
  isEqualPension,
  isEqualEmployerNI,
  isEqualShopPay,
} from './payslip.comparer';

describe('payslip.comparer', () => {
  // ==========================================
  // isEqualPay
  // ==========================================

  describe('isEqualPay', () => {
    it('should return true when all 8 fields are identical', () => {
      const iris = createPayslip({
        totalPay: 4000,
        paye: -600,
        employeeNI: -300,
        otherDeductions: -50,
        employeePension: -100,
        salarySacrifice: 200,
        studentLoan: -75,
        netPay: 3000,
      });
      const qb = createPayslip({ ...iris });

      expect(isEqualPay(iris, qb)).toBe(true);
    });

    it('should return true when differences are within tolerance', () => {
      const iris = createPayslip({ totalPay: 4000, paye: -600, employeeNI: -300 });
      const qb = createPayslip({
        totalPay: 4000.004,
        paye: -600.004,
        employeeNI: -300.004,
      });

      expect(isEqualPay(iris, qb)).toBe(true);
    });

    it('should return false when totalPay differs beyond tolerance', () => {
      const iris = createPayslip({ totalPay: 4000 });
      const qb = createPayslip({ totalPay: 4000.01 });

      expect(isEqualPay(iris, qb)).toBe(false);
    });

    it('should return false when paye differs beyond tolerance', () => {
      const iris = createPayslip({ paye: -600 });
      const qb = createPayslip({ paye: -600.01 });

      expect(isEqualPay(iris, qb)).toBe(false);
    });

    it('should return false when employeeNI differs beyond tolerance', () => {
      const iris = createPayslip({ employeeNI: -300 });
      const qb = createPayslip({ employeeNI: -300.01 });

      expect(isEqualPay(iris, qb)).toBe(false);
    });

    it('should return false when otherDeductions differs beyond tolerance', () => {
      const iris = createPayslip({ otherDeductions: -50 });
      const qb = createPayslip({ otherDeductions: -50.01 });

      expect(isEqualPay(iris, qb)).toBe(false);
    });

    it('should return false when employeePension differs beyond tolerance', () => {
      const iris = createPayslip({ employeePension: 100 });
      const qb = createPayslip({ employeePension: 100.01 });

      expect(isEqualPay(iris, qb)).toBe(false);
    });

    it('should return false when salarySacrifice differs beyond tolerance', () => {
      const iris = createPayslip({ salarySacrifice: 200 });
      const qb = createPayslip({ salarySacrifice: 200.01 });

      expect(isEqualPay(iris, qb)).toBe(false);
    });

    it('should return false when studentLoan differs beyond tolerance', () => {
      const iris = createPayslip({ studentLoan: -75 });
      const qb = createPayslip({ studentLoan: -75.01 });

      expect(isEqualPay(iris, qb)).toBe(false);
    });

    it('should return false when netPay differs beyond tolerance', () => {
      const iris = createPayslip({ netPay: 3000 });
      const qb = createPayslip({ netPay: 3000.01 });

      expect(isEqualPay(iris, qb)).toBe(false);
    });

    it('should return true when all fields are zero', () => {
      const iris = createPayslip({});
      const qb = createPayslip({});

      expect(isEqualPay(iris, qb)).toBe(true);
    });
  });

  // ==========================================
  // isEqualPension
  // ==========================================

  describe('isEqualPension', () => {
    it('should return true when iris and QB pension are equal', () => {
      const iris = createPayslip({ employerPension: 400 });
      const qb = createPayslip({ employerPension: 400 });

      expect(isEqualPension(iris, qb)).toBe(true);
    });

    it('should return true when difference is within tolerance', () => {
      const iris = createPayslip({ employerPension: 400 });
      const qb = createPayslip({ employerPension: 400.004 });

      expect(isEqualPension(iris, qb)).toBe(true);
    });

    it('should return false when difference exceeds tolerance', () => {
      const iris = createPayslip({ employerPension: 400 });
      const qb = createPayslip({ employerPension: 410 });

      expect(isEqualPension(iris, qb)).toBe(false);
    });

    it('should return true when iris pension is zero, regardless of QB value', () => {
      // The first OR condition: Math.abs(iris.employerPension) < TOLERANCE
      // means a zero Iris pension is always treated as equal, even if QB has a value.
      const iris = createPayslip({ employerPension: 0 });
      const qb = createPayslip({ employerPension: 400 });

      expect(isEqualPension(iris, qb)).toBe(true);
    });

    it('should return true when iris pension is near zero, regardless of QB value', () => {
      const iris = createPayslip({ employerPension: 0.004 });
      const qb = createPayslip({ employerPension: 400 });

      expect(isEqualPension(iris, qb)).toBe(true);
    });
  });

  // ==========================================
  // isEqualEmployerNI
  // ==========================================

  describe('isEqualEmployerNI', () => {
    it('should return true when iris and QB employer NI are equal', () => {
      const iris = createPayslip({ employerNI: 500 });
      const qb = createPayslip({ employerNI: 500 });

      expect(isEqualEmployerNI(iris, qb)).toBe(true);
    });

    it('should return true when difference is within tolerance', () => {
      const iris = createPayslip({ employerNI: 500 });
      const qb = createPayslip({ employerNI: 500.004 });

      expect(isEqualEmployerNI(iris, qb)).toBe(true);
    });

    it('should return false when difference exceeds tolerance', () => {
      const iris = createPayslip({ employerNI: 500 });
      const qb = createPayslip({ employerNI: 510 });

      expect(isEqualEmployerNI(iris, qb)).toBe(false);
    });

    it('should return true when iris NI is zero, regardless of QB value', () => {
      // Same pattern as isEqualPension: zero Iris NI is always treated as equal.
      const iris = createPayslip({ employerNI: 0 });
      const qb = createPayslip({ employerNI: 500 });

      expect(isEqualEmployerNI(iris, qb)).toBe(true);
    });

    it('should return true when iris NI is near zero, regardless of QB value', () => {
      const iris = createPayslip({ employerNI: 0.004 });
      const qb = createPayslip({ employerNI: 500 });

      expect(isEqualEmployerNI(iris, qb)).toBe(true);
    });
  });

  // ==========================================
  // isEqualShopPay
  // ==========================================

  describe('isEqualShopPay', () => {
    it('should return true when all three fields match QB within tolerance', () => {
      const iris = createPayslip({ totalPay: 2000, employerNI: 200, employerPension: 100 });
      const qb = createPayslip({ totalPay: 2000, employerNI: 200, employerPension: 100 });

      expect(isEqualShopPay(iris, qb)).toBe(true);
    });

    it('should return true when differences are within tolerance', () => {
      const iris = createPayslip({ totalPay: 2000, employerNI: 200, employerPension: 100 });
      const qb = createPayslip({
        totalPay: 2000.004,
        employerNI: 200.004,
        employerPension: 100.004,
      });

      expect(isEqualShopPay(iris, qb)).toBe(true);
    });

    it('should return false when totalPay exceeds tolerance', () => {
      const iris = createPayslip({ totalPay: 2000, employerNI: 200, employerPension: 100 });
      const qb = createPayslip({ totalPay: 2010, employerNI: 200, employerPension: 100 });

      expect(isEqualShopPay(iris, qb)).toBe(false);
    });

    it('should return false when employerNI exceeds tolerance', () => {
      const iris = createPayslip({ totalPay: 2000, employerNI: 200, employerPension: 100 });
      const qb = createPayslip({ totalPay: 2000, employerNI: 210, employerPension: 100 });

      expect(isEqualShopPay(iris, qb)).toBe(false);
    });

    it('should return false when employerPension exceeds tolerance', () => {
      const iris = createPayslip({ totalPay: 2000, employerNI: 200, employerPension: 100 });
      const qb = createPayslip({ totalPay: 2000, employerNI: 200, employerPension: 110 });

      expect(isEqualShopPay(iris, qb)).toBe(false);
    });

    it('should return true when all three iris values are zero, even if QB values are non-zero', () => {
      // First OR condition: all three Iris values near zero → true regardless of QB
      const iris = createPayslip({ totalPay: 0, employerNI: 0, employerPension: 0 });
      const qb = createPayslip({ totalPay: 2000, employerNI: 200, employerPension: 100 });

      expect(isEqualShopPay(iris, qb)).toBe(true);
    });

    it('should return true when all three iris values are near zero', () => {
      const iris = createPayslip({
        totalPay: 0.004,
        employerNI: 0.004,
        employerPension: 0.004,
      });
      const qb = createPayslip({ totalPay: 2000, employerNI: 200, employerPension: 100 });

      expect(isEqualShopPay(iris, qb)).toBe(true);
    });

    it('should not short-circuit when only some iris values are near zero', () => {
      // First condition requires ALL THREE to be near zero.
      // If only two are near zero, the second condition (match QB) is evaluated.
      const iris = createPayslip({ totalPay: 0, employerNI: 0, employerPension: 100 });
      const qb = createPayslip({ totalPay: 2000, employerNI: 200, employerPension: 100 });

      // Second condition: totalPay doesn't match QB → false
      expect(isEqualShopPay(iris, qb)).toBe(false);
    });
  });

  // ==========================================
  // HELPER FUNCTIONS
  // ==========================================

  function createPayslip(values: Partial<IrisPayslip>): IrisPayslip {
    // IrisPayslip constructor uses falsy || fallback, so we use Object.assign
    // to ensure all provided values (including 0) are applied correctly.
    const payslip = new IrisPayslip({});
    return Object.assign(payslip, values);
  }
});
