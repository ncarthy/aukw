# Quick Integration Steps for API Messages

## 1. Export New Models and Services

### Update `src/app/_models/index.ts`:
```typescript
// Add to existing exports
export * from './api-response.model';
```

### Update `src/app/_services/index.ts`:
```typescript
// Add to existing exports
export * from './api-response-handler.service';
```

## 2. Update Backend Controller (Example)

```php
<?php
// In controllers/quickbooks/qbpayrollquery.controller.php

use Models\ApiResponse;
use Services\PayrollDataProcessor;

// Replace this:
echo json_encode($payslips);

// With this:
$processor = new PayrollDataProcessor();
$response = $processor->processPayslips($payslips);
echo $response->toJson();
```

## 3. Update Frontend Service

```typescript
// In src/app/_services/quickbooks/qb-payroll.service.ts

import { ApiResponse } from '@app/_models';
import { ApiResponseHandler } from '@app/_services';

@Injectable({ providedIn: 'root' })
export class QBPayrollService {
  private responseHandler = inject(ApiResponseHandler);

  // Change return type and add pipe
  getWhatsAlreadyInQBO(realmID: string, payrollDate: string): Observable<IrisPayslip[]> {
    const monthYear = this.getYearAndMonth(payrollDate);

    return this.http.get<ApiResponse<IrisPayslip[]>>(
      `${baseUrl}/${realmID}/query/payroll/${monthYear.year}/${monthYear.month}`
    ).pipe(
      map(response => this.responseHandler.handleResponse(response))
    );
  }
}
```

## 4. No Component Changes Needed!

Your components work exactly as before:

```typescript
this.qbPayrollService.getWhatsAlreadyInQBO(realmId, date)
  .subscribe(payslips => {
    // User automatically sees any info messages
    // Process payslips normally
    this.payslips = payslips;
  });
```

## 5. Run Composer Autoload

```bash
cd api
composer dump-autoload
```

## What Happens Now

### When Staffology returns negative pension:

**Backend detects it:**
```php
$response->addInfo(
  'Employee John Doe has negative pension contribution: £-50.00',
  ['employeeId' => 123, 'field' => 'employeePension', 'value' => -50.00]
);
```

**Frontend automatically displays:**
```
ℹ️ Employee John Doe has negative pension contribution: £-50.00
```

**Processing continues normally** - payslips load, journals can be created, etc.

## Testing It

### Backend Test:
```bash
# Add to PayrollDataProcessorTest.php
public function testDetectsNegativePension(): void
{
    $payslips = [
        ['employeeName' => 'John Doe', 'employeePension' => -50.00]
    ];

    $processor = new PayrollDataProcessor();
    $response = $processor->processPayslips($payslips);

    $this->assertTrue($response->hasMessagesOfType('info'));
    $this->assertStringContainsString('negative pension', $response->messages[0]['message']);
}
```

### Frontend Test:
```typescript
// Add to api-response-handler.service.spec.ts
it('should display info message for negative pension', () => {
  const response: ApiResponse = {
    success: true,
    data: [],
    messages: [{
      type: 'info',
      message: 'Employee has negative pension',
      context: { field: 'pension', value: -50 }
    }]
  };

  handler.handleResponse(response);

  expect(alertService.info).toHaveBeenCalled();
});
```

## Advanced: Conditional Behavior Based on Messages

```typescript
// If you want to do something special when there are warnings:

this.http.get<ApiResponse<IrisPayslip[]>>('/api/payroll')
  .subscribe(response => {
    const data = this.responseHandler.handleResponse(response);

    // Check if there were issues
    if (this.responseHandler.hasIssues(response)) {
      // Maybe highlight affected employees in the UI
      const warningMessages = this.responseHandler.getMessagesByType(response, 'warning');
      this.highlightEmployeesWithIssues(warningMessages);
    }

    this.payslips = data;
  });
```

## Summary

✅ **Non-blocking** - Info messages don't stop processing
✅ **Automatic** - Messages display without component changes
✅ **Flexible** - Support info, warning, success, error
✅ **Contextual** - Include employee IDs, amounts, etc.
✅ **Testable** - Both backend and frontend can be unit tested
✅ **Backward compatible** - Existing endpoints still work

The user sees helpful info without disrupting their workflow! 🎉
