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
import { describe, it, expect, beforeEach } from 'vitest';
import { firstValueFrom } from 'rxjs';
import { PayrollStateService } from './payroll-state.service';
import { IrisPayslip, EmployeeAllocation, EmployeeName } from '@app/_models';

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
    it('should initialize with empty state', async () => {
      const state = await firstValueFrom(service.state$);
      expect(state.payslips).toEqual([]);
      expect(state.allocations).toEqual([]);
      expect(state.employees).toEqual([]);
      expect(state.payrollDate).toBe('');
      expect(state.error).toBeNull();
    });

    it('should initialize loading state to false', async () => {
      const loading = await firstValueFrom(service.loading$);
      expect(loading.downloadButton).toBe(false);
      expect(loading.reloadButton).toBe(false);
      expect(loading.createTransactions).toBe(false);
    });

    it('should initialize active tab to 1', async () => {
      const tab = await firstValueFrom(service.activeTab$);
      expect(tab).toBe(1);
    });
  });

  // ==========================================
  // PAYSLIPS TESTS
  // ==========================================

  describe('Payslips', () => {
    it('should set payslips', async () => {
      const payslips = [new IrisPayslip(), new IrisPayslip()];

      service.setPayslips(payslips);

      const result = await firstValueFrom(service.payslips$);
      expect(result).toEqual(payslips);
      expect(result.length).toBe(2);
    });

    it('should emit hasPayslips$ true when payslips exist', async () => {
      const payslips = [new IrisPayslip()];

      service.setPayslips(payslips);

      const hasPayslips = await firstValueFrom(service.hasPayslips$);
      expect(hasPayslips).toBe(true);
    });

    it('should emit hasPayslips$ false when no payslips', async () => {
      service.setPayslips([]);

      const hasPayslips = await firstValueFrom(service.hasPayslips$);
      expect(hasPayslips).toBe(false);
    });
  });

  // ==========================================
  // LOADING STATE TESTS
  // ==========================================

  describe('Loading State', () => {
    it('should set loading for specific operation', async () => {
      service.setLoading('downloadButton', true);

      const loading = await firstValueFrom(service.loading$);
      expect(loading.downloadButton).toBe(true);
      expect(loading.reloadButton).toBe(false);
    });

    it('should set loading for multiple operations', async () => {
      service.setLoadingMultiple({
        downloadButton: true,
        reloadButton: true,
      });

      const loading = await firstValueFrom(service.loading$);
      expect(loading.downloadButton).toBe(true);
      expect(loading.reloadButton).toBe(true);
      expect(loading.createTransactions).toBe(false);
    });

    it('should clear all loading states', async () => {
      service.setLoadingMultiple({
        downloadButton: true,
        reloadButton: true,
        createTransactions: true,
      });

      service.clearLoading();

      const loading = await firstValueFrom(service.loading$);
      expect(loading.downloadButton).toBe(false);
      expect(loading.reloadButton).toBe(false);
      expect(loading.createTransactions).toBe(false);
    });

    it('should emit isLoading$ true when any operation is loading', async () => {
      service.setLoading('downloadButton', true);

      const isLoading = await firstValueFrom(service.isLoading$);
      expect(isLoading).toBe(true);
    });

    it('should emit isLoading$ false when no operations loading', async () => {
      service.clearLoading();

      const isLoading = await firstValueFrom(service.isLoading$);
      expect(isLoading).toBe(false);
    });
  });

  // ==========================================
  // ERROR STATE TESTS
  // ==========================================

  describe('Error State', () => {
    it('should set error message', async () => {
      const errorMessage = 'Test error';

      service.setError(errorMessage);

      const error = await firstValueFrom(service.error$);
      expect(error).toBe(errorMessage);
    });

    it('should clear error', async () => {
      service.setError('Test error');
      service.clearError();

      const error = await firstValueFrom(service.error$);
      expect(error).toBeNull();
    });
  });

  // ==========================================
  // ALLOCATION TESTS
  // ==========================================

  describe('Allocations', () => {
    it('should set allocations', async () => {
      const allocations: EmployeeAllocation[] = [
        {
          payrollNumber: 1,
          quickbooksId: 123,
          name: 'Test',
          percentage: 100,
          class: '100',
          className: 'Admin',
          account: 200,
          accountName: 'Salaries',
          isShopEmployee: false,
        } as EmployeeAllocation,
      ];

      service.setAllocations(allocations);

      const result = await firstValueFrom(service.allocations$);
      expect(result).toEqual(allocations);
      expect(result.length).toBe(1);
    });
  });

  // ==========================================
  // EMPLOYEES TESTS
  // ==========================================

  describe('Employees', () => {
    it('should set employees', async () => {
      const employees: EmployeeName[] = [
        {
          payrollNumber: 1,
          quickbooksId: 123,
          name: 'John Doe',
          firstName: 'John',
          middleName: '',
          lastName: 'Doe',
        } as EmployeeName,
      ];

      service.setEmployees(employees);

      const result = await firstValueFrom(service.employees$);
      expect(result).toEqual(employees);
      expect(result.length).toBe(1);
    });
  });

  // ==========================================
  // PAYROLL DATE TESTS
  // ==========================================

  describe('Payroll Date', () => {
    it('should set payroll date', async () => {
      const date = '2024-01-31';

      service.setPayrollDate(date);

      const result = await firstValueFrom(service.payrollDate$);
      expect(result).toBe(date);
    });
  });

  // ==========================================
  // TOTAL TESTS
  // ==========================================

  describe('Total', () => {
    it('should set total payslip', async () => {
      const total = new IrisPayslip();
      total.totalPay = 10000;

      service.setTotal(total);

      const result = await firstValueFrom(service.total$);
      expect(result.totalPay).toBe(10000);
    });
  });

  // ==========================================
  // MISSING DATA TESTS
  // ==========================================

  describe('Missing Data', () => {
    it('should set payslips with missing data', async () => {
      const payslips = [new IrisPayslip()];

      service.setPayslipsWithMissingData(payslips);

      const result = await firstValueFrom(service.payslipsWithMissingData$);
      expect(result).toEqual(payslips);
    });

    it('should emit hasMissingData$ true when missing data exists', async () => {
      service.setPayslipsWithMissingData([new IrisPayslip()]);

      const hasMissing = await firstValueFrom(service.hasMissingData$);
      expect(hasMissing).toBe(true);
    });

    it('should emit hasMissingData$ false when no missing data', async () => {
      service.setPayslipsWithMissingData([]);

      const hasMissing = await firstValueFrom(service.hasMissingData$);
      expect(hasMissing).toBe(false);
    });
  });

  // ==========================================
  // BULK UPDATE TESTS
  // ==========================================

  describe('Bulk Updates', () => {
    it('should set payroll data in bulk', async () => {
      const data = {
        payslips: [new IrisPayslip()],
        total: new IrisPayslip(),
        allocations: [] as EmployeeAllocation[],
        employees: [] as EmployeeName[],
      };

      service.setPayrollData(data);

      const state = await firstValueFrom(service.state$);
      expect(state.payslips).toEqual(data.payslips);
      expect(state.total).toEqual(data.total);
      expect(state.allocations).toEqual(data.allocations);
      expect(state.employees).toEqual(data.employees);
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
    it('should reset to initial state', async () => {
      // Set some state
      service.setPayslips([new IrisPayslip()]);
      service.setPayrollDate('2024-01-31');
      service.setActiveTab(2);
      service.setError('Error');

      // Reset
      service.reset();

      const state = await firstValueFrom(service.state$);
      expect(state.payslips).toEqual([]);
      expect(state.payrollDate).toBe('');
      expect(state.activeTab).toBe(1);
      expect(state.error).toBeNull();
    });

    it('should reset only data keeping UI state', async () => {
      // Set some state
      service.setPayslips([new IrisPayslip()]);
      service.setPayrollDate('2024-01-31');
      service.setActiveTab(2);

      // Reset data only
      service.resetData();

      const state = await firstValueFrom(service.state$);
      expect(state.payslips).toEqual([]);
      expect(state.payrollDate).toBe('');
      expect(state.activeTab).toBe(2); // UI state preserved
    });
  });

  // ==========================================
  // UI STATE TESTS
  // ==========================================

  describe('UI State', () => {
    it('should set show create transactions button', async () => {
      service.setShowCreateTransactionsButton(true);

      const show = await firstValueFrom(service.showCreateTransactionsButton$);
      expect(show).toBe(true);
    });

    it('should set active tab', async () => {
      service.setActiveTab(3);

      const tab = await firstValueFrom(service.activeTab$);
      expect(tab).toBe(3);
    });
  });

  // ==========================================
  // TOTAL COSTS BY CLASS TESTS
  // ==========================================

  describe('Total Costs By Class', () => {
    it('should set total costs by class', async () => {
      const costs: [string, string, number][] = [
        ['Admin', '100', 1000],
        ['Operations', '200', 2000],
      ];

      service.setTotalCostsByClass(costs);

      const result = await firstValueFrom(service.totalCostsByClass$);
      expect(result).toEqual(costs);
      expect(result.length).toBe(2);
    });
  });
});
