import { Component, inject, OnInit } from '@angular/core';
import { NgClass } from '@angular/common';

import { Router, RouterLink, RouterOutlet } from '@angular/router';
import { forkJoin, of, switchMap, tap } from 'rxjs';
import { environment } from '@environments/environment';
import { NgbTooltip } from '@ng-bootstrap/ng-bootstrap';
import {
  AlertService,
  AllocationsService,
  GrossToNetService,
  PayRunService,
  QBClassService,
  QBEmployeeService,
} from '@app/_services';
import {
  EmployeeAllocations,
  EmployeeName,
  IrisPayslip,
  QBClass,
} from '@app/_models';

@Component({
  selector: 'app-allocations',
  imports: [NgClass, NgbTooltip, RouterLink, RouterOutlet],
  templateUrl: './allocations.component.html',
  styleUrl: './allocations.component.css',
})
export class AllocationsComponent implements OnInit {
  classes: QBClass[] = [];
  employees: EmployeeName[] = [];
  allocations: EmployeeAllocations[] = [];
  submitted: boolean = false;
  employeesWithAllocations: EmployeeName[] = [];
  inPayrun: Map<number, boolean> = new Map<number, boolean>();
  loading: boolean = false;

  private realmID: string = environment.qboCharityRealmID;
  private employerID: string = environment.staffologyEmployerID;

  private router = inject(Router);
  private alertService = inject(AlertService);
  private allocationsService = inject(AllocationsService);
  private qbClassService = inject(QBClassService);
  private qbEmployeeService = inject(QBEmployeeService);
  private payrunService = inject(PayRunService);
  private grosstonetReportService = inject(GrossToNetService);

  get nonAllocatedEmployees() {
    return this.employees.filter(
      (e) => !this.employeesWithAllocations.includes(e),
    );
  }

  constructor() {}

  ngOnInit(): void {
    this.loading = true;

    // If allocations have changed then recalcualte the
    // employeesWithAllocations array
    this.allocationsService.allocations$.subscribe(
      (allocs) => (this.allocations = allocs),
    );

    // Start initializing the component by downloading lists of
    //  i) QBO classes; and
    // ii) QBO Employees
    forkJoin({
      employees: this.qbEmployeeService.getAll(this.realmID),
      classes: this.qbClassService.getAllocatableClasses(this.realmID),
    })
      .pipe(
        switchMap((x) => {
          this.classes = x.classes;
          this.employees = x.employees;

          // Get metadata about the most recent closed Pay Run
          // An 'Open' pay run might not yet have employees allocated to it
          return this.payrunService.getLatest(this.employerID);
        }),

        switchMap((payrun) => {
          return forkJoin({
            // Get full details of that last pay run.
            // This will be used to see if some employees can be deleted
            // from the allocations table
            grossToNet: this.grosstonetReportService.getAll(
              this.employerID,
              payrun.taxYear,
              payrun.taxMonth,
              null,
              null,
              false,
            ),
            // Get the full list of Employees
            allocations: this.allocationsService.getAllocations(this.employees),
          });
        }),

        switchMap((x) => {
          x.allocations.forEach((element) => {
            // Ignoring messages in GrossToNet ApiResponse as the allocations endpoint 
            // only returns data, not messages
            this.assignEmployeeByAllocationStatus(element.name, x.grossToNet.data);
          });
          return of(x.allocations);
        }),
      )
      .subscribe({
        next: (value) => (this.allocations = value),
        error: (e) => {
          this.alertService.error(e, { autoClose: false });
        },
      })
      .add(() => (this.loading = false));
  }

  /**
   * From the list of all QBO employees, find those that actually have
   * project allocations. Of those, identify those that are missing
   * from the last pay run.
   * @param employee
   * @param grossToNetPayslips
   */
  private assignEmployeeByAllocationStatus(
    employee: EmployeeName,
    grossToNetPayslips: IrisPayslip[],
  ) {
    if (
      !this.employeesWithAllocations.find(
        (pair) => pair.payrollNumber == employee.payrollNumber,
      )
    ) {
      this.employeesWithAllocations.push(employee);

      // Identify the employees who were not in the last payrun
      this.identifyMissingEmployeesInLastPayrun(
        employee.payrollNumber,
        grossToNetPayslips,
      );
    }
  }

  /**
   * Identify the employees who were not in the last payrun and
   * place them into a component-level boolean-valued Map called inPayrun
   * @param payrollNumber
   * @param grossToNetPayslips
   */
  private identifyMissingEmployeesInLastPayrun(
    payrollNumber: number,
    grossToNetPayslips: IrisPayslip[],
  ) {
    this.inPayrun.set(
      payrollNumber,
      grossToNetPayslips.some(
        (payslip) => payslip.payrollNumber == payrollNumber,
      ),
    );
  }

  /**
   * Remove all allocations for the specified employee.
   * The employee themselves are not removed from QBO.
   * @param employee The employee whose allocations are to be removed
   */
  onRemoveEmployee(employee: EmployeeName) {
    if (employee && employee.payrollNumber) {
      // Delete allocations in the database
      this.allocationsService
        .deleteEmployeeAllocations(employee.payrollNumber)
        .pipe(
          // Update the employeesWithAllocations array
          tap(
            () =>
              (this.employeesWithAllocations =
                this.employeesWithAllocations.filter(
                  (x) => x.payrollNumber != employee.payrollNumber,
                )),
          ),
        )
        .subscribe({
          next: () => {
            this.alertService.success(
              'Allocations deleted for ' + employee.firstName + '.',
              {
                keepAfterRouteChange: true,
              },
            );
          },
          error: (error) => {
            this.alertService.error(
              'Employee allocations not deleted. ' + error,
              {
                autoClose: false,
              },
            );
          },
        });
    }
  }
  /**
   * Get a list of project short names for the specified employee
   * @param en The employee whose projects are to be listed
   */
  employeeProjects(en: EmployeeName): string[] {
    var ea: EmployeeAllocations | undefined = this.allocations.find(
      (a) => a.name.payrollNumber == en.payrollNumber,
    );

    // Projects IS NULL !!
    if (!ea || !ea.projects || !ea.projects.length) return [];

    var output: string[] = [];

    return ea.projects.map((element) => {
      var cls = this.classes.find((clz) => clz.id === element.classID);
      if (cls) {
        return cls.shortName;
      } else {
        return 'Unknown Project';
      }
    });
  }

  /**
   * Reload the allocations data from the database, then find those employees who
   * are in QBO but not in the allocations table.
   */
  reload() {
    this.allocationsService
      .getAllocations(this.employees)
      .pipe(
        switchMap((allocations) => {
          this.employeesWithAllocations = [];

          allocations.forEach((element) => {
            if (
              !this.employeesWithAllocations.find(
                (pair) => pair.payrollNumber == element.name.payrollNumber,
              )
            ) {
              this.employeesWithAllocations.push(element.name);
            }
          });
          return of();
        }),
      )
      .subscribe();
  }
}
