/**
 * Unit tests for PayrollJournalEntry
 *
 * Tests:
 * - sumOfTotalPay(): sums the amount field across all LineItemDetail entries
 * - add(): concatenates totalPay arrays and accumulates all scalar deduction fields
 */

import { PayrollJournalEntry, LineItemDetail } from '@app/_models';

describe('PayrollJournalEntry', () => {
  // ==========================================
  // sumOfTotalPay
  // ==========================================

  describe('sumOfTotalPay()', () => {
    it('should return 0 for an empty totalPay array', () => {
      const entry = new PayrollJournalEntry({ totalPay: [] });

      expect(entry.sumOfTotalPay()).toBe(0);
    });

    it('should return the amount for a single line item', () => {
      const entry = new PayrollJournalEntry({
        totalPay: [lineItem(1500)],
      });

      expect(entry.sumOfTotalPay()).toBe(1500);
    });

    it('should sum amounts across multiple line items', () => {
      const entry = new PayrollJournalEntry({
        totalPay: [lineItem(600), lineItem(700), lineItem(200)],
      });

      expect(entry.sumOfTotalPay()).toBe(1500);
    });

    it('should handle negative amounts', () => {
      const entry = new PayrollJournalEntry({
        totalPay: [lineItem(1000), lineItem(-200)],
      });

      expect(entry.sumOfTotalPay()).toBe(800);
    });

    it('should return 0 when totalPay is null', () => {
      const entry = new PayrollJournalEntry({});
      // Force null to test the guard inside sumOfTotalPay
      (entry as any).totalPay = null;

      expect(entry.sumOfTotalPay()).toBe(0);
    });
  });

  // ==========================================
  // add
  // ==========================================

  describe('add()', () => {
    it('should concatenate totalPay line items from both entries', () => {
      const entry = new PayrollJournalEntry({
        totalPay: [lineItem(600)],
      });
      const other = new PayrollJournalEntry({
        totalPay: [lineItem(400)],
      });

      entry.add(other);

      expect(entry.totalPay.length).toBe(2);
      expect(entry.sumOfTotalPay()).toBe(1000);
    });

    it('should accumulate all 7 scalar deduction fields', () => {
      const entry = journalEntry({
        paye: -600,
        employeeNI: -300,
        otherDeductions: -50,
        salarySacrifice: -200,
        studentLoan: -75,
        netPay: -3000,
        employeePension: -100,
      });
      const other = journalEntry({
        paye: -150,
        employeeNI: -80,
        otherDeductions: -10,
        salarySacrifice: -50,
        studentLoan: -25,
        netPay: -750,
        employeePension: -25,
      });

      entry.add(other);

      expect(entry.paye).toBe(-750);
      expect(entry.employeeNI).toBe(-380);
      expect(entry.otherDeductions).toBe(-60);
      expect(entry.salarySacrifice).toBe(-250);
      expect(entry.studentLoan).toBe(-100);
      expect(entry.netPay).toBe(-3750);
      expect(entry.employeePension).toBe(-125);
    });

    it('should return this for chaining', () => {
      const entry = new PayrollJournalEntry({});
      const result = entry.add(new PayrollJournalEntry({}));

      expect(result).toBe(entry);
    });

    it('should leave totalPay unchanged when the other entry has no line items', () => {
      const entry = new PayrollJournalEntry({ totalPay: [lineItem(1000)] });

      entry.add(new PayrollJournalEntry({ totalPay: [] }));

      // concat([]) leaves the array the same length but creates a new reference;
      // the important thing is the data is preserved
      expect(entry.totalPay.length).toBe(1);
    });

    it('should leave scalar fields unchanged when added values are zero', () => {
      const entry = journalEntry({ paye: -600, employeeNI: -300 });

      entry.add(new PayrollJournalEntry({}));

      expect(entry.paye).toBe(-600);
      expect(entry.employeeNI).toBe(-300);
    });
  });

  // ==========================================
  // HELPERS
  // ==========================================

  function lineItem(amount: number): LineItemDetail {
    return new LineItemDetail({ amount, quickbooksId: 1, account: '100' });
  }

  function journalEntry(
    values: Partial<PayrollJournalEntry>,
  ): PayrollJournalEntry {
    const entry = new PayrollJournalEntry({ totalPay: [] });
    return Object.assign(entry, values);
  }
});
