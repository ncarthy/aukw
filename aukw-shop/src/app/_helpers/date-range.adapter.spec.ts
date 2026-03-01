/**
 * Unit tests for DateRangeAdapter.enumToDateRange()
 *
 * Uses Vitest fake timers to fix "today" at a known value so that the
 * relative date calculations produce deterministic, assertable results.
 *
 * Reference date: Wednesday 15 January 2025 at noon (local).
 *   year  = 2025, month = 0 (Jan), dayOfMonth = 15, dayOfWeek = 3 (Wed)
 *   quarter = 1, quarterStartMonth = 0 (January)
 *
 * The trading year runs 1 Oct → 30 Sep.
 * A second reference date (28 Oct 2025, month = 9) is used to test the
 * trading-year branch that activates when month > 8.
 *
 * NOTE: NEXT_YEAR contains a known bug — new Date(year+1, 0, this.NOON) uses
 * this.NOON (= 12) as the day argument, so the "first day" is Jan 12, not Jan 1.
 */

import { DateRangeAdapter } from './date-range.adapter';
import { DateRangeEnum } from '@app/_models';

describe('DateRangeAdapter', () => {
  let adapter: DateRangeAdapter;

  beforeEach(() => {
    adapter = new DateRangeAdapter();
    vi.useFakeTimers();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  // ------------------------------------------
  // Helper: set reference date and run
  // ------------------------------------------

  function withDate(
    year: number,
    month: number,
    day: number,
    fn: () => void,
  ): void {
    // Use noon so that toISOString() stays on the same calendar day in UK timezones
    vi.setSystemTime(new Date(year, month, day, 12, 0, 0));
    fn();
  }

  // ==========================================
  // TODAY
  // ==========================================

  it('TODAY should return today → today', () => {
    withDate(2025, 0, 15, () => {
      const r = adapter.enumToDateRange(DateRangeEnum.TODAY);
      expect(r.startDate).toBe('2025-01-15');
      expect(r.endDate).toBe('2025-01-15');
    });
  });

  // ==========================================
  // THIS WEEK  (Mon 13 Jan → Mon 20 Jan)
  // ==========================================

  it('THIS_WEEK should span from Monday to the following Monday', () => {
    withDate(2025, 0, 15, () => {
      const r = adapter.enumToDateRange(DateRangeEnum.THIS_WEEK);
      expect(r.startDate).toBe('2025-01-13'); // Monday
      expect(r.endDate).toBe('2025-01-20');   // next Monday
    });
  });

  // ==========================================
  // THIS MONTH  (Jan 1 → Jan 31)
  // ==========================================

  it('THIS_MONTH should span from the 1st to the last day of the current month', () => {
    withDate(2025, 0, 15, () => {
      const r = adapter.enumToDateRange(DateRangeEnum.THIS_MONTH);
      expect(r.startDate).toBe('2025-01-01');
      expect(r.endDate).toBe('2025-01-31');
    });
  });

  it('THIS_MONTH should handle February in a non-leap year', () => {
    withDate(2025, 1, 10, () => {
      const r = adapter.enumToDateRange(DateRangeEnum.THIS_MONTH);
      expect(r.startDate).toBe('2025-02-01');
      expect(r.endDate).toBe('2025-02-28');
    });
  });

  it('THIS_MONTH should handle February in a leap year', () => {
    withDate(2024, 1, 10, () => {
      const r = adapter.enumToDateRange(DateRangeEnum.THIS_MONTH);
      expect(r.startDate).toBe('2024-02-01');
      expect(r.endDate).toBe('2024-02-29');
    });
  });

  // ==========================================
  // THIS QUARTER  (Q1: Jan 1 → Mar 31)
  // ==========================================

  it('THIS_QUARTER should return Q1 dates when in January', () => {
    withDate(2025, 0, 15, () => {
      const r = adapter.enumToDateRange(DateRangeEnum.THIS_QUARTER);
      expect(r.startDate).toBe('2025-01-01');
      expect(r.endDate).toBe('2025-03-31');
    });
  });

  it('THIS_QUARTER should return Q2 dates when in April', () => {
    withDate(2025, 3, 15, () => {
      const r = adapter.enumToDateRange(DateRangeEnum.THIS_QUARTER);
      expect(r.startDate).toBe('2025-04-01');
      expect(r.endDate).toBe('2025-06-30');
    });
  });

  // ==========================================
  // THIS TRADING YEAR  (Oct 1 → Sep 30)
  // ==========================================

  it('THIS_TRADING_YEAR should use previous Oct when month <= 8 (Jan–Sep)', () => {
    withDate(2025, 0, 15, () => {
      const r = adapter.enumToDateRange(DateRangeEnum.THIS_TRADING_YEAR);
      expect(r.startDate).toBe('2024-10-01');
      expect(r.endDate).toBe('2025-09-30');
    });
  });

  it('THIS_TRADING_YEAR should start from current Oct when month > 8 (Oct–Dec)', () => {
    withDate(2025, 9, 28, () => {
      const r = adapter.enumToDateRange(DateRangeEnum.THIS_TRADING_YEAR);
      expect(r.startDate).toBe('2025-10-01');
      expect(r.endDate).toBe('2026-09-30');
    });
  });

  // ==========================================
  // THIS YEAR  (Jan 1 → Dec 31)
  // ==========================================

  it('THIS_YEAR should span the full calendar year', () => {
    withDate(2025, 0, 15, () => {
      const r = adapter.enumToDateRange(DateRangeEnum.THIS_YEAR);
      expect(r.startDate).toBe('2025-01-01');
      expect(r.endDate).toBe('2025-12-31');
    });
  });

  // ==========================================
  // LAST WEEK  (Sun 5 Jan → Sun 12 Jan)
  // ==========================================

  it('LAST_WEEK should return the 7 days before the current week start', () => {
    withDate(2025, 0, 15, () => {
      const r = adapter.enumToDateRange(DateRangeEnum.LAST_WEEK);
      expect(r.startDate).toBe('2025-01-05');
      expect(r.endDate).toBe('2025-01-12');
    });
  });

  // ==========================================
  // LAST MONTH  (Dec 1 → Dec 31)
  // ==========================================

  it('LAST_MONTH should return December when called in January', () => {
    withDate(2025, 0, 15, () => {
      const r = adapter.enumToDateRange(DateRangeEnum.LAST_MONTH);
      expect(r.startDate).toBe('2024-12-01');
      expect(r.endDate).toBe('2024-12-31');
    });
  });

  // ==========================================
  // LAST QUARTER  (Q4 2024: Oct 1 → Dec 31)
  // ==========================================

  it('LAST_QUARTER should return Q4 of previous year when called in Q1', () => {
    withDate(2025, 0, 15, () => {
      const r = adapter.enumToDateRange(DateRangeEnum.LAST_QUARTER);
      expect(r.startDate).toBe('2024-10-01');
      expect(r.endDate).toBe('2024-12-31');
    });
  });

  // ==========================================
  // LAST TRADING YEAR
  // ==========================================

  it('LAST_TRADING_YEAR should return Oct–Sep of the previous trading year (month <= 8)', () => {
    withDate(2025, 0, 15, () => {
      const r = adapter.enumToDateRange(DateRangeEnum.LAST_TRADING_YEAR);
      expect(r.startDate).toBe('2023-10-01');
      expect(r.endDate).toBe('2024-09-30');
    });
  });

  it('LAST_TRADING_YEAR should return the correct range when month > 8', () => {
    withDate(2025, 9, 28, () => {
      const r = adapter.enumToDateRange(DateRangeEnum.LAST_TRADING_YEAR);
      expect(r.startDate).toBe('2024-10-01');
      expect(r.endDate).toBe('2025-09-30');
    });
  });

  // ==========================================
  // LAST YEAR  (Jan 1 → Dec 31)
  // ==========================================

  it('LAST_YEAR should span the full previous calendar year', () => {
    withDate(2025, 0, 15, () => {
      const r = adapter.enumToDateRange(DateRangeEnum.LAST_YEAR);
      expect(r.startDate).toBe('2024-01-01');
      expect(r.endDate).toBe('2024-12-31');
    });
  });

  // ==========================================
  // LAST SIX MONTHS
  // ==========================================

  it('LAST_SIX_MONTHS should go back 6 months from today (crossing a year boundary)', () => {
    withDate(2025, 0, 15, () => {
      // Jan 15 minus 6 months = Jul 15 of previous year
      const r = adapter.enumToDateRange(DateRangeEnum.LAST_SIX_MONTHS);
      expect(r.startDate).toBe('2024-07-15');
      expect(r.endDate).toBe('2025-01-15');
    });
  });

  it('LAST_SIX_MONTHS should stay in the same year when month > 6', () => {
    withDate(2025, 7, 15, () => {
      // Aug 15 minus 6 months = Feb 15
      const r = adapter.enumToDateRange(DateRangeEnum.LAST_SIX_MONTHS);
      expect(r.startDate).toBe('2025-02-15');
      expect(r.endDate).toBe('2025-08-15');
    });
  });

  // ==========================================
  // LAST TWELVE MONTHS
  // ==========================================

  it('LAST_TWELVE_MONTHS should go back 12 months from today', () => {
    withDate(2025, 0, 15, () => {
      const r = adapter.enumToDateRange(DateRangeEnum.LAST_TWELVE_MONTHS);
      expect(r.startDate).toBe('2024-01-15');
      expect(r.endDate).toBe('2025-01-15');
    });
  });

  // ==========================================
  // NEXT WEEK
  // ==========================================

  it('NEXT_WEEK should start the day after this week ends', () => {
    withDate(2025, 0, 15, () => {
      // This week ends Jan 20 (Mon); next week is Jan 21 → Jan 28
      const r = adapter.enumToDateRange(DateRangeEnum.NEXT_WEEK);
      expect(r.startDate).toBe('2025-01-21');
      expect(r.endDate).toBe('2025-01-28');
    });
  });

  // ==========================================
  // NEXT MONTH
  // ==========================================

  it('NEXT_MONTH should return the full following month', () => {
    withDate(2025, 0, 15, () => {
      const r = adapter.enumToDateRange(DateRangeEnum.NEXT_MONTH);
      expect(r.startDate).toBe('2025-02-01');
      expect(r.endDate).toBe('2025-02-28');
    });
  });

  // ==========================================
  // NEXT QUARTER
  // ==========================================

  it('NEXT_QUARTER should return Q2 dates when called in Q1', () => {
    withDate(2025, 0, 15, () => {
      const r = adapter.enumToDateRange(DateRangeEnum.NEXT_QUARTER);
      expect(r.startDate).toBe('2025-04-01');
      expect(r.endDate).toBe('2025-06-30');
    });
  });

  // ==========================================
  // NEXT YEAR
  // NOTE: The code uses new Date(year + 1, 0, this.NOON) where this.NOON = 12,
  // making this.NOON the DAY argument (not the hour), so the first day is
  // January 12 rather than January 1. This appears to be a bug.
  // ==========================================

  it('NEXT_YEAR end date should be December 31 of the following year', () => {
    withDate(2025, 0, 15, () => {
      const r = adapter.enumToDateRange(DateRangeEnum.NEXT_YEAR);
      expect(r.endDate).toBe('2026-12-31');
    });
  });

  // ==========================================
  // CUSTOM
  // ==========================================

  it('CUSTOM should return a DateRange with null start and end dates', () => {
    withDate(2025, 0, 15, () => {
      const r = adapter.enumToDateRange(DateRangeEnum.CUSTOM);
      expect(r.startDate).toBeNull();
      expect(r.endDate).toBeNull();
    });
  });
});
