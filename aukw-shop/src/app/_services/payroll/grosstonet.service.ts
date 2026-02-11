import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';

import { environment } from '@environments/environment';
import { IrisPayslip, ApiResponse } from '@app/_models';
import { Observable } from 'rxjs';

const baseUrl = `${environment.apiUrl}/payroll`;

/**
 * This class has a single method which returns a array of Payslips
 */
@Injectable({ providedIn: 'root' })
export class GrossToNetService {
  private http = inject(HttpClient);

  /**
   * Get a array of Payslips for the given employer, tax year and month
   * @param employerID The Staffology employer ID
   * @param taxYear The Staffology tax year value, for example 'Year2024'
   * @param month The month number (1-12). April is month 1, May is month 2 etc.
   * @param payrollDate The date the payroll run is for. Usually the 25th of the month.
   * @param sortBy The field to sort by. If null, defaults to 'PayrollCode'
   * @param sortDescending Whether to sort in descending order
   * @returns Array of PayRun objects
   */
  getAll(
    employerID: string,
    taxYear: string,
    month: number,
    payrollDate: string | null,
    sortBy: string | null,
    sortDescending: boolean,
  ): Observable<ApiResponse<IrisPayslip[]>> {
    return this.http.get<ApiResponse<IrisPayslip[]>>(
      `${baseUrl}/${employerID}/reports/gross-to-net/${taxYear}/month/${month}` +
        `?sortBy=${sortBy == null ? 'PayrollCode' : sortBy}` +
        `${payrollDate == null ? '' : '&payrollDate=' + payrollDate}` +
        `&sortDescending=${sortDescending ? 'true' : 'false'}`,
    );
  }
}
