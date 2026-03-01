/**
 * Unit tests for IrisPayslip.add()
 *
 * The add() method accumulates values from a second payslip into this instance
 * across all 10 numeric fields and returns `this` for chaining.
 */

import { IrisPayslip } from '@app/_models';

describe('IrisPayslip', () => {
  describe('add()', () => {
    it('should add all 10 numeric fields from the supplied payslip', () => {
      const base = payslip({
        totalPay: 4000,
        paye: -600,
        employeeNI: -300,
        otherDeductions: -50,
        salarySacrifice: 200,
        studentLoan: -75,
        netPay: 3000,
        employerNI: 450,
        employerPension: 380,
        employeePension: 100,
      });

      const extra = payslip({
        totalPay: 1000,
        paye: -150,
        employeeNI: -80,
        otherDeductions: -10,
        salarySacrifice: 50,
        studentLoan: -25,
        netPay: 750,
        employerNI: 110,
        employerPension: 90,
        employeePension: 25,
      });

      base.add(extra);

      expect(base.totalPay).toBe(5000);
      expect(base.paye).toBe(-750);
      expect(base.employeeNI).toBe(-380);
      expect(base.otherDeductions).toBe(-60);
      expect(base.salarySacrifice).toBe(250);
      expect(base.studentLoan).toBe(-100);
      expect(base.netPay).toBe(3750);
      expect(base.employerNI).toBe(560);
      expect(base.employerPension).toBe(470);
      expect(base.employeePension).toBe(125);
    });

    it('should return this for chaining', () => {
      const base = payslip({ totalPay: 1000 });
      const result = base.add(payslip({ totalPay: 500 }));

      expect(result).toBe(base);
    });

    it('should leave values unchanged when adding a zero payslip', () => {
      const base = payslip({ totalPay: 4000, paye: -600, employerNI: 450 });

      base.add(payslip({}));

      expect(base.totalPay).toBe(4000);
      expect(base.paye).toBe(-600);
      expect(base.employerNI).toBe(450);
    });

    it('should correctly accumulate negative values (deductions)', () => {
      const base = payslip({ paye: -600, employeeNI: -300 });
      const extra = payslip({ paye: -150, employeeNI: -80 });

      base.add(extra);

      expect(base.paye).toBe(-750);
      expect(base.employeeNI).toBe(-380);
    });

    it('should support chaining multiple adds', () => {
      const base = payslip({ totalPay: 1000 });

      base.add(payslip({ totalPay: 500 })).add(payslip({ totalPay: 250 }));

      expect(base.totalPay).toBe(1750);
    });
  });

  // ==========================================
  // HELPER
  // ==========================================

  function payslip(values: Partial<IrisPayslip>): IrisPayslip {
    const p = new IrisPayslip({});
    return Object.assign(p, values);
  }
});
