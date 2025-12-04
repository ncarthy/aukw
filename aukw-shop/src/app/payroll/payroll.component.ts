import {
  Component,
  DestroyRef,
  inject,
  LOCALE_ID,
  OnInit,
} from '@angular/core';
import { AsyncPipe, formatDate, JsonPipe } from '@angular/common';
import {
  FormBuilder,
  FormGroup,
  ReactiveFormsModule,
  Validators,
} from '@angular/forms';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import {
  from,
  Observable,
  of,
  map,
  retry,
  shareReplay,
  switchMap,
  Subject,
  takeUntil,
} from 'rxjs';
import {
  NgbDateAdapter,
  NgbDateParserFormatter,
  NgbDatepickerModule,
  NgbNavModule,
  NgbOffcanvas,
  NgbTooltip,
} from '@ng-bootstrap/ng-bootstrap';
import { environment } from '@environments/environment';
import {
  GrossToNetService,
  PayRunService,
  TaxYearService,
} from '@app/_services/payroll';
import {
  AlertService,
  LoadingIndicatorService,
  PayrollApiAdapterService,
  PayrollTransactionsService,
  QBEmployeeService,
  QBPayrollService,
} from '@app/_services';
import {
  EmployeeAllocation,
  EmployeeName,
  IrisPayslip,
  PayRun,
  TaxYear,
} from '@app/_models';
import { CustomDateParserFormatter, NgbUTCStringAdapter } from '@app/_helpers';
import { PayslipListComponent } from './payslip-list/list/list.component';
import { PayslipsSummaryComponent } from './payslip-list/summary/payslips-summary.component';
import { NewEmployeeOffcanvasComponent } from './new-employee/new-employee-offcanvas.component';

@Component({
  selector: 'app-payroll',
  imports: [
    AsyncPipe,
    JsonPipe,
    PayslipListComponent,
    PayslipsSummaryComponent,
    ReactiveFormsModule,
    NgbDatepickerModule,
    NgbNavModule,
    NgbTooltip,
    RouterLink,
    RouterLinkActive,
    RouterOutlet,
  ],
  templateUrl: './payroll.component.html',
  styleUrl: './payroll.component.css',
  providers: [
    { provide: NgbDateAdapter, useClass: NgbUTCStringAdapter },
    { provide: NgbDateParserFormatter, useClass: CustomDateParserFormatter },
  ],
})
export class PayrollComponent implements OnInit {
  form!: FormGroup;
  payruns$: Observable<PayRun[]>;
  taxyears$: Observable<TaxYear[]>;
  payslips: IrisPayslip[] = [];
  allocations: EmployeeAllocation[] = [];
  employees: EmployeeName[] = [];
  payrollDate: string = '';
  total: IrisPayslip = new IrisPayslip();
  payslipsWithMissingEmployeesOrAllocations: IrisPayslip[] = [];

  /** 1st value is for the Download button, 2nd is for reload button */
  loading: [boolean, boolean] = [false, false];
  showCreateTransactionsButton: boolean = false;
  active = 1;

  tceByClass$: Observable<[string, string, number][]> = of([]);

  private employerID: string = environment.staffologyEmployerID;
  private realmID: string = environment.qboCharityRealmID;

  private formBuilder = inject(FormBuilder);
  private grossToNetService = inject(GrossToNetService);
  private payRunService = inject(PayRunService);
  private taxYearService = inject(TaxYearService);
  private alertService = inject(AlertService);
  /** Used for allocations$ Observable, which is a public property of PayrollService */
  private qbPayrollService = inject(QBPayrollService);
  /** Used to download list of current employee names */
  private qbEmployeeService = inject(QBEmployeeService);
  private destroyRef = inject(DestroyRef);
  private offcanvasService = inject(NgbOffcanvas);
  private loadingIndicatorService = inject(LoadingIndicatorService);
  private locale = inject(LOCALE_ID);
  private payrollApiAdapterService = inject(PayrollApiAdapterService);
  private payrollTransactionsService = inject(PayrollTransactionsService);

  constructor() {
    this.payruns$ = of([]);
    this.taxyears$ = this.taxYearService.getAll();
  }

  ngOnInit(): void {
    this.form = this.formBuilder.group({
      taxYear: [null, Validators.required],
      month: [null, Validators.required],
      payrollDate: [null],
      sortBy: [null],
      sortDescending: [false],
    });

    this.form.controls['taxYear'].valueChanges.subscribe((value) => {
      this.payruns$ = this.payRunService.getAll(this.employerID, value).pipe(retry(3));
    });

    this.loading[0] = true;

    /**
     * This pattern is used to subscribe to an rxjs Subject and automatically
     * unsubscribe when the object is destroyed. Angular gives us the destroyRef
     * hook to manage this.
     * { @link https://medium.com/@chandrashekharsingh25/exploring-the-takeuntildestroyed-operator-in-angular-d7244c24a43e }
     */
    const destroyed = new Subject();
    this.destroyRef.onDestroy(() => {
      destroyed.next('');
      destroyed.complete();
    });

    this.qbPayrollService.allocations$
      .pipe(takeUntil(destroyed))
      .subscribe((allocations) => {
        this.allocations = allocations;
        this.loading[0] = false;
      });

    this.tceByClass$ = this.payrollTransactionsService.tceByClass$.pipe(
      takeUntil(destroyed),
    );

    // Load employee names and allocations
    this.loadEmployeesAndAllocations().subscribe({
      error: (error: any) => {
        this.alertService.error(error, {
          autoClose: false,
          keepAfterRouteChange: true,
        });
      },
    });
  }

  get f() {
    return this.form.controls;
  }

  onSubmit() {
    this.loading = [true, false];
    this.reloadPayslipsFromAPI();
  }

