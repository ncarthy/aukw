import { Component } from '@angular/core';
import { IrisPayslip, LineItemDetail } from '@app/_models';
import { from, merge, Observable, of, shareReplay, tap, toArray } from 'rxjs';
import { BasePayrollTransactionComponent } from './base-transaction.component';

@Component({
  selector: 'pension-invoice',
  standalone: true,
  imports: [],
  template: '',
})
export class PensionInvoiceComponent extends BasePayrollTransactionComponent<LineItemDetail> {
  total: number = 0;
  totalSalarySacrifice: number = 0;
  totalEmployeePension: number = 0;

  override createTransactions(): Observable<LineItemDetail[]> {
    if (!this.payslips.length || !this.allocations.length) return of([]);

    const lines: LineItemDetail[] = [];
    this.total = 0;
    this.totalSalarySacrifice = 0;
    this.totalEmployeePension = 0;

    this.payslips.forEach((p: IrisPayslip) => {
      this.totalSalarySacrifice += p.salarySacrifice;
      this.totalEmployeePension += p.employeePension;
    });

    lines.push(
      new LineItemDetail({
        payrollNumber: 0,
        name: 'Salary Sacrifice total',
        amount: this.totalSalarySacrifice,
        className: '04 Administration',
      }),
    );
    lines.push(
      new LineItemDetail({
        payrollNumber: 0,
        name: 'Employee Pension total',
        amount: this.totalEmployeePension,
        className: '04 Administration',
      }),
    );

    return merge(
      from(lines),
      this.payrollService.pensionAllocatedCosts(
        this.payslips,
        this.allocations,
      ),
    ).pipe(
      tap((line) => {
        this.lines.push(line);
        this.total += line.amount;
      }),
      toArray(),
    );
  }

  /**
   * Create a single new invoice in the Charity QuickBooks file that records the pension amounts and
   * account and class allocations for each employee.
   */
  addToQuickBooks() {
    // Filter out lines for which there is already a QBO entry
    const filteredTransactions = this.filteredTransactions(
      this.getQBFlagsProperty(),
    );

    //Add the invoice
    if (filteredTransactions && filteredTransactions.length) {
      this.qbPayrollService
        .createPensionBill(
          {
            salarySacrificeTotal: this.totalSalarySacrifice.toFixed(2),
            employeePensionTotal: this.totalEmployeePension.toFixed(2),
            pensionCosts: filteredTransactions,
            total: (
              this.totalEmployeePension +
              this.totalSalarySacrifice +
              this.total
            ).toFixed(2),
          },
          this.payrollDate,
        )
        .pipe(
          this.loadingIndicatorService.createObserving({
            loading: () => `Adding pension invoice to QuickBooks`,
            success: (result) =>
              `Successfully created pension invoice with id=${result.id} in QuickBooks.`,
            error: (err) => `${err}`,
          }),
          shareReplay(1),

          // Add entry to audit log
          tap((result) => {
            this.auditLogService.log(
              this.authenticationService.userValue,
              'INSERT',
              `Added pension invoice with id=${result.id} to QuickBooks`,
              'General Journal',
              result.id,
            );
          }),
        )
        .subscribe({
          error: (e) => {
            this.alertService.error(e, { autoClose: false });
          },
          complete: () => {
            this.qbPayrollService.sendPayslips(this.setQBOFlagsToTrue());
          },
        });
    } else {
      this.alertService.info('The pension invoice is already in QuickBooks.');
    }
  }

  /** This is the property that the list must check to see if the line is in QBO or not*/
  override getQBFlagsProperty() {
    return function (payslip: IrisPayslip) {
      return payslip.pensionBillInQBO;
    };
  }
  override setQBFlagsProperty() {
    return function (payslip: IrisPayslip, value: boolean) {
      payslip.pensionBillInQBO = value;
    };
  }
}
