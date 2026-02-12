/**
 * Unit tests for PayrollFacadeService
 *
 * Tests:
 * - Payslip loading workflow
 * - Transaction creation
 * - Error handling
 * - State management integration
 * - Retry logic
 */

import { TestBed } from '@angular/core/testing';
import { of, throwError, firstValueFrom, lastValueFrom, EmptyError } from 'rxjs';
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { PayrollFacadeService } from './payroll-facade.service';
import { PayrollStateService } from '../state/payroll-state.service';
import {
  GrossToNetService,
  PayRunService,
  TaxYearService,
} from '@app/_services/payroll';
import {
  PayrollApiAdapterService,
  QBPayrollService,
  LoadingIndicatorService,
  AlertService,
} from '@app/_services';
import { IrisPayslip, EmployeeAllocation, EmployeeName } from '@app/_models';
import { HttpErrorResponse } from '@angular/common/http';

describe('PayrollFacadeService', () => {
  let service: PayrollFacadeService;
  let stateService: any;
  let grossToNetService: any;
  let payrollApiAdapter: any;
  let qbPayrollService: any;
  let alertService: any;

  beforeEach(() => {
    // Create spy objects
    const stateServiceSpy = {
      setLoading: vi.fn(),
      clearError: vi.fn(),
      setPayslips: vi.fn(),
      setTotal: vi.fn(),
      setPayrollDate: vi.fn(),
      setPayslipsWithMissingData: vi.fn(),
      setShowCreateTransactionsButton: vi.fn(),
      setError: vi.fn(),
      snapshot: vi.fn(),
    };

    const grossToNetServiceSpy = {
      getAll: vi.fn(),
    };

    const payrollApiAdapterSpy = {
      adaptStaffologyToQuickBooks: vi.fn(),
    };

    const qbPayrollServiceSpy = {
      createQBOEntries: vi.fn(),
    };

    const alertServiceSpy = {
      success: vi.fn(),
      error: vi.fn(),
    };

    const loadingIndicatorSpy = {
      createObserving: vi.fn(),
    };

    // Configure TestBed
    TestBed.configureTestingModule({
      providers: [
        PayrollFacadeService,
        { provide: PayrollStateService, useValue: stateServiceSpy },
        { provide: GrossToNetService, useValue: grossToNetServiceSpy },
        { provide: PayrollApiAdapterService, useValue: payrollApiAdapterSpy },
        { provide: QBPayrollService, useValue: qbPayrollServiceSpy },
        { provide: AlertService, useValue: alertServiceSpy },
        { provide: LoadingIndicatorService, useValue: loadingIndicatorSpy },
      ],
    });

    service = TestBed.inject(PayrollFacadeService);
    stateService = TestBed.inject(PayrollStateService);
    grossToNetService = TestBed.inject(GrossToNetService);
    payrollApiAdapter = TestBed.inject(PayrollApiAdapterService);
    qbPayrollService = TestBed.inject(QBPayrollService);
    alertService = TestBed.inject(AlertService);

    // Default spy return values
    loadingIndicatorSpy.createObserving.mockReturnValue((source: any) => source);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });

  // ==========================================
  // LOAD PAYSLIPS TESTS
  // ==========================================

  describe('loadPayslips', () => {
    it('should set loading state at start', async () => {
      const mockResult = {
        payslips: [new IrisPayslip()],
        total: new IrisPayslip(),
        payrollDate: '2024-01-31',
      };

      grossToNetService.getAll.mockReturnValue(of([new IrisPayslip()]));
      payrollApiAdapter.adaptStaffologyToQuickBooks.mockReturnValue(
        of(mockResult)
      );

      const params = createLoadParams();
      const employees: EmployeeName[] = [];
      const allocations: EmployeeAllocation[] = [];

      await firstValueFrom(service.loadPayslips(params, employees, allocations));
      expect(stateService.setLoading).toHaveBeenCalledWith(
        'downloadButton',
        true
      );
    });

    it('should update state with loaded payslips', async () => {
      const mockPayslips = [new IrisPayslip()];
      const mockTotal = new IrisPayslip();
      const mockDate = '2024-01-31';
      const mockResult = {
        payslips: mockPayslips,
        total: mockTotal,
        payrollDate: mockDate,
      };

      grossToNetService.getAll.mockReturnValue(of(mockPayslips));
      payrollApiAdapter.adaptStaffologyToQuickBooks.mockReturnValue(
        of(mockResult)
      );

      const params = createLoadParams();

      await firstValueFrom(service.loadPayslips(params, [], []));
      expect(stateService.setPayslips).toHaveBeenCalledWith(mockPayslips);
      expect(stateService.setTotal).toHaveBeenCalledWith(mockTotal);
      expect(stateService.setPayrollDate).toHaveBeenCalledWith(mockDate);
    });

    it('should identify payslips with missing data', async () => {
      const payslipWithMissing = new IrisPayslip();
      payslipWithMissing.employeeMissingFromQBO = true;

      const mockResult = {
        payslips: [payslipWithMissing],
        total: new IrisPayslip(),
        payrollDate: '2024-01-31',
      };

      grossToNetService.getAll.mockReturnValue(of([payslipWithMissing]));
      payrollApiAdapter.adaptStaffologyToQuickBooks.mockReturnValue(
        of(mockResult)
      );

      const params = createLoadParams();

      const result = await firstValueFrom(service.loadPayslips(params, [], []));
      expect(result.payslipsWithMissingData.length).toBe(1);
      expect(stateService.setShowCreateTransactionsButton).toHaveBeenCalledWith(
        false
      );
    });

    it('should enable create transactions button when no missing data', async () => {
      const mockResult = {
        payslips: [new IrisPayslip()],
        total: new IrisPayslip(),
        payrollDate: '2024-01-31',
      };

      grossToNetService.getAll.mockReturnValue(of([new IrisPayslip()]));
      payrollApiAdapter.adaptStaffologyToQuickBooks.mockReturnValue(
        of(mockResult)
      );

      const params = createLoadParams();

      await firstValueFrom(service.loadPayslips(params, [], []));
      expect(stateService.setShowCreateTransactionsButton).toHaveBeenCalledWith(
        true
      );
    });

    it('should handle errors gracefully', async () => {
      const error = new HttpErrorResponse({ status: 500 });

      grossToNetService.getAll.mockReturnValue(of([new IrisPayslip()]));
      payrollApiAdapter.adaptStaffologyToQuickBooks.mockReturnValue(
        throwError(() => error)
      );

      const params = createLoadParams();

      await expect(
        firstValueFrom(service.loadPayslips(params, [], []))
      ).rejects.toThrow();

      expect(stateService.setError).toHaveBeenCalled();
      expect(alertService.error).toHaveBeenCalled();
    });
  });

  // ==========================================
  // RELOAD PAYSLIPS TESTS
  // ==========================================

  describe('reloadPayslips', () => {
    it('should set reload loading state', async () => {
      const mockResult = {
        payslips: [new IrisPayslip()],
        total: new IrisPayslip(),
        payrollDate: '2024-01-31',
      };

      grossToNetService.getAll.mockReturnValue(of([new IrisPayslip()]));
      payrollApiAdapter.adaptStaffologyToQuickBooks.mockReturnValue(
        of(mockResult)
      );

      const params = createLoadParams();

      await firstValueFrom(service.reloadPayslips(params, [], []));
      expect(stateService.setLoading).toHaveBeenCalledWith('reloadButton', true);
    });
  });

  // ==========================================
  // CREATE TRANSACTIONS TESTS
  // ==========================================

  describe('createTransactions', () => {
    it('should validate state before creating transactions', async () => {
      stateService.snapshot.mockReturnValue({
        payslips: [],
        payslipsWithMissingEmployeesOrAllocations: [],
      } as any);

      const params = {
        employerId: '123',
        taxYear: '2023-2024',
        month: 1,
      };

      try {
        await firstValueFrom(service.createTransactions(params));
      } catch (err) {
        // Observable completes without emitting (EMPTY), so firstValueFrom throws EmptyError
        expect(err).toBeInstanceOf(EmptyError);
      }
      expect(stateService.setError).toHaveBeenCalled();
    });

    it('should reject if missing data exists', async () => {
      stateService.snapshot.mockReturnValue({
        payslips: [new IrisPayslip()],
        payslipsWithMissingEmployeesOrAllocations: [new IrisPayslip()],
      } as any);

      const params = {
        employerId: '123',
        taxYear: '2023-2024',
        month: 1,
      };

      try {
        await firstValueFrom(service.createTransactions(params));
      } catch (err) {
        // Observable completes without emitting (EMPTY), so firstValueFrom throws EmptyError
        expect(err).toBeInstanceOf(EmptyError);
      }
      expect(stateService.setError).toHaveBeenCalled();
    });

    it('should create transactions when state is valid', async () => {
      const mockPayslips = [new IrisPayslip()];
      const mockAllocations: EmployeeAllocation[] = [
        new EmployeeAllocation({
          id: 1,
          quickbooksId: 1,
          payrollNumber: 123,
          percentage: 100,
          account: '5000',
          accountName: 'Salaries',
          class: '1',
          className: 'Admin',
          name: 'Test Employee',
          isShopEmployee: false,
        }),
      ];

      stateService.snapshot.mockReturnValue({
        payslips: mockPayslips,
        payslipsWithMissingEmployeesOrAllocations: [],
        allocations: mockAllocations,
        payrollDate: '2024-01-31',
      } as any);

      const mockResponse = {
        message: 'Successfully created payroll transactions',
        results: [],
      };

      qbPayrollService.createQBOEntries.mockReturnValue(of(mockResponse));

      const params = {
        employerId: '123',
        taxYear: '2023-2024',
        month: 1,
      };

      await firstValueFrom(service.createTransactions(params));
      expect(qbPayrollService.createQBOEntries).toHaveBeenCalledWith(
        mockPayslips,
        mockAllocations,
        '2024-01-31'
      );
      expect(alertService.success).toHaveBeenCalled();
    });

    it('should reject if allocations are missing', async () => {
      stateService.snapshot.mockReturnValue({
        payslips: [new IrisPayslip()],
        payslipsWithMissingEmployeesOrAllocations: [],
        allocations: [],
        payrollDate: '2024-01-31',
      } as any);

      const params = {
        employerId: '123',
        taxYear: '2023-2024',
        month: 1,
      };

      try {
        await firstValueFrom(service.createTransactions(params));
      } catch (err) {
        // Observable completes without emitting (EMPTY), so firstValueFrom throws EmptyError
        expect(err).toBeInstanceOf(EmptyError);
      }
      expect(stateService.setError).toHaveBeenCalled();
    });
  });

  // ==========================================
  // STATE ACCESS TESTS
  // ==========================================

  describe('State Access', () => {
    it('should return state service', () => {
      const result = service.getStateService();
      expect(result).toBe(stateService);
    });

    it('should return state snapshot', () => {
      const mockSnapshot = { payslips: [] } as any;
      stateService.snapshot.mockReturnValue(mockSnapshot);

      const result = service.getState();
      expect(result).toBe(mockSnapshot);
    });
  });

  // ==========================================
  // RESET TESTS
  // ==========================================

  describe('Reset', () => {
    it('should reset all state', () => {
      stateService.reset = vi.fn();

      service.reset();

      expect(stateService.reset).toHaveBeenCalled();
    });

    it('should reset only data', () => {
      stateService.resetData = vi.fn();

      service.resetData();

      expect(stateService.resetData).toHaveBeenCalled();
    });
  });

  // ==========================================
  // HELPER FUNCTIONS
  // ==========================================

  function createLoadParams() {
    return {
      employerId: '123',
      taxYear: '2023-2024',
      month: 1,
      payrollDate: '2024-01-31',
      sortBy: 'name',
      sortDescending: false,
    };
  }
});
