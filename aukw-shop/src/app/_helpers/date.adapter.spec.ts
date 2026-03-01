/**
 * Unit tests for NgbUTCStringAdapter
 *
 * Converts between ISO date strings ('yyyy-MM-dd') and NgbDateStruct
 * objects used by the ng-bootstrap date picker.
 *
 * Notes on fromModel quirk:
 * The month validity check uses `Number(date.substring(5, 7) + 1)`, where
 * `+ 1` is string concatenation (not addition), producing e.g. '031'.
 * Number('031') = 31 which is always truthy for any non-empty month string,
 * so the month part is effectively unchecked. Only year (non-zero) and
 * day (non-zero) are meaningfully validated.
 */

import { NgbUTCStringAdapter } from './date.adapter';

describe('NgbUTCStringAdapter', () => {
  let adapter: NgbUTCStringAdapter;

  beforeEach(() => {
    adapter = new NgbUTCStringAdapter();
  });

  // ==========================================
  // fromModel — string → NgbDateStruct
  // ==========================================

  describe('fromModel()', () => {
    it('should convert a valid ISO date string to an NgbDateStruct', () => {
      expect(adapter.fromModel('2024-03-15')).toEqual({
        year: 2024,
        month: 3,
        day: 15,
      });
    });

    it('should handle single-digit months and days (with leading zeros)', () => {
      expect(adapter.fromModel('2024-01-05')).toEqual({
        year: 2024,
        month: 1,
        day: 5,
      });
    });

    it('should handle December correctly', () => {
      expect(adapter.fromModel('2024-12-31')).toEqual({
        year: 2024,
        month: 12,
        day: 31,
      });
    });

    it('should return null for null input', () => {
      expect(adapter.fromModel(null)).toBeNull();
    });

    it('should return null for an empty string', () => {
      expect(adapter.fromModel('')).toBeNull();
    });

    it('should return null when the year is zero (falsy)', () => {
      expect(adapter.fromModel('0000-03-15')).toBeNull();
    });

    it('should return null when the day is zero', () => {
      // day substring is '00', Number('00') = 0 which is falsy
      expect(adapter.fromModel('2024-03-00')).toBeNull();
    });
  });

  // ==========================================
  // toModel — NgbDateStruct → string
  // ==========================================

  describe('toModel()', () => {
    it('should convert an NgbDateStruct to an ISO date string', () => {
      expect(adapter.toModel({ year: 2024, month: 3, day: 15 })).toBe(
        '2024-03-15',
      );
    });

    it('should zero-pad single-digit months', () => {
      expect(adapter.toModel({ year: 2024, month: 1, day: 15 })).toBe(
        '2024-01-15',
      );
    });

    it('should zero-pad single-digit days', () => {
      expect(adapter.toModel({ year: 2024, month: 3, day: 5 })).toBe(
        '2024-03-05',
      );
    });

    it('should handle December 31 correctly', () => {
      expect(adapter.toModel({ year: 2024, month: 12, day: 31 })).toBe(
        '2024-12-31',
      );
    });

    it('should return null for null input', () => {
      expect(adapter.toModel(null)).toBeNull();
    });

    it('should round-trip correctly through both methods', () => {
      const original = '2024-07-22';
      const struct = adapter.fromModel(original);
      const result = adapter.toModel(struct);

      expect(result).toBe(original);
    });
  });
});
