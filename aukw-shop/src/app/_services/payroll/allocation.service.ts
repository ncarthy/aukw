import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import {
  BehaviorSubject,
  forkJoin,
  defaultIfEmpty,
  Observable,
  of,
  switchMap,
  tap,
} from 'rxjs';

import { environment } from '@environments/environment';
import {
  ApiMessage,
  EmployeeAllocation,
  EmployeeAllocations,
  EmployeeName,
} from '@app/_models';
import {
  AuditLogService,
  AuthenticationService,
  QBEmployeeService,
} from '@app/_services';

const baseUrl = `${environment.apiUrl}/allocations`;

/**
 * This class has a single method which returns a array of PayRuns
 */
@Injectable({ providedIn: 'root' })
export class AllocationsService {
  private http = inject(HttpClient);
  private auditLogService = inject(AuditLogService);
  private authenticationService = inject(AuthenticationService);
  private qbEmployeeService = inject(QBEmployeeService);

  private allocationsSubject = new BehaviorSubject<EmployeeAllocations[]>([]);

  /**
   * Use this Subject to see the most recent set of Employee Allocations from the database.
   */
  allocations$ = this.allocationsSubject.asObservable();

  /**
   * Add allocation(s) to the database.
   * The provided allocation(s) are appended to existing ones.
   * @returns Message of success or failure
   */
  append(params: EmployeeAllocation[]): Observable<ApiMessage> {
    return this.http.post<ApiMessage>(`${baseUrl}/append`, params).pipe(
      tap(() => {
        this.auditLogService.log(
          this.authenticationService.userValue,
          'INSERT',
          `Appended project allocations to table.`,
          'Allocation',
        );
      }),
    );
  }

  /**
   * Delete any allocations for the employee given by the parameter
   * @param payrollNumber The payroll number for the employee
   * @returns Message of success or failure
   */
  deleteEmployeeAllocations(payrollNumber: number): Observable<ApiMessage> {
    return this.http.delete<ApiMessage>(`${baseUrl}/${payrollNumber}`).pipe(
      tap(() => {
        this.auditLogService.log(
          this.authenticationService.userValue,
          'DELETE',
          `Delete project allocations from table for payroll number: ${payrollNumber}.`,
          'Allocation',
          payrollNumber,
        );
      }),
    );
  }

  /**
   * This query returns an array of allocation objects that specify what percentage of
   * employee salary costs must be allocated to what account/class pairs.
   * There will be one or more objects for each employee. The sum of the percentages
   * for each employee must be 100.0.
   * The allocations are stored in the Charity QuickBooks file as a recurring transaction.
   * @returns An array of percentage allocations, one or more for each employee, or an empty array.
   */
  getAllocations(
    employees: EmployeeName[] = [],
  ): Observable<EmployeeAllocations[]> {
    var employees$: Observable<EmployeeName[]>;
    if (employees && employees.length) {
      employees$ = of(employees);
    } else {
      employees$ = this.qbEmployeeService
        .getAll(environment.qboCharityRealmID)
        .pipe(defaultIfEmpty([]));
    }

    return forkJoin({
      employees: employees$,
      allocations: this.http.get<EmployeeAllocation[]>(
        `${environment.apiUrl}/allocations`,
      ),
    }).pipe(
      switchMap((x) => {
        const output: EmployeeAllocations[] = [];

        x.allocations.forEach((element) => {
          const ea = output.find(
            (ea) => ea.name.payrollNumber == element.payrollNumber,
          );
          if (ea) {
            ea.projects.push({
              percentage: element.percentage,
              classID: element.class,
            });
          } else {
            const name = x.employees.find(
              (e) => e.payrollNumber == element.payrollNumber,
            );
            if (name) {
              const allocations = [
                { percentage: element.percentage, classID: element.class },
              ];
              output.push(
                new EmployeeAllocations({ name: name, projects: allocations }),
              );
            }
          }
        });
        return of(output);
      }),
      tap((allocations) => this.allocationsSubject.next(allocations)),
    );
  }

  saveEmployeeAllocations(
    employee: EmployeeAllocations,
  ): Observable<ApiMessage> {
    if (!employee || !employee.name || !employee.name.payrollNumber) {
      new Error('Invalid employee allocations data');
    }

    const isShopEmployee = this.isShopEmployee(employee.projects);

    var editOrAdd$: Observable<ApiMessage>;

    if (!employee.name.quickbooksId || employee.name.quickbooksId === 0) {
      // Must add employee to QBO first
      if (isShopEmployee) {
        editOrAdd$ = this.qbEmployeeService
          .create(environment.qboEnterprisesRealmID, employee.name)
          .pipe(
            // Now add to Charity QBO
            switchMap(() =>
              this.qbEmployeeService.create(
                environment.qboCharityRealmID,
                employee.name,
              ),
            ),
          );
      } else {
        editOrAdd$ = this.qbEmployeeService.create(
          environment.qboCharityRealmID,
          employee.name,
        );
      }

      return editOrAdd$.pipe(
        switchMap((message) => {
          employee.name.quickbooksId = message.id || 0;
          return this.appendAllocationsToEmployee(employee);
        }),
      );
    } else {
      // Just append allocations
      return this.appendAllocationsToEmployee(employee);
    }
  }

  private isShopEmployee(
    projects: { percentage: number; classID: string }[],
  ): boolean {
    if (!projects) return false;
    return projects.some((item) => item.classID === environment.qboShopClass);
  }

  private appendAllocationsToEmployee(
    employee: EmployeeAllocations,
  ): Observable<ApiMessage> {
    const allocationsToAppend: EmployeeAllocation[] = employee.projects.map(
      (proj) =>
        new EmployeeAllocation({
          quickbooksId: employee.name.quickbooksId,
          payrollNumber: employee.name.payrollNumber,
          isShopEmployee: this.isShopEmployee(employee.projects),
          percentage: proj.percentage,
          class: proj.classID,
        }),
    );

    // First delete existing allocations
    return this.deleteEmployeeAllocations(employee.name.payrollNumber).pipe(
      switchMap(() => this.append(allocationsToAppend)),
    );
  }
}
