<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Services\PayrollService;
use Services\JournalLineFactory;
use Validators\PayrollValidator;
use Config\PayrollConfig;

/**
 * Integration tests for complete payroll workflow
 *
 * Tests the full stack with mocked external APIs:
 * - Employee journal creation end-to-end
 * - Employer NI journal creation end-to-end
 * - Shop payroll journal creation end-to-end
 * - Error scenarios
 */
class PayrollWorkflowTest extends TestCase
{
    private PayrollService $service;

    protected function setUp(): void
    {
        $this->service = new PayrollService();
    }

    // ==========================================
    // EMPLOYEE JOURNAL WORKFLOW TESTS
    // ==========================================

    public function testCompleteEmployeeJournalWorkflow(): void
    {
        // 1. Prepare data (simulating Staffology API response)
        $employeeData = $this->createSampleEmployeeData();

        // 2. Validate data
        $this->assertTrue(
            $this->service->validateJournalData('employee', $employeeData)
        );

        // 3. Build journal lines using factory
        $factory = $this->service->getFactory();
        $lines = $factory->buildLines('employee', $employeeData);

        // 4. Verify line structure
        $this->assertIsArray($lines);
        $this->assertGreaterThan(0, count($lines));

        // 5. Verify balance
        $this->assertTrue($factory->validateBalance($lines));

        // 6. Build complete journal entry
        $journal = $factory->buildJournalEntry(
            'employee',
            $employeeData,
            '2024-01-31',
            'Payroll_2024_01'
        );

        // 7. Verify journal structure
        $this->assertArrayHasKey('TxnDate', $journal);
        $this->assertArrayHasKey('DocNumber', $journal);
        $this->assertArrayHasKey('Line', $journal);

        // Note: Actual QuickBooks posting would happen here
        // but is mocked in integration tests
    }

    public function testEmployeeJournalWithMultipleAllocations(): void
    {
        $employeeData = [
            'quickbooksEmployeeId' => '123',
            'grossSalary' => [
                (object)[
                    'amount' => 1500,
                    'class' => '100',
                    'account' => '261'
                ],
                (object)[
                    'amount' => 500,
                    'class' => '200',
                    'account' => '261'
                ],
            ],
            'netSalary' => -1500,
            'paye' => -300,
            'employeeNI' => -150,
            'salarySacrifice' => 0,
            'employeePensionContribution' => -50,
            'studentLoan' => 0,
            'otherDeduction' => 0,
        ];

        $factory = $this->service->getFactory();
        $lines = $factory->buildLines('employee', $employeeData);

        // Should have multiple gross salary lines
        $grossSalaryLines = array_filter($lines, function($line) {
            return $line['Description'] === PayrollConfig::getDescription('GROSS_SALARY');
        });

        $this->assertGreaterThanOrEqual(2, count($grossSalaryLines));

        // Verify balance
        $this->assertTrue($factory->validateBalance($lines));
    }

    // ==========================================
    // EMPLOYER NI WORKFLOW TESTS
    // ==========================================

    public function testCompleteEmployerNIWorkflow(): void
    {
        // 1. Prepare data
        $entries = [
            (object)[
                'amount' => 100,
                'quickbooksId' => '123',
                'class' => '100',
                'account' => '95'
            ],
            (object)[
                'amount' => 200,
                'quickbooksId' => '456',
                'class' => '100',
                'account' => '95'
            ],
        ];

        $data = ['entries' => $entries];

        // 2. Validate
        $this->assertTrue(
            $this->service->validateJournalData('employer_ni', $data)
        );

        // 3. Build lines
        $factory = $this->service->getFactory();
        $lines = $factory->buildLines('employer_ni', $data);

        // 4. Verify structure (should have debits + credit total)
        $this->assertEquals(3, count($lines));

        // 5. Verify balance
        $this->assertTrue($factory->validateBalance($lines));

        // 6. Verify last line is credit (total)
        $lastLine = end($lines);
        $this->assertEquals('Credit', $lastLine['JournalEntryLineDetail']['PostingType']);
    }

    // ==========================================
    // SHOP PAYROLL WORKFLOW TESTS
    // ==========================================

    public function testCompleteShopPayrollWorkflow(): void
    {
        // 1. Prepare data
        $entries = [
            (object)[
                'totalPay' => 1000,
                'quickbooksId' => '123',
                'employerNI' => 100,
                'employerPension' => 50,
            ],
        ];

        $data = ['entries' => $entries];

        // 2. Validate
        $this->assertTrue(
            $this->service->validateJournalData('shop_payroll', $data)
        );

        // 3. Build lines
        $factory = $this->service->getFactory();
        $lines = $factory->buildLines('shop_payroll', $data);

        // 4. Verify structure (debit/credit pairs for each component)
        $this->assertGreaterThanOrEqual(2, count($lines));

        // 5. Verify balance
        $this->assertTrue($factory->validateBalance($lines));
    }

