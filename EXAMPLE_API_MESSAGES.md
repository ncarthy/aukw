# API Messages Implementation Example

## Backend Controller Example

```php
<?php
// In your payroll controller

use Models\ApiResponse;
use Services\PayrollDataProcessor;

// Example: Fetch payslips from Staffology and process them
public function getPayslips(string $realmId, string $payrollDate): void
{
    header('Content-Type: application/json');

    try {
        // Fetch data from Staffology API
        $payslips = $this->staffologyService->getPayslips($realmId, $payrollDate);

        // Process and detect issues
        $processor = new PayrollDataProcessor();
        $response = $processor->processPayslips($payslips);

        // Response automatically includes data AND messages
        echo $response->toJson();

    } catch (Exception $e) {
        $errorResponse = new ApiResponse(false, null);
        $errorResponse->addError($e->getMessage());
        echo $errorResponse->toJson();
        http_response_code(500);
    }
}
```

## Example API Response

### Successful response with informational messages:

```json
{
  "success": true,
  "data": [
    {
      "payrollNumber": 123,
      "employeeName": "John Doe",
      "totalPay": 3000,
      "employeePension": -50.00,
      "netPay": 2500
    },
    {
      "payrollNumber": 456,
      "employeeName": "Jane Smith",
      "totalPay": 0,
      "netPay": 0
    }
  ],
  "messages": [
    {
      "type": "info",
      "message": "Employee John Doe has negative pension contribution: £-50.00",
      "context": {
        "employeeId": 123,
        "employeeName": "John Doe",
        "payrollNumber": 123,
        "field": "employeePension",
        "value": -50.00
      },
      "timestamp": "2026-02-09 17:00:00"
    },
    {
      "type": "info",
      "message": "Employee Jane Smith has zero total pay for this period",
      "context": {
        "employeeId": 456,
        "employeeName": "Jane Smith"
      },
      "timestamp": "2026-02-09 17:00:00"
    }
  ]
}
```

### Error response:

```json
{
  "success": false,
  "data": null,
  "messages": [
    {
      "type": "error",
      "message": "Failed to connect to Staffology API",
      "context": {},
      "timestamp": "2026-02-09 17:00:00"
    }
  ]
}
```

## Frontend Implementation

### 1. Update TypeScript Model

```typescript
// src/app/_models/api-response.model.ts

export interface ApiMessage {
  type: 'info' | 'warning' | 'success' | 'error';
  message: string;
  context?: any;
  timestamp?: string;
}

export interface ApiResponse<T = any> {
  success: boolean;
  data: T;
  messages: ApiMessage[];
}
```

### 2. Create Response Handler Service

```typescript
// src/app/_services/api-response-handler.service.ts

import { Injectable, inject } from '@angular/core';
import { AlertService } from '@app/_services';
import { ApiResponse, ApiMessage } from '@app/_models';

@Injectable({ providedIn: 'root' })
export class ApiResponseHandler {
  private alertService = inject(AlertService);

  /**
   * Handle API response and display any messages to the user
   */
  handleResponse<T>(response: ApiResponse<T>): T {
    // Display all messages
    if (response.messages && response.messages.length > 0) {
      response.messages.forEach(msg => this.displayMessage(msg));
    }

    return response.data;
  }

  /**
   * Display a single message using the alert service
   */
  private displayMessage(message: ApiMessage): void {
    const options = {
      autoClose: message.type === 'success',
      keepAfterRouteChange: false
    };

    switch (message.type) {
      case 'info':
        this.alertService.info(message.message, options);
        break;
      case 'warning':
        this.alertService.warn(message.message, options);
        break;
      case 'success':
        this.alertService.success(message.message, options);
        break;
      case 'error':
        this.alertService.error(message.message, options);
        break;
    }
  }

  /**
   * Get messages of a specific type
   */
  getMessagesByType(response: ApiResponse, type: string): ApiMessage[] {
    return response.messages.filter(msg => msg.type === type);
  }

  /**
   * Check if response has any warnings or errors
   */
  hasIssues(response: ApiResponse): boolean {
    return response.messages.some(
      msg => msg.type === 'warning' || msg.type === 'error'
    );
  }
}
```

### 3. Use in Your Service

```typescript
// Update your payroll service to use ApiResponse

import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { ApiResponse, IrisPayslip } from '@app/_models';
import { ApiResponseHandler } from '@app/_services';

@Injectable({ providedIn: 'root' })
export class QBPayrollService {
  private http = inject(HttpClient);
  private responseHandler = inject(ApiResponseHandler);

  getPayslips(realmId: string, payrollDate: string): Observable<IrisPayslip[]> {
    return this.http
      .get<ApiResponse<IrisPayslip[]>>(`/api/payroll/${realmId}/${payrollDate}`)
      .pipe(
        map(response => {
          // This will display any info/warning messages to the user
          // but still return the data for processing
          return this.responseHandler.handleResponse(response);
        })
      );
  }
}
```

### 4. Component Usage

```typescript
// In your component

loadPayslips(): void {
  this.payrollService.getPayslips(this.realmId, this.payrollDate)
    .subscribe({
      next: (payslips) => {
        // Data is processed normally
        this.payslips = payslips;

        // User has already seen any info messages via alerts
        // Processing continues regardless
      },
      error: (err) => {
        this.alertService.error('Failed to load payslips');
      }
    });
}
```

## What the User Sees

When a negative pension is detected:

1. **Info Alert appears** (blue/cyan notification):
   ```
   ℹ️ Employee John Doe has negative pension contribution: £-50.00
   ```

2. **Data loads normally** - The payslips are displayed in the UI

3. **Processing continues** - User can create journals, review data, etc.

4. **Alert auto-closes** after a few seconds (or user can dismiss)

## Benefits

✅ **Non-blocking** - Processing continues despite warnings
✅ **User awareness** - User is informed of edge cases
✅ **Context available** - Message includes relevant data
✅ **Type safety** - TypeScript interfaces enforce structure
✅ **Flexible** - Support for info, warning, success, error messages
✅ **Traceable** - Timestamps and context help debugging

## Message Type Guidelines

- **`info`** - FYI messages that don't indicate problems (e.g., negative pension, zero pay)
- **`warning`** - Potential issues that should be reviewed (e.g., unusually high deductions)
- **`success`** - Confirmation messages (e.g., "Journals created successfully")
- **`error`** - Actual errors that prevent operation (handled separately via exceptions)

## Testing

```typescript
describe('ApiResponseHandler', () => {
  it('should display info message', () => {
    const response: ApiResponse = {
      success: true,
      data: [],
      messages: [{
        type: 'info',
        message: 'Test info message',
        context: {}
      }]
    };

    handler.handleResponse(response);

    expect(alertService.info).toHaveBeenCalledWith(
      'Test info message',
      jasmine.any(Object)
    );
  });
});
```
