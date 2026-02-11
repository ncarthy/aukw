import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';

import { environment } from '@environments/environment';
import { ApiMessage, AuditLog, User } from '@app/_models';
import { Observable, of } from 'rxjs';

@Injectable({ providedIn: 'root' })
export class AuditLogService {
  private readonly auditLogUri = `${environment.apiUrl}/auditlog`;

  private http = inject(HttpClient);

  getAll(): Observable<AuditLog[]> {
    return this.http.get<AuditLog[]>(this.auditLogUri);
  }

  getAllEventTypes(): Observable<string[]> {
    return this.http.get<string[]>(`${this.auditLogUri}/eventtype`);
  }

  getFilteredList(urlParameters: string): Observable<AuditLog[]> {
    return this.http.get<AuditLog[]>(`${this.auditLogUri}/?${urlParameters}`);
  }

  log(
    user: User,
    eventtype: string,
    description: string,
    objecttype?: string,
    objectid?: number,
  ) {
    if (!user || (user && !user.id)) return;

    let logentry = new AuditLog();
    logentry.userid = user.id;
    logentry.eventtype = eventtype;
    logentry.description = description;
    if (objecttype) logentry.objecttype = objecttype;
    if (objectid) logentry.objectid = objectid;

    this.http.post(this.auditLogUri, logentry).subscribe();
  }

  logAsync(
    user: User,
    eventtype: string,
    description: string,
    objecttype?: string,
    objectid?: number,
  ): Observable<ApiMessage> {
    if (!user || (user && !user.id))
      return of({ message: 'No user supplied' } as ApiMessage);

    let logentry = new AuditLog();
    logentry.userid = user.id;
    logentry.eventtype = eventtype;
    logentry.description = description;
    if (objecttype) logentry.objecttype = objecttype;
    if (objectid) logentry.objectid = objectid;

    return this.http.post<ApiMessage>(this.auditLogUri, logentry);
  }
}
