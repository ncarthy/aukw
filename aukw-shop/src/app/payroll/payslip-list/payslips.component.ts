import { Component, DestroyRef, EventEmitter, inject, OnInit, Output } from '@angular/core';
import { mergeMap, scan, Subject, takeUntil, tap } from 'rxjs';
import { PayslipListComponent } from './list/list.component';
import { QBPayrollService } from '@app/_services';
import { IrisPayslip } from '@app/_models';

@Component({
  imports: [PayslipListComponent],
  templateUrl: './payslips.component.html',
})
export class PayslipsComponent implements OnInit {
  payslips: IrisPayslip[] = [];
  total: IrisPayslip = new IrisPayslip();
  month: number = 0;

  private destroyRef = inject(DestroyRef);
  protected qbPayrollService = inject(QBPayrollService);

  ngOnInit(): void {
    const destroyed = new Subject();
    this.destroyRef.onDestroy(() => {
      destroyed.next('');
      destroyed.complete();
    });

    this.qbPayrollService.payslips$
      .pipe(
        takeUntil(destroyed),
        tap((response) => {
          this.payslips = response;
          this.total = new IrisPayslip();

          // The number of the month: January is 0, February is 1,... December is 11
          if (response && response.length) {
            var monthNumber = new Date(response[0].payrollDate).getMonth();
            // Convert to fiscal month number
            this.month = monthNumber <= 2 ? monthNumber + 10 : monthNumber - 2;
          }
        }),

        // Go from Observable<IrisPayslip[]> to Observable<IrisPayslip>
        mergeMap((dataArray: IrisPayslip[]) => dataArray),

        // loop through all payslips and sum the values to form a
        // "total" payslip that will be put in class level variable
        scan((prev: IrisPayslip, current) => {

          // If we reach a payslip for payrollNumber 7, reset the total
          // Doing this because the 'prev' variable can hold data from
          // previous subscriptions (not sure why). PayrollNumber 7 is
          // the first employee in the payslip list so a new total should start there.
          if (current.payrollNumber === 7) {
            prev = new IrisPayslip();
          }
          return prev.add(current);
        }, new IrisPayslip()),
      )
      .subscribe({
        next: (sumOfAllPayslips: IrisPayslip) => {
          this.total = sumOfAllPayslips;
        },
        error: (err) => console.log(err),
        // Because this is a Subject it will never complete
        //complete: () => {}
      });
  }

  addEmployee(payslip: IrisPayslip) {

  }
}
