import { Component, inject, OnInit } from '@angular/core';
import { AsyncPipe, DecimalPipe } from '@angular/common';
import {
  switchMap,
  from,
  reduce,
  mergeMap,
  of,
  tap,
  scan,
  Observable,
} from 'rxjs';

import { IrisPayslip, LineItemDetail } from '@app/_models';
import { PayrollIdentifier } from '@app/_interfaces/payroll-identifier';
import { PayrollTransactionsService, QBPayrollService } from '@app/_services';
import { AllocatedCostsListComponent } from './allocated-costs-list/list.component';

@Component({
  standalone: true,
  imports: [AllocatedCostsListComponent, AsyncPipe, DecimalPipe],
  templateUrl: './pension-lines-list.component.html',
  styleUrls: ['../shared.css'],
})
export class PensionLinesListComponent implements OnInit {
  lines: LineItemDetail[] = [];
  total: {
    all: number;
    salarySacrifice: number;
    employee: number;
    employer: number;
  } = { all: 0, salarySacrifice: 0, employee: 0, employer: 0 };
  payslips: Observable<IrisPayslip[]> = of([]);

  private payrollTransactionsService = inject(PayrollTransactionsService);
  private qbPayrollService = inject(QBPayrollService);

  ngOnInit() {
    this.payslips = this.qbPayrollService.payslips$;

    this.payrollTransactionsService.pensions$
      .pipe(
        tap((lines) => (this.lines = lines)),

        // loop through all LineItemDetail's and sum the values to form a
        // "total" LineItemDetail that will be put in class level variable
        switchMap((lines: LineItemDetail[]) =>
          from(lines).pipe(
            reduce(
              (prev, curr) => {
                switch (curr.name.substring(0, 10)) {
                  case 'Salary Sac':
                    return {
                      all: prev.all + curr.amount,
                      salarySacrifice: prev.salarySacrifice + curr.amount,
                      employee: prev.employee,
                      employer: prev.employer,
                    };
                  case 'Employee P':
                    return {
                      all: prev.all + curr.amount,
                      salarySacrifice: prev.salarySacrifice,
                      employee: prev.employee + curr.amount,
                      employer: prev.employer,
                    };
                  default:
                    return {
                      all: prev.all + curr.amount,

                      salarySacrifice: prev.salarySacrifice,
                      employee: prev.employee,
                      employer: prev.employer + curr.amount,
                    };
                }
              },
              { all: 0, salarySacrifice: 0, employee: 0, employer: 0 },
            ),
          ),
        ),
      )
      .subscribe((result) => (this.total = result));
  }

  inQBO(line: PayrollIdentifier): boolean {
    return this.payrollTransactionsService.inQBO(line, 'Pensions');
  }

  /** This is the property that the list must check to see if the line is in QBO or not*/
  getQBFlagsProperty() {
    return function (payslip: IrisPayslip) {
      return payslip.pensionBillInQBO;
    };
  }
}
