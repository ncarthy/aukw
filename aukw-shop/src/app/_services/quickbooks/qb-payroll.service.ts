import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import {
  Observable,
  forkJoin,
  of,
  BehaviorSubject,
  Subject,
  switchMap,
  from,
} from 'rxjs';
import { defaultIfEmpty, map, tap, mergeMap, toArray } from 'rxjs/operators';

import { environment } from '@environments/environment';
import {
  ApiMessage,
  EmployeeAllocation,
  EmployeeAllocations,
  EmployeeName,
  IrisPayslip,
  LineItemDetail,
  PayrollJournalEntry,
  ValueStringIdPair,
} from '@app/_models';
import {
  isEqualPay,
  isEqualPension,
  isEqualEmployerNI,
  isEqualShopPay,
} from '@app/_helpers';
import { QBEntityService, QBEmployeeService, PayrollService } from '@app/_services';

const baseUrl = `${environment.apiUrl}/qb`;

/**
 * This class performs a number of payroll-related tasks on QuickBooks
 */
@Injectable({ providedIn: 'root' })
export class QBPayrollService {
  private http = inject(HttpClient);
  private qbEntityService = inject(QBEntityService);
  private qbEmployeeService = inject(QBEmployeeService);
  private payrollService = inject(PayrollService);

  private allocationsSubject = new BehaviorSubject<EmployeeAllocation[]>([]);
  private payslipsSubject = new BehaviorSubject<IrisPayslip[]>([]);
  private payrollDateSubject = new Subject<string>();

  allocations$ = this.allocationsSubject.asObservable();
  payslips$ = this.payslipsSubject.asObservable();
  payrollDate$ = this.payrollDateSubject.asObservable();

  /**
   * Set a new value for the 2 BehaviorSubjects
   * @param payslips
   */
  sendPayslips(payslips: IrisPayslip[]) {
    this.payslipsSubject.next(payslips);
    this.payrollDateSubject.next(payslips[0].payrollDate);
  }

  /**
   * Get the Month and Year of the payroll date. Both are strings, the year is in the format
   * 'YYYY' and the month is in the format 'MM'. For example 25/3/2024 will return
   * { month: '03',year: '2024'}
   * @param payrollDate The date the payroll run is for. Usually the 25th of the month..
   * @returns
   */
  private getYearAndMonth(payrollDate: string) {
    const dt = new Date(payrollDate + 'T12:00:00');
    return {
      month: (dt.getMonth() + 1).toString().padStart(2, '0'),
      year: dt.getFullYear().toString(),
    };
  }

