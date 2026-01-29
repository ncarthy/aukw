/// <reference types="@angular/localize" />
import { bootstrapApplication } from '@angular/platform-browser';
import { provideRouter } from '@angular/router';
import { provideHttpClient, withInterceptors } from '@angular/common/http';
import {
  enableProdMode,
  LOCALE_ID,
  provideZoneChangeDetection,
} from '@angular/core';

import { AppComponent } from './app/app.component';
import { jwtInterceptor, errorInterceptor } from '@app/_helpers';
import { environment } from './environments/environment';
import { APP_ROUTES } from '@app/app.routes';

if (environment.production) {
  enableProdMode();
}

bootstrapApplication(AppComponent, {
  providers: [
    provideZoneChangeDetection(),
    provideRouter(APP_ROUTES),
    provideHttpClient(withInterceptors([jwtInterceptor, errorInterceptor])),
    { provide: LOCALE_ID, useValue: 'en-GB' },
  ],
});
