# PayrollDataProcessor Integration Guide

## Where It Fits

`PayrollDataProcessor` should be called **right before returning payslip data** to the frontend. There are two main integration points:

### Integration Point 1: Staffology Report (Primary)
**This is where negative pensions first appear from Staffology API**

### Integration Point 2: QuickBooks Query
**When fetching existing payslips from QuickBooks**

---

## Option 1: Minimal Change (Recommended)

### Update: `controllers/staffology/report.controller.php`

**Before (Line ~50):**
```php
$payslips = ParseGrosstoNetReport::parse($salaryData, $payrollDate);

echo json_encode($payslips, JSON_NUMERIC_CHECK);
```

**After:**
```php
use Models\ApiResponse;
use Services\PayrollDataProcessor;

$payslips = ParseGrosstoNetReport::parse($salaryData, $payrollDate);

// Process and detect issues
$processor = new PayrollDataProcessor();
$response = $processor->processPayslips($payslips);

echo $response->toJson();
```

### Update: `controllers/quickbooks/qbpayrollquery.controller.php`

**Before (Line 50):**
```php
echo json_encode(array_values($payslips));
```

**After:**
```php
use Models\ApiResponse;
use Services\PayrollDataProcessor;

// Process and detect issues
$processor = new PayrollDataProcessor();
$response = $processor->processPayslips(array_values($payslips));

echo $response->toJson();
```

**That's it!** These two changes enable the entire message system.

---

## Option 2: Integrated into PayrollService (More Complete)

If you want deeper integration, add it to the PayrollService:

### Update: `services/PayrollService.php`

Add a new method:

```php
/**
 * Process and validate payslip data, adding informational messages
 *
 * @param array $payslips The payslip data to process
 * @return ApiResponse Response with data and messages
 */
public function processPayslips(array $payslips): ApiResponse
{
    $processor = new PayrollDataProcessor();
    return $processor->processPayslips($payslips);
}
```

Then in controllers:

```php
use Services\PayrollService;

$payslips = ParseGrosstoNetReport::parse($salaryData, $payrollDate);

$service = new PayrollService();
$response = $service->processPayslips($payslips);

echo $response->toJson();
```

---

## Data Flow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. Frontend requests payslips                                   │
│    GET /api/staffology/{employerId}/report/{year}/{month}       │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│ 2. Controller fetches from Staffology API                       │
│    ReportCtl::grossToNetReport()                                │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│ 3. Parse into payslip format                                    │
│    ParseGrosstoNetReport::parse()                               │
│    Returns: [ {payrollNumber, employeeName, pension: -50}... ]  │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│ 4. NEW: Process with PayrollDataProcessor                       │
│    $processor = new PayrollDataProcessor();                     │
│    $response = $processor->processPayslips($payslips);          │
│                                                                  │
│    Detects:                                                      │
│    - Negative pensions ❌                                        │
│    - Zero pay                                                    │
│    - Unusually high deductions                                  │
│                                                                  │
│    Returns ApiResponse with messages:                           │
│    {                                                             │
│      success: true,                                              │
│      data: [...payslips...],                                    │
│      messages: [                                                 │
│        {                                                         │
│          type: "info",                                           │
│          message: "Employee has negative pension: £-50.00"      │
│        }                                                         │
│      ]                                                           │
│    }                                                             │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│ 5. Return to frontend                                           │
│    echo $response->toJson();                                    │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│ 6. Frontend receives ApiResponse                                │
│    ApiResponseHandler.handleResponse(response)                  │
│    - Displays info messages via AlertService                    │
│    - Returns data for normal processing                         │
└─────────────────────────────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│ 7. User sees:                                                   │
│    ℹ️ Employee John Doe has negative pension: £-50.00          │
│    [Payslips load normally in UI]                               │
└─────────────────────────────────────────────────────────────────┘
```

---

## Complete Example: Staffology Report Controller

Here's the complete modified method:

```php
<?php
// In controllers/staffology/report.controller.php

namespace Controllers\Staffology;

use Core\ErrorResponse as Error;
use Models\Staffology\GrossToNetReport;
use Models\Staffology\ParseGrosstoNetReport;
use Models\ApiResponse;  // ADD THIS
use Services\PayrollDataProcessor;  // ADD THIS
use Exception;