  /**
   * Query QuickBooks online for all payroll-related transactions for a given
   * month and year. The payroll transactions are identified by having a DocNumber
   * of the format 'Payroll-YYYY-MM....'.
   * The transactions are then converted by the API into IrisPayslip objects for
   * each employee.
   * @param realmID The QuickBooks ID of the company file.
   * @param payrollDate The transaction date of the journal entry.
   * @returns An array of payslips, one for each employee, or an empty array.
   */
  getWhatsAlreadyInQBO(realmID: string, payrollDate: string) {
    const monthYear = this.getYearAndMonth(payrollDate);
    return this.http.get<IrisPayslip[]>(
      `${baseUrl}/${realmID}/query/payroll/${monthYear.year}/${monthYear.month}`,
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
    classes: ValueStringIdPair[] = [],
    employees: EmployeeName[] = [],
  ): Observable<EmployeeAllocation[]> {
    var classes$: Observable<ValueStringIdPair[]> = this.qbEntityService
      .getAllClasses(environment.qboCharityRealmID)
      .pipe(defaultIfEmpty([]));
    if (classes && classes.length) {
      classes$ = of(classes);
    }

    var employees$: Observable<EmployeeName[]> = this.qbEmployeeService
      .getAll(environment.qboCharityRealmID)
      .pipe(defaultIfEmpty([]));
    if (employees && employees.length) {
      employees$ = of(employees);
    }

    return forkJoin({
      classes: classes$,
      employees: employees$,
      allocations: this.http.get<EmployeeAllocation[]>(
        `${environment.apiUrl}/allocations`,
      ),
    }).pipe(
      switchMap((x) => {
        x.allocations.forEach((element) => {
          element.className =
            x.classes.find((c) => c.id === element.class)?.value ?? '';
          element.name =
            x.employees.find((e) => e.payrollNumber === element.payrollNumber)
              ?.name ?? '';
        });
        return of(x.allocations);
      }),
      tap((result) => this.allocationsSubject.next(result)),
    );
  }

  /**
   * Create a new journal entry in the Charity QuickBooks file that records the Employer NI amounts and
   * account and class allocations.
   * @param params An array of LineItemDetails that specify the employee NI amount and account/class pairs.
   * @param payrollDate The transaction date of the journal entry.
   * @returns A success or failure message. A success message will have the quickbooks id of the new transaction.
   */
  createEmployerNIJournal(
    params: LineItemDetail[],
    payrollDate: string,
  ): Observable<ApiMessage> {
    return this.http.post<any>(
      `${baseUrl}/${environment.qboCharityRealmID}/journal/employerni?payrolldate=${payrollDate}`,
      params,
    );
  }

  /**
   * Create a new general journal entry in the Charity QuickBooks file that records the salary and deductions
   * for a single employee.
   * @param params An array of PayrollJournalEntry that specify the employee salary and deductions and account/class pairs.
   * @param payrollDate The transaction date of the journal entry.
   * @returns A success or failure message. A success message will have the quickbooks id of the new transaction.
   */
  createEmployeeJournal(params: PayrollJournalEntry, payrollDate: string) {
    return this.http.post<ApiMessage>(
      `${baseUrl}/${environment.qboCharityRealmID}/journal/employee?payrolldate=${payrollDate}`,
      params,
    );
  }

  /**
   * Create a new pension invoice in the Charity QuickBooks file that records the Employer pension amounts
   * and account and class allocations.
   * @param params An array that specify the employee pension amount and account/class pairs.
   * @param payrollDate The transaction date of the journal entry.
   * @returns A success or failure message. A success message will have the quickbooks id of the new transaction.
   */
  createPensionBill(params: any, payrollDate: string) {
    return this.http.post<ApiMessage>(
      `${baseUrl}/${environment.qboCharityRealmID}/bill/pensions?payrolldate=${payrollDate}`,
      params,
    );
  }

  /**
   * Create a new general journal entry in the shop QuickBooks file that records the cost of employing
   * the shop employees.
   * @param params An array that specifies the employee costs
   * @param payrollDate The transaction date of the journal entry.
   * @returns A success or failure message. A success message will have the quickbooks id of the new transaction.
   */
  createShopJournal(params: any, payrollDate: string) {
    return this.http.post<ApiMessage>(
      `${baseUrl}/${environment.qboEnterprisesRealmID}/journal/enterprises?payrolldate=${payrollDate}`,
      params,
    );
  }

  /**
   * Orchestrate creation of all payroll-related QuickBooks transactions
   *
   * This method handles the complete payroll workflow:
   * 1. Employee journals (one per employee)
   * 2. Employer NI journal (single consolidated journal)
   * 3. Pension bills (if applicable)
   * 4. Shop journal (for shop employees only)
   *
   * Transactions are filtered to exclude entries already in QuickBooks.
   * All transactions are created in parallel where possible using forkJoin.
   *
   * @param payslips Array of payslips for all employees
   * @param allocations Array of cost allocation rules
   * @param payrollDate The transaction date for all entries
   * @returns Observable that emits results of all transaction creations
   */
  createQBOEntries(
    payslips: IrisPayslip[],
    allocations: EmployeeAllocation[],
    payrollDate: string
  ): Observable<any> {
    // Validate inputs
    if (!payslips || payslips.length === 0) {
      return of({ error: 'No payslips provided' });
    }

    if (!allocations || allocations.length === 0) {
      return of({ error: 'No allocations provided' });
    }

    // Prepare all transaction observables
    const transactions: Observable<any>[] = [];

    // 1. Employee Journals - Create one journal per employee
    // Filter out employees whose journals are already in QBO
    const employeeJournalsToCreate$ = this.payrollService
      .employeeJournalEntries(payslips, allocations)
      .pipe(toArray())
      .pipe(
        switchMap((journalEntries) => {
          // Filter out entries already in QBO
          const filteredEntries = journalEntries.filter((entry) => {
            const payslip = payslips.find(
              (p) => p.payrollNumber === entry.payrollNumber
            );
            return payslip && !payslip.payslipJournalInQBO;
          });

          if (filteredEntries.length === 0) {
            return of({ employeeJournals: 'All employee journals already in QBO' });
          }

          // Create journals in parallel for each employee
          return from(filteredEntries).pipe(
            mergeMap((entry) =>
              this.createEmployeeJournal(entry, payrollDate).pipe(
                map((result) => ({
                  type: 'employeeJournal',
                  payrollNumber: entry.payrollNumber,
                  result,
                }))
              )
            ),
            toArray(),
            map((results) => ({ employeeJournals: results }))
          );
        })
      );

    transactions.push(employeeJournalsToCreate$);

    // 2. Employer NI Journal - Single consolidated journal for all employer NI
    // Only create if not all payslips have NI journal already in QBO
    const needsEmployerNIJournal = payslips.some((p) => !p.niJournalInQBO);

    if (needsEmployerNIJournal) {
      const employerNIJournal$ = this.payrollService
        .employerNIAllocatedCosts(payslips, allocations)
        .pipe(toArray())
        .pipe(
          switchMap((lineItems) => {
            // Filter line items for employees not yet in QBO
            const filteredItems = lineItems.filter((item) => {
              const payslip = payslips.find(
                (p) => p.payrollNumber === item.payrollNumber
              );
              return payslip && !payslip.niJournalInQBO;
            });

            if (filteredItems.length === 0) {
              return of({ employerNIJournal: 'Already in QBO' });
            }

            return this.createEmployerNIJournal(filteredItems, payrollDate).pipe(
              map((result) => ({ employerNIJournal: result }))
            );
          })
        );

      transactions.push(employerNIJournal$);
    }

    // 3. Pension Bill - Single bill for all employee pensions
    // Only create if not all payslips have pension bill already in QBO
    const needsPensionBill = payslips.some((p) => !p.pensionBillInQBO);

    if (needsPensionBill) {
      const pensionBill$ = this.payrollService
        .pensionAllocatedCosts(payslips, allocations)
        .pipe(toArray())
        .pipe(
          switchMap((lineItems) => {
            // Filter line items for employees not yet in QBO
            const filteredItems = lineItems.filter((item) => {
              const payslip = payslips.find(
                (p) => p.payrollNumber === item.payrollNumber
              );
              return payslip && !payslip.pensionBillInQBO;
            });

            if (filteredItems.length === 0) {
              return of({ pensionBill: 'Already in QBO' });
            }

            // Calculate totals for pension bill
            const totalSalarySacrifice = payslips
              .filter((p) => !p.pensionBillInQBO)
              .reduce((sum, p) => sum + p.salarySacrifice, 0);

            const totalEmployeePension = payslips
              .filter((p) => !p.pensionBillInQBO)
              .reduce((sum, p) => sum + p.employeePension, 0);

            const pensionCostsTotal = filteredItems.reduce(
              (sum, item) => sum + item.amount,
              0
            );

            const params = {
              salarySacrificeTotal: totalSalarySacrifice.toFixed(2),
              employeePensionTotal: totalEmployeePension.toFixed(2),
              pensionCosts: filteredItems,
              total: (
                totalEmployeePension +
                totalSalarySacrifice +
                pensionCostsTotal
              ).toFixed(2),
            };

            return this.createPensionBill(params, payrollDate).pipe(
              map((result) => ({ pensionBill: result }))
            );
          })
        );

      transactions.push(pensionBill$);
    }

    // 4. Shop Journal - For shop employees only
    const shopEmployees = payslips.filter(
      (p) => p.isShopEmployee && !p.shopJournalInQBO
    );

    if (shopEmployees.length > 0) {
      const shopJournal$ = forkJoin({
        shopPayslips: of(shopEmployees),
        employees: this.qbEmployeeService.getAll(
          environment.qboEnterprisesRealmID
        ),
      }).pipe(
        map((x) => {
          // Map to format expected by API
          return x.shopPayslips.map((payslip) => {
            const employeeName = x.employees.find(
              (emp) => emp.payrollNumber === payslip.payrollNumber
            );

            return new IrisPayslip({
              payrollNumber: payslip.payrollNumber,
              quickbooksId: employeeName?.quickbooksId || 0,
              employeeName: employeeName?.name || '',
              totalPay: payslip.totalPay,
              employerNI: payslip.employerNI,
              employerPension: payslip.employerPension,
            });
          });
        }),
        switchMap((shopPayslipData) => {
          if (shopPayslipData.length === 0) {
            return of({ shopJournal: 'No shop employees to process' });
          }

          return this.createShopJournal(shopPayslipData, payrollDate).pipe(
            map((result) => ({ shopJournal: result }))
          );
        })
      );

      transactions.push(shopJournal$);
    }

    // If no transactions to create, return early
    if (transactions.length === 0) {
      return of({
        message: 'All transactions already in QuickBooks',
        results: [],
      });
    }

    // Execute all transactions in parallel
    return forkJoin(transactions).pipe(
      map((results) => ({
        message: 'Successfully created payroll transactions',
        results,
      }))
    );
  }

  /**
   * Set the 'in Charity QuickBooks' flags for a given array of payslips. There are 3 flags:
   *  i) Is the employer NI amount entered in QB?
   *  ii) Are the employee salary and deductions entered in QB?
   *  iii) Is the employer pension amount entered in QB?
   * The function takes the given payslips, sets or unsets the boolean flags for each payslip
   * and then returns the amended array of payslips.
   * @param irisPayslips An array of payslips obtained from Iris/FMP, our payroll provider
   * @param payrollDate The date of the payroll run. Usually the 25th of the month.
   * @returns An array of payslips, one for each employee, or an empty array.
   */
  payslipFlagsForCharity(
    irisPayslips: IrisPayslip[],
    payrollDate: string,
  ): Observable<IrisPayslip[]> {
    return forkJoin({
      qbPayslips: this.getWhatsAlreadyInQBO(
        environment.qboCharityRealmID,
        payrollDate,
      ).pipe(defaultIfEmpty([])),
      payrollPayslips: of(irisPayslips),
    }).pipe(
      map((x) => {
        x.payrollPayslips.forEach((payslip) => {
          const qbPayslip =
            x.qbPayslips.find(
              (item) => item.payrollNumber == payslip.payrollNumber,
            ) ?? new IrisPayslip();

          if (qbPayslip) {
            // isEqualEmployerNI() is defined in @app/_helpers/payslip-comparer.ts
            payslip.niJournalInQBO = isEqualEmployerNI(payslip, qbPayslip);
            payslip.pensionBillInQBO = isEqualPension(payslip, qbPayslip);
            payslip.payslipJournalInQBO = isEqualPay(payslip, qbPayslip);
          }
        });
        return x.payrollPayslips;
      }),
    );
  }

  /**
   * Set the 'in Enterprises QuickBooks' flag for a given array of payslips. The flags is
   * set or unset by reference to the employee salary, employer NI and employer pension amounts.
   * The function takes the given payslips, sets or unsets the boolean flags for each payslip
   * and then returns the amended array of payslips.
   * @param irisPayslips An array of payslips obtained from Iris/FMP, our payroll provider
   * @param payrollDate The date of the payroll run. Usually the 25th of the month.
   * @returns An array of payslips, one for each employee, or an empty array.
   */
  payslipFlagsForShop(
    irisPayslips: IrisPayslip[],
    payrollDate: string,
  ): Observable<IrisPayslip[]> {
    return forkJoin({
      qbPayslips: this.getWhatsAlreadyInQBO(
        environment.qboEnterprisesRealmID,
        payrollDate,
      ).pipe(defaultIfEmpty([])),
      payrollPayslips: of(irisPayslips),
    }).pipe(
      map((x) => {
        x.payrollPayslips.forEach((payslip) => {
          const qbPayslip = x.qbPayslips.find(
            (item) => item.payrollNumber == payslip.payrollNumber,
          );
          if (qbPayslip) {
            payslip.shopJournalInQBO = isEqualShopPay(payslip, qbPayslip);
          }
        });
        return x.payrollPayslips;
      }),
    );
  }
}
