/**
 * Unit tests for PayrollStateService
 *
 * Tests:
 * - State initialization
 * - State mutations
 * - Observable selectors
 * - Snapshot access
 * - Reset functionality
 */

import { TestBed } from '@angular/core/testing';
import { PayrollStateService } from './payroll-state.service';
import { IrisPayslip, EmployeeAllocation, EmployeeName } from '@app/_models';
import { take } from 'rxjs/operators';

describe('PayrollStateService', () => {
  let service: PayrollStateService;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    service = TestBed.inject(PayrollStateService);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });

  // ==========================================
  // INITIALIZATION TESTS
  // ==========================================

  describe('Initialization', () => {
    it('should initialize with empty state', (done) => {
      service.state$.pipe(take(1)).subscribe((state) => {
        expect(state.payslips).toEqual([]);
        expect(state.allocations).toEqual([]);
        expect(state.employees).toEqual([]);
        expect(state.payrollDate).toBe('');
        expect(state.error).toBeNull();
        done();
      });
    });

    it('should initialize loading state to false', (done) => {
      service.loading$.pipe(take(1)).subscribe((loading) => {
        expect(loading.downloadButton).toBe(false);
        expect(loading.reloadButton).toBe(false);
        expect(loading.createTransactions).toBe(false);
        done();
      });
    });

    it('should initialize active tab to 1', (done) => {
      service.activeTab$.pipe(take(1)).subscribe((tab) => {
        expect(tab).toBe(1);
        done();
      });
    });
  });

  // ==========================================
  // PAYSLIPS TESTS
  // ==========================================

  describe('Payslips', () => {
    it('should set payslips', (done) => {
      const payslips = [new IrisPayslip(), new IrisPayslip()];

      service.setPayslips(payslips);

      service.payslips$.pipe(take(1)).subscribe((result) => {
        expect(result).toEqual(payslips);
        expect(result.length).toBe(2);
        done();
      });
    });

    it('should emit hasPayslips$ true when payslips exist', (done) => {
      const payslips = [new IrisPayslip()];

      service.setPayslips(payslips);

      service.hasPayslips$.pipe(take(1)).subscribe((hasPayslips) => {
        expect(hasPayslips).toBe(true);
        done();
      });
    });

    it('should emit hasPayslips$ false when no payslips', (done) => {
      service.setPayslips([]);

      service.hasPayslips$.pipe(take(1)).subscribe((hasPayslips) => {
        expect(hasPayslips).toBe(false);
        done();
      });
    });
  });

  // ==========================================
  // LOADING STATE TESTS
  // ==========================================

  describe('Loading State', () => {
    it('should set loading for specific operation', (done) => {
      service.setLoading('downloadButton', true);

      service.loading$.pipe(take(1)).subscribe((loading) => {
        expect(loading.downloadButton).toBe(true);
        expect(loading.reloadButton).toBe(false);
        done();
      });
    });

    it('should set loading for multiple operations', (done) => {
      service.setLoadingMultiple({
        downloadButton: true,
        reloadButton: true,
      });

      service.loading$.pipe(take(1)).subscribe((loading) => {
        expect(loading.downloadButton).toBe(true);
        expect(loading.reloadButton).toBe(true);
        expect(loading.createTransactions).toBe(false);
        done();
      });
    });

    it('should clear all loading states', (done) => {
      service.setLoadingMultiple({
        downloadButton: true,
        reloadButton: true,
        createTransactions: true,
      });

      service.clearLoading();

      service.loading$.pipe(take(1)).subscribe((loading) => {
        expect(loading.downloadButton).toBe(false);
        expect(loading.reloadButton).toBe(false);
        expect(loading.createTransactions).toBe(false);
        done();
      });
    });

    it('should emit isLoading$ true when any operation is loading', (done) => {
      service.setLoading('downloadButton', true);

      service.isLoading$.pipe(take(1)).subscribe((isLoading) => {
        expect(isLoading).toBe(true);
        done();
      });
    });

    it('should emit isLoading$ false when no operations loading', (done) => {
      service.clearLoading();

      service.isLoading$.pipe(take(1)).subscribe((isLoading) => {
        expect(isLoading).toBe(false);
        done();
      });
    });
  });

  // ==========================================
  // ERROR STATE TESTS
  // ==========================================

  describe('Error State', () => {
    it('should set error message', (done) => {
      const errorMessage = 'Test error';

      service.setError(errorMessage);

      service.error$.pipe(take(1)).subscribe((error) => {
        expect(error).toBe(errorMessage);
        done();
      });
    });

    it('should clear error', (done) => {
      service.setError('Test error');
      service.clearError();

      service.error$.pipe(take(1)).subscribe((error) => {
        expect(error).toBeNull();
        done();
      });
    });
  });

  // ==========================================
  // ALLOCATION TESTS
  // ==========================================

  describe('Allocations', () => {
    it('should set allocations', (done) => {
      const allocations: EmployeeAllocation[] = [
        {
          payrollNumber: 1,
          quickbooksId: '123',
          name: 'Test',
          percentage: 100,
          class: '100',
          className: 'Admin',
          account: '200',
          accountName: 'Salaries',
          isShopEmployee: false,
        },
      ];

      service.setAllocations(allocations);

      service.allocations$.pipe(take(1)).subscribe((result) => {
        expect(result).toEqual(allocations);
        expect(result.length).toBe(1);
        done();
      });
    });
  });

  // ==========================================
  // EMPLOYEES TESTS
  // ==========================================

  describe('Employees', () => {
    it('should set employees', (done) => {
      const employees: EmployeeName[] = [
        {
          payrollNumber: 1,
          quickbooksId: '123',
          firstName: 'John',
          lastName: 'Doe',
        } as EmployeeName,
      ];

      service.setEmployees(employees);

      service.employees$.pipe(take(1)).subscribe((result) => {
        expect(result).toEqual(employees);
        expect(result.length).toBe(1);
        done();
      });
    });
  });

  // ==========================================
  // PAYROLL DATE TESTS
  // ==========================================

  describe('Payroll Date', () => {
    it('should set payroll date', (done) => {
      const date = '2024-01-31';

      service.setPayrollDate(date);

      service.payrollDate$.pipe(take(1)).subscribe((result) => {
        expect(result).toBe(date);
        done();
      });
    });
  });

  // ==========================================
  // TOTAL TESTS
  // ==========================================

  describe('Total', () => {
    it('should set total payslip', (done) => {
      const total = new IrisPayslip();
      total.grossPay = 10000;

      service.setTotal(total);

      service.total$.pipe(take(1)).subscribe((result) => {
        expect(result.grossPay).toBe(10000);
        done();
      });
    });
  });

  // ==========================================
  // MISSING DATA TESTS
  // ==========================================

  describe('Missing Data', () => {
    it('should set payslips with missing data', (done) => {
      const payslips = [new IrisPayslip()];

      service.setPayslipsWithMissingData(payslips);

      service.payslipsWithMissingData$.pipe(take(1)).subscribe((result) => {
        expect(result).toEqual(payslips);
        done();
      });
    });

    it('should emit hasMissingData$ true when missing data exists', (done) => {
      service.setPayslipsWithMissingData([new IrisPayslip()]);

      service.hasMissingData$.pipe(take(1)).subscribe((hasMissing) => {
        expect(hasMissing).toBe(true);
        done();
      });
    });

    it('should emit hasMissingData$ false when no missing data', (done) => {
      service.setPayslipsWithMissingData([]);

      service.hasMissingData$.pipe(take(1)).subscribe((hasMissing) => {
        expect(hasMissing).toBe(false);
        done();
      });
    });
  });

  // ==========================================
  // BULK UPDATE TESTS
  // ==========================================

  describe('Bulk Updates', () => {
    it('should set payroll data in bulk', (done) => {
      const data = {
        payslips: [new IrisPayslip()],
        total: new IrisPayslip(),
        allocations: [] as EmployeeAllocation[],
        employees: [] as EmployeeName[],
      };

      service.setPayrollData(data);

      service.state$.pipe(take(1)).subscribe((state) => {
        expect(state.payslips).toEqual(data.payslips);
        expect(state.total).toEqual(data.total);
        expect(state.allocations).toEqual(data.allocations);
        expect(state.employees).toEqual(data.employees);
        done();
      });
    });
  });

  // ==========================================
  // SNAPSHOT TESTS
  // ==========================================

  describe('Snapshot', () => {
    it('should return current state snapshot', () => {
      service.setPayrollDate('2024-01-31');
      service.setActiveTab(2);

      const snapshot = service.snapshot();

      expect(snapshot.payrollDate).toBe('2024-01-31');
      expect(snapshot.activeTab).toBe(2);
    });

    it('should get specific property from snapshot', () => {
      service.setPayrollDate('2024-01-31');

      const date = service.get('payrollDate');

      expect(date).toBe('2024-01-31');
    });
  });

  // ==========================================
  // RESET TESTS
  // ==========================================

  describe('Reset', () => {
    it('should reset to initial state', (done) => {
      // Set some state
      service.setPayslips([new IrisPayslip()]);
      service.setPayrollDate('2024-01-31');
      service.setActiveTab(2);
      service.setError('Error');

      // Reset
      service.reset();

      service.state$.pipe(take(1)).subscribe((state) => {
        expect(state.payslips).toEqual([]);
        expect(state.payrollDate).toBe('');
        expect(state.activeTab).toBe(1);
        expect(state.error).toBeNull();
        done();
      });
    });

    it('should reset only data keeping UI state', (done) => {
      // Set some state
      service.setPayslips([new IrisPayslip()]);
      service.setPayrollDate('2024-01-31');
      service.setActiveTab(2);

      // Reset data only
      service.resetData();

      service.state$.pipe(take(1)).subscribe((state) => {
        expect(state.payslips).toEqual([]);
        expect(state.payrollDate).toBe('');
        expect(state.activeTab).toBe(2); // UI state preserved
        done();
      });
    });
  });

  // ==========================================
  // UI STATE TESTS
  // ==========================================

  describe('UI State', () => {
    it('should set show create transactions button', (done) => {
      service.setShowCreateTransactionsButton(true);

      service.showCreateTransactionsButton$.pipe(take(1)).subscribe((show) => {
        expect(show).toBe(true);
        done();
      });
    });

    it('should set active tab', (done) => {
      service.setActiveTab(3);

      service.activeTab$.pipe(take(1)).subscribe((tab) => {
        expect(tab).toBe(3);
        done();
      });
    });
  });

  // ==========================================
  // TOTAL COSTS BY CLASS TESTS
  // ==========================================

  describe('Total Costs By Class', () => {
    it('should set total costs by class', (done) => {
      const costs: [string, string, number][] = [
        ['Admin', '100', 1000],
        ['Operations', '200', 2000],
      ];

      service.setTotalCostsByClass(costs);

      service.totalCostsByClass$.pipe(take(1)).subscribe((result) => {
        expect(result).toEqual(costs);
        expect(result.length).toBe(2);
        done();
      });
    });
  });
});
