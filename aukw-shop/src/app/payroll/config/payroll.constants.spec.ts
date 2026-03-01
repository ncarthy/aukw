/**
 * Unit tests for payroll.constants.ts helper functions
 *
 * Tests:
 * - isValidTransactionType: type guard for allowed transaction types
 * - isZeroAmount: floating-point near-zero check (uses <=)
 * - isBalanced: journal balance check (uses <, stricter boundary than isZeroAmount)
 * - shouldUseRemainder: allocation rounding edge-case rule
 * - generateDocNumber: QuickBooks document number generation with 21-char limit
 * - calculatePayrollDate: fiscal month + tax year → calendar date (day 25)
 *
 * Note on taxYear format: the app uses 'Year2024' (from the PayRun model),
 * NOT '2023-2024' as the JSDoc comment in the constants file states.
 * substring(4) strips the 'Year' prefix to give '2024'.
 *
 * Note on fiscal months: fiscal months 10, 11, 12 correspond to January,
 * February, March (the months that cross into the next calendar year).
 * The constant's comment saying "Oct, Nov, Dec" is incorrect.
 */

import {
  isValidTransactionType,
  isZeroAmount,
  isBalanced,
  shouldUseRemainder,
  generateDocNumber,
  calculatePayrollDate,
  TRANSACTION_TYPES,
  VALIDATION_RULES,
} from './payroll.constants';

