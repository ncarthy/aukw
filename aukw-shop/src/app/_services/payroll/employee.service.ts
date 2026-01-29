import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';

import { environment } from '@environments/environment';
import { EmployeeName } from '@app/_models';
import { Observable } from 'rxjs';

const baseUrl = `${environment.apiUrl}/payroll`;

/**
 * This service provides methods to interact with Employee data
 */
@Injectable({ providedIn: 'root' })
export class EmployeeService {
  private http = inject(HttpClient);

  /**
   * Get a list of the employees for the given employer
   * @param employerID The Staffology Employer ID (uuid format)
   * @returns Array of PayRun objects
   */
  getAll(employerID: string): Observable<EmployeeName[]> {
    return this.http.get<EmployeeName[]>(
      `${baseUrl}/${employerID}/employees`,
    );
  }

  /**
   * Get details about a single employee by supplying Payroll Number
   * @param employerID The Staffology Employer ID (uuid format)
   * @param payrollNumber Iris payroll number for employee
   * @returns Array of PayRun objects
   */
  getByPayrollNumber(employerID: string, payrollNumber: number): Observable<EmployeeName> {
    return this.http.get<EmployeeName>(`${baseUrl}/${employerID}/employees/${payrollNumber}`);
  }

  /**
   * Get details about a single employee by supplying Staffology employee id
   * @param employerID The Staffology Employer ID (uuid format)
   * @param employeeID The Staffology Employee ID (uuid format)
   * @returns Array of PayRun objects
   */
  getByEmployeeId(employerID: string, employeeId: string): Observable<EmployeeName> {
    return this.http.get<EmployeeName>(`${baseUrl}/${employerID}/employees/${employeeId}`);
  }
}
