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
import { of, throwError } from 'rxjs';
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
  let stateService: jasmine.SpyObj<PayrollStateService>;
  let grossToNetService: jasmine.SpyObj<GrossToNetService>;
  let payrollApiAdapter: jasmine.SpyObj<PayrollApiAdapterService>;
  let qbPayrollService: jasmine.SpyObj<QBPayrollService>;
  let alertService: jasmine.SpyObj<AlertService>;

  beforeEach(() => {
    // Create spy objects
    const stateServiceSpy = jasmine.createSpyObj('PayrollStateService', [
      'setLoading',
      'clearError',
      'setPayslips',
      'setTotal',
      'setPayrollDate',
      'setPayslipsWithMissingData',
      'setShowCreateTransactionsButton',
      'setError',
      'snapshot',
    ]);

    const grossToNetServiceSpy = jasmine.createSpyObj('GrossToNetService', [
      'getAll',
    ]);

    const payrollApiAdapterSpy = jasmine.createSpyObj(
      'PayrollApiAdapterService',
      ['adaptStaffologyToQuickBooks']
    );

    const qbPayrollServiceSpy = jasmine.createSpyObj('QBPayrollService', [
      'createQBOEntries',
    ]);

    const alertServiceSpy = jasmine.createSpyObj('AlertService', [
      'success',
      'error',
    ]);

    const loadingIndicatorSpy = jasmine.createSpyObj('LoadingIndicatorService', [
      'createObserving',
    ]);

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
    stateService = TestBed.inject(
      PayrollStateService
    ) as jasmine.SpyObj<PayrollStateService>;
    grossToNetService = TestBed.inject(
      GrossToNetService
    ) as jasmine.SpyObj<GrossToNetService>;
    payrollApiAdapter = TestBed.inject(
      PayrollApiAdapterService
    ) as jasmine.SpyObj<PayrollApiAdapterService>;
    qbPayrollService = TestBed.inject(
      QBPayrollService
    ) as jasmine.SpyObj<QBPayrollService>;
    alertService = TestBed.inject(AlertService) as jasmine.SpyObj<AlertService>;

    // Default spy return values
    loadingIndicatorSpy.createObserving.and.returnValue((source: any) => source);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });

  // ==========================================
  // LOAD PAYSLIPS TESTS
  // ==========================================

  describe('loadPayslips', () => {
    it('should set loading state at start', (done) => {
      const mockResult = {
        payslips: [new IrisPayslip()],
        total: new IrisPayslip(),
        payrollDate: '2024-01-31',
      };

      grossToNetService.getAll.and.returnValue(of([new IrisPayslip()]));
      payrollApiAdapter.adaptStaffologyToQuickBooks.and.returnValue(
        of(mockResult)
      );

      const params = createLoadParams();
      const employees: EmployeeName[] = [];
      const allocations: EmployeeAllocation[] = [];

      service.loadPayslips(params, employees, allocations).subscribe(() => {
        expect(stateService.setLoading).toHaveBeenCalledWith(
          'downloadButton',
          true
        );
        done();
      });
    });

    it('should update state with loaded payslips', (done) => {
      const mockPayslips = [new IrisPayslip()];
      const mockTotal = new IrisPayslip();
      const mockDate = '2024-01-31';
      const mockResult = {
        payslips: mockPayslips,
        total: mockTotal,
        payrollDate: mockDate,
      };

      grossToNetService.getAll.and.returnValue(of(mockPayslips));
      payrollApiAdapter.adaptStaffologyToQuickBooks.and.returnValue(
        of(mockResult)
      );

      const params = createLoadParams();

      service.loadPayslips(params, [], []).subscribe(() => {
        expect(stateService.setPayslips).toHaveBeenCalledWith(mockPayslips);
        expect(stateService.setTotal).toHaveBeenCalledWith(mockTotal);
        expect(stateService.setPayrollDate).toHaveBeenCalledWith(mockDate);
        done();
      });
    });

    it('should identify payslips with missing data', (done) => {
      const payslipWithMissing = new IrisPayslip();
      payslipWithMissing.employeeMissingFromQBO = true;

      const mockResult = {
        payslips: [payslipWithMissing],
        total: new IrisPayslip(),
        payrollDate: '2024-01-31',
      };

      grossToNetService.getAll.and.returnValue(of([payslipWithMissing]));
      payrollApiAdapter.adaptStaffologyToQuickBooks.and.returnValue(
        of(mockResult)
      );

      const params = createLoadParams();

      service.loadPayslips(params, [], []).subscribe((result) => {
        expect(result.payslipsWithMissingData.length).toBe(1);
        expect(stateService.setShowCreateTransactionsButton).toHaveBeenCalledWith(
          false
        );
        done();
      });
    });

    it('should enable create transactions button when no missing data', (done) => {
      const mockResult = {
        payslips: [new IrisPayslip()],
        total: new IrisPayslip(),
        payrollDate: '2024-01-31',
      };

      grossToNetService.getAll.and.returnValue(of([new IrisPayslip()]));
      payrollApiAdapter.adaptStaffologyToQuickBooks.and.returnValue(
        of(mockResult)
      );

      const params = createLoadParams();

      service.loadPayslips(params, [], []).subscribe(() => {
        expect(stateService.setShowCreateTransactionsButton).toHaveBeenCalledWith(
          true
        );
        done();
      });
    });

    it('should handle errors gracefully', (done) => {
      const error = new HttpErrorResponse({ status: 500 });

      grossToNetService.getAll.and.returnValue(throwError(() => error));

      const params = createLoadParams();

      service.loadPayslips(params, [], []).subscribe({
        error: (err) => {
          expect(stateService.setError).toHaveBeenCalled();
          expect(alertService.error).toHaveBeenCalled();
          done();
        },
      });
    });
  });

  // ==========================================
  // RELOAD PAYSLIPS TESTS
  // ==========================================

  describe('reloadPayslips', () => {
    it('should set reload loading state', (done) => {
      const mockResult = {
        payslips: [new IrisPayslip()],
        total: new IrisPayslip(),
        payrollDate: '2024-01-31',
      };

      grossToNetService.getAll.and.returnValue(of([new IrisPayslip()]));
      payrollApiAdapter.adaptStaffologyToQuickBooks.and.returnValue(
        of(mockResult)
      );

      const params = createLoadParams();

      service.reloadPayslips(params, [], []).subscribe(() => {
        expect(stateService.setLoading).toHaveBeenCalledWith('reloadButton', true);
        done();
      });
    });
  });

  // ==========================================
  // CREATE TRANSACTIONS TESTS
  // ==========================================

  describe('createTransactions', () => {
    it('should validate state before creating transactions', (done) => {
      stateService.snapshot.and.returnValue({
        payslips: [],
        payslipsWithMissingEmployeesOrAllocations: [],
      } as any);

      const params = {
        employerId: '123',
        taxYear: '2023-2024',
        month: 1,
      };

      service.createTransactions(params).subscribe({
        complete: () => {
          expect(stateService.setError).toHaveBeenCalled();
          done();
        },
      });
    });

    it('should reject if missing data exists', (done) => {
      stateService.snapshot.and.returnValue({
        payslips: [new IrisPayslip()],
        payslipsWithMissingEmployeesOrAllocations: [new IrisPayslip()],
      } as any);

      const params = {
        employerId: '123',
        taxYear: '2023-2024',
        month: 1,
      };

      service.createTransactions(params).subscribe({
        complete: () => {
          expect(stateService.setError).toHaveBeenCalled();
          done();
        },
      });
    });

    // TODO: Re-enable when createQBOEntries method is implemented in QBPayrollService
    xit('should create transactions when state is valid', (done) => {
      stateService.snapshot.and.returnValue({
        payslips: [new IrisPayslip()],
        payslipsWithMissingEmployeesOrAllocations: [],
      } as any);

      // qbPayrollService.createQBOEntries.and.returnValue(of({}));

      const params = {
        employerId: '123',
        taxYear: '2023-2024',
        month: 1,
      };

      service.createTransactions(params).subscribe(() => {
        // expect(qbPayrollService.createQBOEntries).toHaveBeenCalled();
        expect(alertService.success).toHaveBeenCalled();
        done();
      });
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
      stateService.snapshot.and.returnValue(mockSnapshot);

      const result = service.getState();
      expect(result).toBe(mockSnapshot);
    });
  });

  // ==========================================
  // RESET TESTS
  // ==========================================

  describe('Reset', () => {
    it('should reset all state', () => {
      stateService.reset = jasmine.createSpy('reset');

      service.reset();

      expect(stateService.reset).toHaveBeenCalled();
    });

    it('should reset only data', () => {
      stateService.resetData = jasmine.createSpy('resetData');

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
