<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Services\JournalLineFactory;
use Services\EmployeeJournalLineBuilder;
use Services\EmployerNILineBuilder;
use Services\ShopPayrollLineBuilder;
use Config\PayrollConfig;
use Errors\ValidationException;

/**
 * Unit tests for JournalLineFactory
 *
 * Tests:
 * - Builder registration and retrieval
 * - Line building for each transaction type
 * - Balance validation
 * - Error handling
 */
class JournalLineFactoryTest extends TestCase
{
    private JournalLineFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new JournalLineFactory();
    }

    // ==========================================
    // BUILDER REGISTRATION TESTS
    // ==========================================

    public function testFactoryRegistersDefaultBuilders(): void
    {
        $types = $this->factory->getRegisteredTransactionTypes();

        $this->assertContains('employee', $types);
        $this->assertContains('employer_ni', $types);
        $this->assertContains('pensions', $types);
        $this->assertContains('shop_payroll', $types);
    }

    public function testGetBuilderReturnsCorrectBuilder(): void
    {
        $builder = $this->factory->getBuilder('employee');

        $this->assertInstanceOf(EmployeeJournalLineBuilder::class, $builder);
    }

    public function testGetBuilderThrowsForInvalidType(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid transaction type');

        $this->factory->getBuilder('invalid_type');
    }

    public function testHasBuilderReturnsTrueForRegistered(): void
    {
        $this->assertTrue($this->factory->hasBuilder('employee'));
        $this->assertTrue($this->factory->hasBuilder('employer_ni'));
    }

    public function testHasBuilderReturnsFalseForUnregistered(): void
    {
        $this->assertFalse($this->factory->hasBuilder('nonexistent'));
    }

    // ==========================================
    // EMPLOYEE JOURNAL TESTS
    // ==========================================

    public function testBuildLinesForEmployeeJournal(): void
    {
        $data = $this->createEmployeeJournalData();

        $lines = $this->factory->buildLines('employee', $data);

        // Should have 8 lines: gross salary, PAYE, employee NI, salary sacrifice,
        // employee pension, other deductions, student loan, net pay
        $this->assertIsArray($lines);
        $this->assertGreaterThan(0, count($lines));

        // Check structure of first line
        $firstLine = $lines[0];
        $this->assertArrayHasKey('Description', $firstLine);
        $this->assertArrayHasKey('Amount', $firstLine);
        $this->assertArrayHasKey('DetailType', $firstLine);
        $this->assertArrayHasKey('JournalEntryLineDetail', $firstLine);
    }

    public function testEmployeeJournalSkipsZeroAmounts(): void
    {
        $data = [
            'quickbooksEmployeeId' => '123',
            'grossSalary' => [(object)[
                'amount' => 1000,
                'class' => '100',
                'account' => '261'
            ]],
            'netSalary' => 800,
            'paye' => 0, // Zero amount - should be skipped
            'employeeNI' => 0, // Zero amount - should be skipped
            'salarySacrifice' => 0,
            'employeePensionContribution' => 0,
            'studentLoan' => 0,
            'otherDeduction' => 0,
        ];

        $lines = $this->factory->buildLines('employee', $data);

        // Should only have gross salary and net pay (non-zero amounts)
        $this->assertLessThanOrEqual(2, count($lines));
    }

    public function testEmployeeJournalValidatesRequiredFields(): void
    {
        $this->expectException(ValidationException::class);

        $data = [
            // Missing quickbooksEmployeeId
            'grossSalary' => [],
        ];

        $this->factory->buildLines('employee', $data);
    }

    // ==========================================
    // EMPLOYER NI TESTS
    // ==========================================

    public function testBuildLinesForEmployerNI(): void
    {
        $data = [
            'entries' => [
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
            ]
        ];

        $lines = $this->factory->buildLines('employer_ni', $data);

        // Should have 3 lines: 2 debits + 1 credit total
        $this->assertCount(3, $lines);

        // Last line should be credit (negative amount)
        $lastLine = end($lines);
        $this->assertEquals('Credit', $lastLine['JournalEntryLineDetail']['PostingType']);
    }

    // ==========================================
    // SHOP PAYROLL TESTS
    // ==========================================

    public function testBuildLinesForShopPayroll(): void
    {
        $data = [
            'entries' => [
                (object)[
                    'totalPay' => 1000,
                    'quickbooksId' => '123',
                    'employerNI' => 100,
                    'employerPension' => 50,
                ],
            ]
        ];

        $lines = $this->factory->buildLines('shop_payroll', $data);

        // Should have 6 lines:
        // - 2 for gross salary (debit + credit)
        // - 2 for employer NI (debit + credit)
        // - 2 for employer pension (debit + credit)
        $this->assertCount(6, $lines);
    }

    // ==========================================
    // BALANCE VALIDATION TESTS
    // ==========================================

    public function testValidateBalancePassesForBalancedJournal(): void
    {
        $lines = [
            [
                'Amount' => 100,
                'JournalEntryLineDetail' => ['PostingType' => 'Debit']
            ],
            [
                'Amount' => 100,
                'JournalEntryLineDetail' => ['PostingType' => 'Credit']
            ],
        ];

        $result = $this->factory->validateBalance($lines);

        $this->assertTrue($result);
    }

    public function testValidateBalanceThrowsForUnbalancedJournal(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Journal entry is not balanced');

        $lines = [
            [
                'Amount' => 100,
                'JournalEntryLineDetail' => ['PostingType' => 'Debit']
            ],
            [
                'Amount' => 50,
                'JournalEntryLineDetail' => ['PostingType' => 'Credit']
            ],
        ];

        $this->factory->validateBalance($lines);
    }

    // ==========================================
    // COMPLETE JOURNAL ENTRY TESTS
    // ==========================================

    public function testBuildJournalEntryCreatesCompleteStructure(): void
    {
        $data = $this->createEmployeeJournalData();

        $journal = $this->factory->buildJournalEntry(
            'employee',
            $data,
            '2024-01-31',
            'Payroll_2024_01'
        );

        $this->assertArrayHasKey('TxnDate', $journal);
        $this->assertArrayHasKey('DocNumber', $journal);
        $this->assertArrayHasKey('Line', $journal);
        $this->assertArrayHasKey('TotalAmt', $journal);

        $this->assertEquals('2024-01-31', $journal['TxnDate']);
        $this->assertEquals('Payroll_2024_01', $journal['DocNumber']);
        $this->assertIsArray($journal['Line']);
    }

    public function testBuildJournalEntryWithValidationDisabled(): void
    {
        $data = [
            'quickbooksEmployeeId' => '123',
            'grossSalary' => [], // Invalid - empty
        ];

        // Should not throw when validation disabled
        $journal = $this->factory->buildJournalEntry(
            'employee',
            $data,
            '2024-01-31',
            'Payroll_2024_01',
            false // validation disabled
        );

        $this->assertIsArray($journal);
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    private function createEmployeeJournalData(): array
    {
        return [
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
    }
}
