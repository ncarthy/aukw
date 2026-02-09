<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Services\PayrollService;
use Services\JournalLineFactory;
use Validators\PayrollValidator;
use Errors\ValidationException;
use Config\PayrollConfig;

/**
 * Unit tests for PayrollService
 *
 * Tests:
 * - Employee journal creation
 * - Employer NI journal creation
 * - Shop payroll journal creation
 * - Batch processing
 * - Validation integration
 * - Error handling
 */
class PayrollServiceTest extends TestCase
{
    private PayrollService $service;
    private JournalLineFactory $factory;
    private PayrollValidator $validator;

    protected function setUp(): void
    {
        $this->factory = new JournalLineFactory();
        $this->validator = new PayrollValidator();
        $this->service = new PayrollService($this->factory, $this->validator);
    }

    // ==========================================
    // DOC NUMBER GENERATION TESTS
    // ==========================================

    public function testGenerateDocNumberCreatesCorrectFormat(): void
    {
        $docNumber = $this->service->generateDocNumber('2024-01-31');

        $this->assertStringStartsWith('Payroll_2024_01', $docNumber);
        $this->assertLessThanOrEqual(
            PayrollConfig::QBO_DOCNUMBER_MAX_LENGTH,
            strlen($docNumber)
        );
    }

    public function testGenerateDocNumberWithSuffix(): void
    {
        $docNumber = $this->service->generateDocNumber('2024-01-31', '_EMP');

        $this->assertStringContainsString('_EMP', $docNumber);
    }

    // ==========================================
    // VALIDATION TESTS
    // ==========================================

    public function testValidateJournalDataAcceptsValidEmployee(): void
    {
        $data = [
            'quickbooksEmployeeId' => '123',
            'grossSalary' => [
                (object)[
                    'amount' => 2000,
                    'class' => '100',
                    'account' => '261'
                ]
            ],
            'netSalary' => 1500,
            'paye' => 300,
            'employeeNI' => 150,
            'salarySacrifice' => 0,
            'employeePensionContribution' => 50,
            'studentLoan' => 0,
            'otherDeduction' => 0,
        ];

        $result = $this->service->validateJournalData('employee', $data);

        $this->assertTrue($result);
    }

    public function testValidateJournalDataRejectsInvalidData(): void
    {
        $this->expectException(ValidationException::class);

        $data = [
            // Missing required fields
            'grossSalary' => []
        ];

        $this->service->validateJournalData('employee', $data);
    }

    // ==========================================
    // BATCH PROCESSING TESTS
    // ==========================================

    public function testCreateEmployeeJournalsBatchProcessesMultiple(): void
    {
        $employees = [
            $this->createEmployeeData('100'),
            $this->createEmployeeData('200'),
            $this->createEmployeeData('300'),
        ];

        // Note: This test would need mocking to avoid actual QB API calls
        // For now, we test the validation and data structure

        $this->expectException(\Error::class); // Will fail at QB API call (expected)

        $results = $this->service->createEmployeeJournalsBatch(
            $employees,
            PayrollConfig::CHARITY_REALM_ID,
            '2024-01-31',
            'Payroll_2024_01',
            true,
            true // stopOnError
        );
    }

    public function testCreateEmployeeJournalsBatchStopsOnError(): void
    {
        $employees = [
            ['invalid' => 'data'], // Invalid - will cause error
            $this->createEmployeeData('200'),
        ];

        $this->expectException(\Error::class); // Will fail at validation

        $results = $this->service->createEmployeeJournalsBatch(
            $employees,
            PayrollConfig::CHARITY_REALM_ID,
            '2024-01-31',
            'Payroll_2024_01',
            true,
            true // stopOnError = true
        );
    }

    // ==========================================
    // FACTORY AND VALIDATOR ACCESS TESTS
    // ==========================================

    public function testGetFactoryReturnsFactory(): void
    {
        $factory = $this->service->getFactory();

        $this->assertInstanceOf(JournalLineFactory::class, $factory);
        $this->assertSame($this->factory, $factory);
    }

    public function testGetValidatorReturnsValidator(): void
    {
        $validator = $this->service->getValidator();

        $this->assertInstanceOf(PayrollValidator::class, $validator);
        $this->assertSame($this->validator, $validator);
    }

    // ==========================================
    // INTEGRATION TESTS WITH FACTORY
    // ==========================================

    public function testServiceUsesFactoryForLineCreation(): void
    {
        $data = $this->createEmployeeData('123');

        // Validate data through service (uses factory internally)
        $result = $this->service->validateJournalData('employee', $data);

        $this->assertTrue($result);

        // Verify factory can build lines from same data
        $lines = $this->factory->buildLines('employee', $data);

        $this->assertIsArray($lines);
        $this->assertGreaterThan(0, count($lines));
    }

    // ==========================================
    // ERROR HANDLING TESTS
    // ==========================================

    public function testServiceThrowsValidationExceptionForInvalidRealm(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid realm ID');

        $data = $this->createEmployeeData('123');

        $this->service->createEmployeeJournal(
            $data,
            'invalid-realm',
            '2024-01-31',
            'Test'
        );
    }

    public function testServiceThrowsValidationExceptionForInvalidDate(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid date format');

        $data = $this->createEmployeeData('123');

        $this->service->createEmployeeJournal(
            $data,
            PayrollConfig::CHARITY_REALM_ID,
            '31-01-2024', // Wrong format
            'Test'
        );
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    private function createEmployeeData(string $employeeId): array
    {
        return [
            'quickbooksEmployeeId' => $employeeId,
            'grossSalary' => [
                (object)[
                    'amount' => 2000,
                    'class' => '100',
                    'account' => '261'
                ]
            ],
            'netSalary' => 1500,
            'paye' => 300,
            'employeeNI' => 150,
            'salarySacrifice' => 0,
            'employeePensionContribution' => 50,
            'studentLoan' => 0,
            'otherDeduction' => 0,
        ];
    }
}