class ReportCtl
{
    public static function grossToNetReport(
        string $employerId,
        string $taxYear,
        int $month,
        string $sortBy = "Name",
        bool $sortDescending = false
    ): void {
        try {
            $dt = new \DateTime("25-{$month}-" . substr($taxYear, 0, 4));
            $payrollDate = $dt->format('Y-m-d');

            $salaryData = GrossToNetReport::getInstance()
              ->setEmployerId($employerId)
              ->setTaxYear($taxYear)
              ->setFromPeriod($month)
              ->setToPeriod($month)
              ->setSortBy($sortBy)
              ->setSortDescending($sortDescending)
              ->generate();

            $payslips = ParseGrosstoNetReport::parse($salaryData, $payrollDate);

            // NEW: Process payslips and detect issues
            $processor = new PayrollDataProcessor();
            $response = $processor->processPayslips($payslips);

            // Return with messages
            echo $response->toJson();

        } catch (Exception $e) {
            // Standardize error response format
            $errorResponse = new ApiResponse(false, null);
            $errorResponse->addError(
                "Error retrieving Gross-To-Net report for " . $taxYear . " Month " . $month,
                ['error' => $e->getMessage()]
            );
            echo $errorResponse->toJson();
            http_response_code(500);
        }
    }
}
```

---

## Complete Example: QB Payroll Query Controller

```php
<?php
// In controllers/quickbooks/qbpayrollquery.controller.php

namespace Controllers\QuickBooks;

use Core\QuickbooksConstants as QBO;
use Models\Payslip;
use Models\QuickbooksQuery;
use Models\QuickbooksEmployee;
use Models\ApiResponse;  // ADD THIS
use Services\PayrollDataProcessor;  // ADD THIS
use Core\ErrorResponse as Error;
use Exception;

class QBPayrollQueryCtl
{
    public static function query(string $realmid, int $year, int $month): void
    {
        try {
            $payslips = array();

            $payrollIdentifier = QBO::payrollDocNumber($year . '-' . $month . '-25');

            $employees = QuickbooksEmployee::getInstance()
              ->setRealmID($realmid)
              ->readAllAssociatedByName();

            $bills = QuickbooksQuery::getInstance()
              ->setRealmID($realmid)
              ->query_by_docnumber('Bill', $payrollIdentifier);

            QBPayrollQueryCtl::parsePensionBills($employees, $bills, $payslips);

            $journals = QuickbooksQuery::getInstance()
              ->setRealmID($realmid)
              ->query_by_docnumber('JournalEntry', $payrollIdentifier);

            QBPayrollQueryCtl::parsePayrollJournals($employees, $journals, $payslips);

            // NEW: Process payslips and detect issues
            $processor = new PayrollDataProcessor();
            $response = $processor->processPayslips(array_values($payslips));

            echo $response->toJson();

        } catch (Exception $e) {
            $errorResponse = new ApiResponse(false, null);
            $errorResponse->addError(
                "Unable to complete the query of payroll entities.",
                ['error' => $e->getMessage()]
            );
            echo $errorResponse->toJson();
            http_response_code(500);
        }
    }

    // ... rest of class
}
```

---

## Adding More Detection Rules

Want to detect other issues? Just add methods to `PayrollDataProcessor`:

```php
// In services/PayrollDataProcessor.php

/**
 * Check for missing QuickBooks employee IDs
 */
private function checkMissingEmployeeIds(array $payslip, ApiResponse $response): void
{
    if (empty($payslip['quickbooksId'])) {
        $response->addWarning(
            sprintf(
                'Employee %s is missing QuickBooks ID - cannot create journal',
                $payslip['employeeName'] ?? 'Unknown'
            ),
            [
                'payrollNumber' => $payslip['payrollNumber'] ?? null,
                'employeeName' => $payslip['employeeName'] ?? null
            ]
        );
    }
}
```

Then call it from `processPayslips()`:

```php
foreach ($payslips as $payslip) {
    $this->checkMissingEmployeeIds($payslip, $response);
    // ... other checks
}
```

---

## Testing the Integration

### 1. Backend Test:
```bash
# Call the endpoint directly
curl http://localhost/api/staffology/employer123/report/2024-2025/1

# Should return:
{
  "success": true,
  "data": [...payslips...],
  "messages": [
    {
      "type": "info",
      "message": "Employee John Doe has negative pension contribution: £-50.00",
      "context": {...}
    }
  ]
}
```

### 2. Frontend Test:
```typescript
// In your component
this.qbPayrollService.getPayslips(realmId, date)
  .subscribe(payslips => {
    // If there were negative pensions, user has already seen the info alert
    console.log('Payslips loaded:', payslips);
  });
```

---

## Run Composer Autoload

After adding the new classes:

```bash
cd api
composer dump-autoload
```

---

## Summary

**Two small changes** to existing controllers enable the entire message system:

1. ✅ Replace `echo json_encode($payslips)` with `PayrollDataProcessor` call
2. ✅ Update frontend to use `ApiResponse<T>` type

**No changes needed to:**
- ❌ Your existing services
- ❌ Your existing models
- ❌ Your component logic (it works automatically)

The messages flow naturally from backend → API response → frontend handler → user notification! 🎉
