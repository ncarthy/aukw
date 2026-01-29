import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';

import { environment } from '@environments/environment';
import { TaxYear } from '@app/_models';

const baseUrl = `${environment.apiUrl}/payroll/taxyear`;

/**
 * This class has a single method which returns a array of payroll tax year labels
 */
@Injectable({ providedIn: 'root' })
export class TaxYearService {
  private http = inject(HttpClient);

  /**
   * Get a list of the names of all available tax years
   * @returns Array of TaxYear objects
   */
  getAll() {
    return this.http.get<TaxYear[]>(baseUrl);
  }
}