  reloadPayslipsFromAPI() {
    if (this.form.valid) {
      // Get the pay information from the Staffology api
      const grossToNetReport$ = this.grossToNetService.getAll(
        this.employerID,
        this.f['taxYear'].value,
        this.f['month'].value,
        this.f['payrollDate'].value,
        this.f['sortBy'].value,
        this.f['sortDescending'].value,
      );

      // Adapt the salary info into QuickBooks transaction-ready arrays
      this.payrollApiAdapterService
        .adaptStaffologyToQuickBooks(
          grossToNetReport$,
          this.employees,
          this.allocations,
        )
        .pipe(
          map(
            (o: {
              payslips: IrisPayslip[];
              total: IrisPayslip;
              payrollDate: string;
            }) => {
              // Store module-level array stuff
              this.payslips = o.payslips;
              this.payrollDate = o.payrollDate;
              this.total = o.total;

              // check and flag any payslips for employees who are either
              // i) Not in QuickBooks; or
              // ii) Do not have saved allocations in the database
              return o.payslips.filter(
                (payslip) =>
                  payslip.employeeMissingFromQBO ||
                  payslip.allocationsMissingFromQBO,
              );
            },
          ),

          // Keep user informed
          this.loadingIndicatorService.createObserving({
            loading: () =>
              ' Querying QuickBooks to see if transactions already entered.',
            success: () => `Successfully loaded QuickBooks transactions.`,
            error: (err) => `${err}`,
          }),
          shareReplay(1),
        )
        .subscribe({
          next: (p: IrisPayslip[]) =>
            (this.payslipsWithMissingEmployeesOrAllocations = p),
          error: (error: any) => {
            this.alertService.error(error, {
              autoClose: false,
              keepAfterRouteChange: true,
            });
            this.loading = [false, false];
          },
          complete: () => {
            this.loading = [false, false];

            // Show create transactions button if there are no employees that are
            // missing from QBO or do not have allocations.
            this.showCreateTransactionsButton =
              this.payslipsWithMissingEmployeesOrAllocations &&
              !this.payslipsWithMissingEmployeesOrAllocations.length;
          },
        });
    }
  }

  onEmployeeToAdd(payslip: IrisPayslip) {
    const offcanvasRef = this.offcanvasService.open(
      NewEmployeeOffcanvasComponent,
    );

    // Pass known values to offcanvas component
    offcanvasRef.componentInstance.payrollNumber = payslip.payrollNumber;

    // Pass employee name if not missing
    if (!payslip.employeeMissingFromQBO) {
      offcanvasRef.componentInstance.employeeName = this.employees.find(
        (emp) => emp.payrollNumber === payslip.payrollNumber,
      );
    } else {
      // Create employee name from payslip data
      var firstName = '';
      var lastName = '';
      const nameParts = payslip.employeeName.split(' ');
      if (nameParts.length > 0) {
        firstName = nameParts[0];
        if (firstName == 'Ms' || firstName == 'Mr' || firstName == 'Mrs') {
          // Prefix detected - skip to next part
          if (nameParts.length > 1) {
            firstName = nameParts[1];
            lastName = nameParts.slice(2).join(' ');
          }
        } else {
          lastName = nameParts.slice(1).join(' ');
        }
      }

      offcanvasRef.componentInstance.employeeName = {
        payrollNumber: payslip.payrollNumber,
        firstName: firstName,
        lastName: lastName,
      };
    }

    // Reload everything after offcanvas is closed
    from(offcanvasRef.result).subscribe({
      next: () => this.reloadEverything(),
      error: (error) => {
        if (error !== 'Cross click') {
          this.alertService.error(error, {
            autoClose: false,
            keepAfterRouteChange: true,
          });
        }
      },
    });
  }

  reloadEverything() {
    this.loading[1] = true;
    this.loadEmployeesAndAllocations().subscribe({
      next: () => {
        this.reloadPayslipsFromAPI();
      },
      error: (error: any) => {
        this.alertService.error(error, {
          autoClose: false,
          keepAfterRouteChange: true,
        });
        this.loading[1] = false;
      },
      complete: () => {
        this.loading[1] = false;
      },
    });
  }

  private loadEmployeesAndAllocations(): Observable<EmployeeAllocation[]> {
    return this.qbEmployeeService.getAll(this.realmID).pipe(
      switchMap((employees: EmployeeName[]) => {
        this.employees = employees;
        return this.qbPayrollService.getAllocations();
      }),
    );
  }

  onMonthSelectClicked() {
    if (this.f['month'].value && this.f['taxYear'].value) {
      this.f['payrollDate'].setValue(
        this.calcPayrollDate(
          Number(this.f['month'].value),
          this.f['taxYear'].value,
        ),
      );
    }
  }

  private calcPayrollDate(fiscalMonthNumber: number, taxYear: string): string {
    try {
      // The fiscal month numbers of Jan, Feb, Mar, Apr... etc.
      const months = [10, 11, 12, 1, 2, 3, 4, 5, 6, 7, 8, 9];

      const monthNumber = months.indexOf(fiscalMonthNumber);
      if (monthNumber == -1) {
        // Not Found
        return '';
      }

      const year = Number(taxYear.substring(4));

      const dt = new Date(year, monthNumber, 25);

      return formatDate(dt, 'yyyy-MM-dd', this.locale);
    } catch (error) {
      console.log('Error ocurred calculating payroll date: ' + error);
      return '';
    }
  }

  createQBOEntries() {
    // Show Nav Bar
    // Recalculate Transactions
    // Display transactions
    // Recalculate InQBO flags

    this.payrollTransactionsService.addToQuickBooks();
  }
}