describe('payroll.constants helpers', () => {
  // ==========================================
  // isValidTransactionType
  // ==========================================

  describe('isValidTransactionType', () => {
    it('should return true for each valid transaction type', () => {
      expect(isValidTransactionType(TRANSACTION_TYPES.EMPLOYEE_JOURNAL)).toBe(
        true,
      );
      expect(isValidTransactionType(TRANSACTION_TYPES.EMPLOYER_NI)).toBe(true);
      expect(isValidTransactionType(TRANSACTION_TYPES.PENSIONS)).toBe(true);
      expect(isValidTransactionType(TRANSACTION_TYPES.SHOP_PAYROLL)).toBe(true);
    });

    it('should return true for the raw string values of valid types', () => {
      expect(isValidTransactionType('employee')).toBe(true);
      expect(isValidTransactionType('employer_ni')).toBe(true);
      expect(isValidTransactionType('pensions')).toBe(true);
      expect(isValidTransactionType('shop_payroll')).toBe(true);
    });

    it('should return false for an unknown string', () => {
      expect(isValidTransactionType('unknown')).toBe(false);
    });

    it('should return false for an empty string', () => {
      expect(isValidTransactionType('')).toBe(false);
    });

    it('should be case-sensitive', () => {
      expect(isValidTransactionType('EMPLOYEE')).toBe(false);
      expect(isValidTransactionType('Employee')).toBe(false);
      expect(isValidTransactionType('EMPLOYER_NI')).toBe(false);
    });
  });

  // ==========================================
  // isZeroAmount
  // ==========================================

  describe('isZeroAmount', () => {
    it('should return true for exactly zero', () => {
      expect(isZeroAmount(0)).toBe(true);
    });

    it('should return true for amounts within tolerance', () => {
      expect(isZeroAmount(0.004)).toBe(true);
      expect(isZeroAmount(-0.004)).toBe(true);
    });

    it('should return true for amounts exactly at the tolerance boundary', () => {
      // Uses <= so the boundary value itself is considered zero
      expect(isZeroAmount(VALIDATION_RULES.AMOUNT_ZERO_THRESHOLD)).toBe(true);
      expect(isZeroAmount(-VALIDATION_RULES.AMOUNT_ZERO_THRESHOLD)).toBe(true);
    });

    it('should return false for amounts just above the tolerance', () => {
      expect(isZeroAmount(0.006)).toBe(false);
      expect(isZeroAmount(-0.006)).toBe(false);
    });

    it('should return false for normal amounts', () => {
      expect(isZeroAmount(1)).toBe(false);
      expect(isZeroAmount(100)).toBe(false);
      expect(isZeroAmount(-50)).toBe(false);
    });
  });

  // ==========================================
  // isBalanced
  // ==========================================

  describe('isBalanced', () => {
    it('should return true when the balance is zero', () => {
      expect(isBalanced(0)).toBe(true);
    });

    it('should return true for small imbalances within tolerance', () => {
      expect(isBalanced(0.004)).toBe(true);
      expect(isBalanced(-0.004)).toBe(true);
    });

    it('should return false at exactly the tolerance boundary', () => {
      // Uses < (strict), so the boundary value itself is NOT balanced
      // This differs from isZeroAmount which uses <=
      expect(isBalanced(VALIDATION_RULES.BALANCE_TOLERANCE)).toBe(false);
      expect(isBalanced(-VALIDATION_RULES.BALANCE_TOLERANCE)).toBe(false);
    });

    it('should return false for a meaningful imbalance', () => {
      expect(isBalanced(0.01)).toBe(false);
      expect(isBalanced(1)).toBe(false);
      expect(isBalanced(-10)).toBe(false);
    });
  });

  // ==========================================
  // shouldUseRemainder
  // ==========================================

  describe('shouldUseRemainder', () => {
    it('should return true when remainder equals calculated amount', () => {
      expect(shouldUseRemainder(50, 50)).toBe(true);
      expect(shouldUseRemainder(0, 0)).toBe(true);
    });

    it('should return true when the difference is less than £1', () => {
      expect(shouldUseRemainder(50.33, 50)).toBe(true);
      expect(shouldUseRemainder(50, 50.99)).toBe(true);
    });

    it('should return false when the difference is exactly £1', () => {
      // Uses < (strict), so a difference of exactly 1.0 does NOT use the remainder
      expect(
        shouldUseRemainder(51, 50),
      ).toBe(false);
    });

    it('should return false when the difference is more than £1', () => {
      expect(shouldUseRemainder(52, 50)).toBe(false);
      expect(shouldUseRemainder(100, 50)).toBe(false);
    });

    it('should use absolute difference (handles negative remainders)', () => {
      expect(shouldUseRemainder(-50.33, -50)).toBe(true);
      expect(shouldUseRemainder(-52, -50)).toBe(false);
    });
  });

  // ==========================================
  // generateDocNumber
  // ==========================================

  describe('generateDocNumber', () => {
    it('should produce the correct format for a standard date', () => {
      expect(generateDocNumber('2024-03-25')).toBe('Payroll_2024_03');
    });

    it('should zero-pad single-digit months', () => {
      expect(generateDocNumber('2024-01-15')).toBe('Payroll_2024_01');
      expect(generateDocNumber('2024-09-15')).toBe('Payroll_2024_09');
    });

    it('should handle December correctly', () => {
      expect(generateDocNumber('2024-12-25')).toBe('Payroll_2024_12');
    });

    it('should append a suffix when provided', () => {
      expect(generateDocNumber('2024-03-25', '_E')).toBe('Payroll_2024_03_E');
    });

    it('should truncate the result to 21 characters', () => {
      // 'Payroll_2024_03' = 15 chars; a 10-char suffix would push it to 25, truncated to 21
      const result = generateDocNumber('2024-03-25', '_ABCDEFGHIJ');
      expect(result.length).toBe(21);
      expect(result).toBe('Payroll_2024_03_ABCDE');
    });

    it('should throw for an invalid date string', () => {
      expect(() => generateDocNumber('not-a-date')).toThrow();
      expect(() => generateDocNumber('')).toThrow();
    });
  });

  // ==========================================
  // calculatePayrollDate
  // ==========================================

  describe('calculatePayrollDate', () => {
    // The taxYear format used by the app is 'Year2024' (from the PayRun model).
    // substring(4) strips 'Year' to give the numeric year.
    //
    // UK fiscal year mapping (starts in April):
    //   Fiscal month 1  = April    (same calendar year as taxYear number)
    //   Fiscal month 2  = May
    //   Fiscal month 3  = June
    //   Fiscal month 4  = July
    //   Fiscal month 5  = August
    //   Fiscal month 6  = September
    //   Fiscal month 7  = October
    //   Fiscal month 8  = November
    //   Fiscal month 9  = December
    //   Fiscal month 10 = January  (calendar year + 1)
    //   Fiscal month 11 = February (calendar year + 1)
    //   Fiscal month 12 = March    (calendar year + 1)
    //
    // Payroll is always on day 25 of the month.

    describe('fiscal months 1–9 (same calendar year)', () => {
      it('should return April 25 for fiscal month 1', () => {
        expect(calculatePayrollDate('Year2024', 1)).toBe('2024-04-25');
      });

      it('should return May 25 for fiscal month 2', () => {
        expect(calculatePayrollDate('Year2024', 2)).toBe('2024-05-25');
      });

      it('should return September 25 for fiscal month 6', () => {
        expect(calculatePayrollDate('Year2024', 6)).toBe('2024-09-25');
      });

      it('should return October 25 for fiscal month 7', () => {
        expect(calculatePayrollDate('Year2024', 7)).toBe('2024-10-25');
      });

      it('should return December 25 for fiscal month 9', () => {
        expect(calculatePayrollDate('Year2024', 9)).toBe('2024-12-25');
      });
    });

    describe('fiscal months 10–12 (cross into next calendar year)', () => {
      it('should return January 25 of the next year for fiscal month 10', () => {
        expect(calculatePayrollDate('Year2024', 10)).toBe('2025-01-25');
      });

      it('should return February 25 of the next year for fiscal month 11', () => {
        expect(calculatePayrollDate('Year2024', 11)).toBe('2025-02-25');
      });

      it('should return March 25 of the next year for fiscal month 12', () => {
        expect(calculatePayrollDate('Year2024', 12)).toBe('2025-03-25');
      });
    });

    describe('year boundary', () => {
      it('should work correctly across different tax years', () => {
        expect(calculatePayrollDate('Year2023', 1)).toBe('2023-04-25');
        expect(calculatePayrollDate('Year2023', 12)).toBe('2024-03-25');
        expect(calculatePayrollDate('Year2025', 10)).toBe('2026-01-25');
      });
    });

    describe('invalid inputs', () => {
      it('should return an empty string for an invalid fiscal month', () => {
        expect(calculatePayrollDate('Year2024', 0)).toBe('');
        expect(calculatePayrollDate('Year2024', 13)).toBe('');
      });
    });
  });
});
