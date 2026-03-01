/**
 * Unit tests for PayrollService
 *
 * Tests:
 * - employerNIAllocatedCosts: Splits employer NI across cost centres
 * - pensionAllocatedCosts: Splits employer pension across cost centres
 * - grossSalaryAllocatedCosts: Splits gross salary across cost centres
 * - employeeJournalEntries: Converts payslips to QBO journal entry format
 * - shopPayslips: Filters payslips to shop employees not yet booked in QBO
 *
 * No TestBed needed — PayrollService is a pure service with no injected dependencies.
 */

import { Observable, firstValueFrom } from 'rxjs';
import { toArray } from 'rxjs/operators';
import { PayrollService } from './payroll.service';
import { EmployeeAllocation, IrisPayslip } from '@app/_models';

describe('PayrollService', () => {
  let service: PayrollService;

  beforeEach(() => {
    service = new PayrollService();
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });

  // ==========================================
  // EMPLOYER NI ALLOCATED COSTS
  // ==========================================

  describe('employerNIAllocatedCosts', () => {
    it('should return one line item for a single 100% allocation', async () => {
      const payslips = [createPayslip(1, { employerNI: 100 })];
      const allocations = [createAllocation(1, 10, 100)];

      const results = await collectAll(
        service.employerNIAllocatedCosts(payslips, allocations),
      );

      expect(results.length).toBe(1);
      expect(results[0].amount).toBe(100);
    });

    it('should split costs across two allocations by percentage', async () => {
      const payslips = [createPayslip(1, { employerNI: 200 })];
      const allocations = [
        createAllocation(1, 10, 60),
        createAllocation(1, 10, 40),
      ];

      const results = await collectAll(
        service.employerNIAllocatedCosts(payslips, allocations),
      );

      expect(results.length).toBe(2);
      expect(results[0].amount).toBe(120);
      expect(results[1].amount).toBe(80);
    });

    it('should ensure total allocated equals original amount despite rounding', async () => {
      const payslips = [createPayslip(1, { employerNI: 100 })];
      const allocations = [
        createAllocation(1, 10, 33.33),
        createAllocation(1, 10, 33.33),
        createAllocation(1, 10, 33.34),
      ];

      const results = await collectAll(
        service.employerNIAllocatedCosts(payslips, allocations),
      );

      const total = results.reduce((sum, r) => sum + r.amount, 0);
      expect(total).toBeCloseTo(100, 2);
    });

    it('should skip employees with zero employer NI', async () => {
      const payslips = [createPayslip(1, { employerNI: 0 })];
      const allocations = [createAllocation(1, 10, 100)];

      const results = await collectAll(
        service.employerNIAllocatedCosts(payslips, allocations),
      );

      expect(results.length).toBe(0);
    });

    it('should skip zero-percentage allocations', async () => {
      const payslips = [createPayslip(1, { employerNI: 200 })];
      const allocations = [
        createAllocation(1, 10, 100),
        createAllocation(1, 10, 0),
      ];

      const results = await collectAll(
        service.employerNIAllocatedCosts(payslips, allocations),
      );

      expect(results.length).toBe(1);
      expect(results[0].amount).toBe(200);
    });

    it('should produce separate line items for each employee', async () => {
      const payslips = [
        createPayslip(1, { employerNI: 100 }),
        createPayslip(2, { employerNI: 200 }),
      ];
      const allocations = [
        createAllocation(1, 10, 100),
        createAllocation(2, 20, 100),
      ];

      const results = await collectAll(
        service.employerNIAllocatedCosts(payslips, allocations),
      );

      expect(results.length).toBe(2);
      expect(results[0].amount).toBe(100);
      expect(results[1].amount).toBe(200);
    });

    it('should set correct metadata on each line item', async () => {
      const payslips = [createPayslip(1, { employerNI: 100 })];
      const allocations = [
        createAllocation(1, 42, 100, {
          name: 'Jane Doe',
          account: 500,
          accountName: 'NI Expense',
          class: 'ADM',
          className: 'Admin',
          isShopEmployee: true,
        }),
      ];

      const results = await collectAll(
        service.employerNIAllocatedCosts(payslips, allocations),
      );

      expect(results[0].quickbooksId).toBe(42);
      expect(results[0].name).toBe('Jane Doe');
      expect(results[0].account).toBe(500);
      expect(results[0].accountName).toBe('NI Expense');
      expect(results[0].class).toBe('ADM');
      expect(results[0].className).toBe('Admin');
      expect(results[0].payrollNumber).toBe(1);
      expect(results[0].isShopEmployee).toBe(true);
    });
  });

  // ==========================================
  // PENSION ALLOCATED COSTS
  // ==========================================

  describe('pensionAllocatedCosts', () => {
    it('should split pension costs across allocations', async () => {
      const payslips = [createPayslip(1, { employerPension: 150 })];
      const allocations = [
        createAllocation(1, 10, 50),
        createAllocation(1, 10, 50),
      ];

      const results = await collectAll(
        service.pensionAllocatedCosts(payslips, allocations),
      );

      expect(results.length).toBe(2);
      expect(results[0].amount).toBe(75);
      expect(results[1].amount).toBe(75);
    });

    it('should skip employees with zero pension', async () => {
      const payslips = [createPayslip(1, { employerPension: 0 })];
      const allocations = [createAllocation(1, 10, 100)];

      const results = await collectAll(
        service.pensionAllocatedCosts(payslips, allocations),
      );

      expect(results.length).toBe(0);
    });

    it('should handle employees with no matching allocations', async () => {
      const payslips = [createPayslip(1, { employerPension: 150 })];
      const allocations = [createAllocation(99, 10, 100)]; // different payroll number

      const results = await collectAll(
        service.pensionAllocatedCosts(payslips, allocations),
      );

      expect(results.length).toBe(0);
    });
  });

  // ==========================================
  // GROSS SALARY ALLOCATED COSTS
  // ==========================================

  describe('grossSalaryAllocatedCosts', () => {
    it('should split gross salary across allocations', async () => {
      const payslips = [createPayslip(1, { totalPay: 3000 })];
      const allocations = [
        createAllocation(1, 10, 60),
        createAllocation(1, 10, 40),
      ];

      const results = await collectAll(
        service.grossSalaryAllocatedCosts(payslips, allocations),
      );

      expect(results.length).toBe(2);
      expect(results[0].amount).toBe(1800);
      expect(results[1].amount).toBe(1200);
    });

    it('should skip employees with zero total pay', async () => {
      const payslips = [createPayslip(1, { totalPay: 0 })];
      const allocations = [createAllocation(1, 10, 100)];

      const results = await collectAll(
        service.grossSalaryAllocatedCosts(payslips, allocations),
      );

      expect(results.length).toBe(0);
    });
  });

  // ==========================================
  // EMPLOYEE JOURNAL ENTRIES
  // ==========================================

  describe('employeeJournalEntries', () => {
    it('should return one journal entry per payslip', async () => {
      const payslips = [createPayslip(1), createPayslip(2)];
      const allocations = [
        createAllocation(1, 10, 100),
        createAllocation(2, 20, 100),
      ];

      const results = await collectAll(
        service.employeeJournalEntries(payslips, allocations),
      );

      expect(results.length).toBe(2);
    });

    it('should negate netPay, salarySacrifice, and employeePension', async () => {
      const payslips = [
        createPayslip(1, {
          netPay: 3000,
          salarySacrifice: 200,
          employeePension: 100,
          paye: -600,
          employeeNI: -300,
          otherDeductions: -50,
          studentLoan: -75,
        }),
      ];
      const allocations = [createAllocation(1, 10, 100)];

      const results = await collectAll(
        service.employeeJournalEntries(payslips, allocations),
      );
      const entry = results[0];

      // These three are negated when converting to journal format
      expect(entry.netPay).toBe(-3000);
      expect(entry.salarySacrifice).toBe(-200);
      expect(entry.employeePension).toBe(-100);
      // These are passed through as-is (already negative from the payslip)
      expect(entry.paye).toBe(-600);
      expect(entry.employeeNI).toBe(-300);
      expect(entry.otherDeductions).toBe(-50);
      expect(entry.studentLoan).toBe(-75);
    });

    it('should split totalPay across allocations', async () => {
      const payslips = [createPayslip(1, { totalPay: 1000 })];
      const allocations = [
        createAllocation(1, 10, 60),
        createAllocation(1, 10, 40),
      ];

      const results = await collectAll(
        service.employeeJournalEntries(payslips, allocations),
      );
      const entry = results[0];

      expect(entry.totalPay.length).toBe(2);
      expect(entry.totalPay[0].amount).toBe(600);
      expect(entry.totalPay[1].amount).toBe(400);
    });

    it('should adjust the last totalPay allocation so the sum equals the original total', async () => {
      // 1000.01 with 33.33/33.33/33.34 split will produce a rounding discrepancy
      // without the adjustment: 333.30 + 333.30 + 333.40 = 1000.00 ≠ 1000.01
      const payslips = [createPayslip(1, { totalPay: 1000.01 })];
      const allocations = [
        createAllocation(1, 10, 33.33),
        createAllocation(1, 10, 33.33),
        createAllocation(1, 10, 33.34),
      ];

      const results = await collectAll(
        service.employeeJournalEntries(payslips, allocations),
      );
      const entry = results[0];

      const sum = entry.totalPay.reduce((s, x) => s + x.amount, 0);
      expect(sum).toBeCloseTo(1000.01, 2);
    });

    it('should return an entry with null quickbooksId when employee has no allocations', async () => {
      const payslips = [createPayslip(1, { totalPay: 1000 })];
      const allocations: EmployeeAllocation[] = [];

      const results = await collectAll(
        service.employeeJournalEntries(payslips, allocations),
      );
      const entry = results[0];

      expect(entry.quickbooksId).toBeNull();
      expect(entry.totalPay).toEqual([]);
    });

    it('should set the employee name and payroll number on the entry', async () => {
      const payslips = [createPayslip(7, { employeeName: 'Alice Smith' })];
      const allocations = [createAllocation(7, 99, 100)];

      const results = await collectAll(
        service.employeeJournalEntries(payslips, allocations),
      );
      const entry = results[0];

      expect(entry.employeeName).toBe('Alice Smith');
      expect(entry.payrollNumber).toBe(7);
    });

    it('should set quickbooksId from the first allocation', async () => {
      const payslips = [createPayslip(1, { totalPay: 1000 })];
      const allocations = [createAllocation(1, 42, 100)];

      const results = await collectAll(
        service.employeeJournalEntries(payslips, allocations),
      );

      expect(results[0].quickbooksId).toBe(42);
    });
  });

  // ==========================================
  // SHOP PAYSLIPS
  // ==========================================

  describe('shopPayslips', () => {
    it('should return only payslips for shop employees', async () => {
      const payslips = [createPayslip(1), createPayslip(2)];
      const allocations = [
        createAllocation(1, 10, 100, { isShopEmployee: true }),
        createAllocation(2, 20, 100, { isShopEmployee: false }),
      ];

      const results = await collectAll(
        service.shopPayslips(payslips, allocations),
      );

      expect(results.length).toBe(1);
      expect(results[0].payrollNumber).toBe(1);
    });

    it('should exclude payslips already entered in QBO', async () => {
      const payslips = [
        createPayslip(1, { shopJournalInQBO: true }),
        createPayslip(2),
      ];
      const allocations = [
        createAllocation(1, 10, 100, { isShopEmployee: true }),
        createAllocation(2, 20, 100, { isShopEmployee: true }),
      ];

      const results = await collectAll(
        service.shopPayslips(payslips, allocations),
      );

      expect(results.length).toBe(1);
      expect(results[0].payrollNumber).toBe(2);
    });

    it('should return an empty list when no shop employees exist in allocations', async () => {
      const payslips = [createPayslip(1)];
      const allocations = [
        createAllocation(1, 10, 100, { isShopEmployee: false }),
      ];

      const results = await collectAll(
        service.shopPayslips(payslips, allocations),
      );

      expect(results.length).toBe(0);
    });

    it('should return an empty list when all shop payslips are already in QBO', async () => {
      const payslips = [createPayslip(1, { shopJournalInQBO: true })];
      const allocations = [
        createAllocation(1, 10, 100, { isShopEmployee: true }),
      ];

      const results = await collectAll(
        service.shopPayslips(payslips, allocations),
      );

      expect(results.length).toBe(0);
    });

    it('should return multiple shop employees', async () => {
      const payslips = [createPayslip(1), createPayslip(2), createPayslip(3)];
      const allocations = [
        createAllocation(1, 10, 100, { isShopEmployee: true }),
        createAllocation(2, 20, 100, { isShopEmployee: true }),
        createAllocation(3, 30, 100, { isShopEmployee: false }),
      ];

      const results = await collectAll(
        service.shopPayslips(payslips, allocations),
      );

      expect(results.length).toBe(2);
      expect(results.map((r) => r.payrollNumber)).toEqual([1, 2]);
    });
  });

  // ==========================================
  // HELPER FUNCTIONS
  // ==========================================

  function collectAll<T>(obs: Observable<T>): Promise<T[]> {
    return firstValueFrom(obs.pipe(toArray()));
  }

  function createPayslip(
    payrollNumber: number,
    overrides?: Partial<IrisPayslip>,
  ): IrisPayslip {
    // IrisPayslip constructor does not copy all boolean flags (e.g. shopJournalInQBO)
    // from obj, so we apply overrides via Object.assign after construction.
    const payslip = new IrisPayslip({
      payrollNumber,
      employeeName: `Employee ${payrollNumber}`,
    });
    return Object.assign(payslip, overrides);
  }

  function createAllocation(
    payrollNumber: number,
    quickbooksId: number,
    percentage: number,
    overrides?: Partial<EmployeeAllocation>,
  ): EmployeeAllocation {
    // EmployeeAllocation constructor uses `|| null`, so passing percentage: 0
    // would be stored as null. We set percentage directly after construction
    // to preserve the exact value, then apply any overrides the same way.
    const alloc = new EmployeeAllocation({
      payrollNumber,
      quickbooksId,
      name: `Employee ${payrollNumber}`,
      account: 200,
      accountName: 'Salaries',
      class: 'GEN',
      className: 'General',
      isShopEmployee: false,
    });
    alloc.percentage = percentage;
    return Object.assign(alloc, overrides);
  }
});
