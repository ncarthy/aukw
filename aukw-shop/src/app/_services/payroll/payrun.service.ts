import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';

import { environment } from '@environments/environment';
import { PayRun } from '@app/_models';
import { Observable } from 'rxjs';

const baseUrl = `${environment.apiUrl}/payroll`;

/**
 * This service provides methods to interact with PayRun data
 */
@Injectable({ providedIn: 'root' })
export class PayRunService {
  private http = inject(HttpClient);

  /**
   * Get a list of the names of all available PayRuns
   * @param employerID The Staffology Employer ID (uuid format)
   * @param taxYear The payroll tax year (e.g. 2023/2024)
   * @returns Array of PayRun objects
   */
  getAll(employerID: string, taxYear: string): Observable<PayRun[]> {
    return this.http.get<PayRun[]>(
      `${baseUrl}/${employerID}/payrun/${taxYear}`,
    );
  }

  /**
   * Get the most recent 'Closed' Pay Run
   * An 'Open' pay run might not yet have employees allocated to it.
   * @param employerID The Staffology Employer ID (uuid format)
   * @returns Array of PayRun objects
   */
  getLatest(employerID: string): Observable<PayRun> {
    return this.http.get<PayRun>(`${baseUrl}/${employerID}/payrun/most-recent`);
  }
}
