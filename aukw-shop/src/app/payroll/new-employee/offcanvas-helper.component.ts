import { Component, inject, Input } from '@angular/core';
import { environment } from '@environments/environment';
import { NgbOffcanvas } from '@ng-bootstrap/ng-bootstrap';
import { EmployeeName, IrisPayslip } from '@app/_models';
import { AlertService, EmployeeService } from '@app/_services';
import { from } from 'rxjs';
import { NewEmployeeOffcanvasComponent } from './new-employee-offcanvas.component';

/**
 * Incomplete
 */
@Component({
  template: ``,
  standalone: true,
  imports: [],
})
export class OffcanvasHelperComponent {
  private offcanvasService = inject(NgbOffcanvas);
  private alertService = inject(AlertService);
  private employeeService = inject(EmployeeService);
  private employerID: string = environment.staffologyEmployerID;

  @Input() payslip: IrisPayslip = new IrisPayslip();
  @Input() employees: EmployeeName[] = [];

  launch(): void {
    const offcanvasRef = this.offcanvasService.open(
      NewEmployeeOffcanvasComponent,
    );

    // Pass known values to offcanvas component
    offcanvasRef.componentInstance.payrollNumber = this.payslip.payrollNumber;

    // Pass employee name if not missing
    if (!this.payslip.employeeMissingFromQBO) {
      offcanvasRef.componentInstance.employeeName = this.employees.find(
        (emp) => emp.payrollNumber === this.payslip.payrollNumber,
      );
    } else {
      // Create employee name from payslip data

      this.employeeService
        .getByPayrollNumber(this.employerID, this.payslip.payrollNumber)
        .subscribe({
          next: (emp) => {
            offcanvasRef.componentInstance.employeeName = emp;
          },
        });
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

  reloadEverything() {}
}