    // ==========================================
    // ERROR SCENARIO TESTS
    // ==========================================

    public function testWorkflowRejectsUnbalancedJournal(): void
    {
        $this->expectException(\Errors\ValidationException::class);
        $this->expectExceptionMessage('not balanced');

        // Create data that won't balance
        $data = [
            'quickbooksEmployeeId' => '123',
            'grossSalary' => [
                (object)[
                    'amount' => 2000,
                    'class' => '100',
                    'account' => '261'
                ]
            ],
            'netSalary' => -1000, // Doesn't balance with gross
            'paye' => 0,
            'employeeNI' => 0,
            'salarySacrifice' => 0,
            'employeePensionContribution' => 0,
            'studentLoan' => 0,
            'otherDeduction' => 0,
        ];

        $factory = $this->service->getFactory();
        $journal = $factory->buildJournalEntry('employee', $data, '2024-01-31', 'Test');

        // Should throw when validating
        $factory->validateBalance($journal['Line']);
    }

    public function testWorkflowRejectsMissingRequiredFields(): void
    {
        $this->expectException(\Errors\ValidationException::class);

        $data = [
            // Missing quickbooksEmployeeId
            'grossSalary' => []
        ];

        $this->service->validateJournalData('employee', $data);
    }

    // ==========================================
    // BATCH PROCESSING TESTS
    // ==========================================

    public function testBatchProcessingHandlesMultipleEmployees(): void
    {
        $employees = [
            $this->createSampleEmployeeData('100', 2000),
            $this->createSampleEmployeeData('200', 3000),
            $this->createSampleEmployeeData('300', 2500),
        ];

        $factory = $this->service->getFactory();

        // Process each employee
        $allLines = [];
        foreach ($employees as $employeeData) {
            $lines = $factory->buildLines('employee', $employeeData);
            $this->assertTrue($factory->validateBalance($lines));
            $allLines[] = $lines;
        }

        $this->assertCount(3, $allLines);
    }

    // ==========================================
    // CONFIGURATION INTEGRATION TESTS
    // ==========================================

    public function testWorkflowUsesConfigurationConstants(): void
    {
        $data = $this->createSampleEmployeeData();

        $factory = $this->service->getFactory();
        $lines = $factory->buildLines('employee', $data);

        // Verify that configuration constants are used
        foreach ($lines as $line) {
            // Check that class and account references exist
            $this->assertNotEmpty($line['JournalEntryLineDetail']['ClassRef']);
            $this->assertNotEmpty($line['JournalEntryLineDetail']['AccountRef']);
        }
    }

    // ==========================================
    // VALIDATION INTEGRATION TESTS
    // ==========================================

    public function testWorkflowValidatesAllTransactionTypes(): void
    {
        $transactionTypes = [
            'employee' => $this->createSampleEmployeeData(),
            'employer_ni' => [
                'entries' => [
                    (object)['amount' => 100, 'quickbooksId' => '123', 'class' => '100', 'account' => '95']
                ]
            ],
            'shop_payroll' => [
                'entries' => [
                    (object)['totalPay' => 1000, 'quickbooksId' => '123']
                ]
            ],
        ];

        foreach ($transactionTypes as $type => $data) {
            $result = $this->service->validateJournalData($type, $data);
            $this->assertTrue($result, "Validation failed for transaction type: {$type}");
        }
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    private function createSampleEmployeeData(string $employeeId = '123', float $grossPay = 2000): array
    {
        $netPay = $grossPay * 0.75; // Simplified calculation

        return [
            'quickbooksEmployeeId' => $employeeId,
            'grossSalary' => [
                (object)[
                    'amount' => $grossPay,
                    'class' => PayrollConfig::getClass('ADMIN_CLASS'),
                    'account' => PayrollConfig::getCharityAccount('STAFF_SALARIES_ACCOUNT')
                ]
            ],
            'netSalary' => -$netPay,
            'paye' => -$grossPay * 0.15,
            'employeeNI' => -$grossPay * 0.10,
            'salarySacrifice' => 0,
            'employeePensionContribution' => 0,
            'studentLoan' => 0,
            'otherDeduction' => 0,
        ];
    }
}
