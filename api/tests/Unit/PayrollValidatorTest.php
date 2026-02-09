<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Validators\PayrollValidator;
use Errors\ValidationException;
use Config\PayrollConfig;

/**
 * Unit tests for PayrollValidator
 *
 * Tests:
 * - Date validation
 * - Date range validation
 * - Amount validation
 * - Journal entry validation
 * - Required field validation
 * - Allocation validation
 */
class PayrollValidatorTest extends TestCase
{
    private PayrollValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new PayrollValidator();
    }

    // ==========================================
    // DATE VALIDATION TESTS
    // ==========================================

    public function testValidateDateAcceptsValidDate(): void
    {
        // Should not throw
        $this->validator->validateDate('2024-01-31', 'testDate');

        $this->assertTrue(true); // If we get here, validation passed
    }

    public function testValidateDateRejectsInvalidFormat(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid date format');

        $this->validator->validateDate('31-01-2024', 'testDate');
    }

    public function testValidateDateRejectsInvalidDate(): void
    {
        $this->expectException(ValidationException::class);

        $this->validator->validateDate('2024-02-31', 'testDate'); // Feb 31 doesn't exist
    }

    public function testValidateDateRangeAcceptsValidRange(): void
    {
        // Should not throw
        $this->validator->validateDateRange('2024-01-01', '2024-01-31');

        $this->assertTrue(true);
    }

    public function testValidateDateRangeRejectsInvalidRange(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid date range');

        $this->validator->validateDateRange('2024-01-31', '2024-01-01');
    }

    // ==========================================
    // AMOUNT VALIDATION TESTS
    // ==========================================

    public function testValidateAmountAcceptsValidNumber(): void
    {
        // Should not throw
        $this->validator->validateAmount(100.50, 'amount');
        $this->validator->validateAmount(-50.25, 'amount');
        $this->validator->validateAmount(0, 'amount');

        $this->assertTrue(true);
    }

    public function testValidateAmountRejectsNonNumeric(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid amount');

        $this->validator->validateAmount('not a number', 'amount');
    }

    public function testValidateAmountRejectsNegativeWhenNotAllowed(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('cannot be negative');

        $this->validator->validateAmount(-100, 'amount', false);
    }

    public function testValidateAmountRejectsInfinity(): void
    {
        $this->expectException(ValidationException::class);

        $this->validator->validateAmount(INF, 'amount');
    }

    // ==========================================
    // REQUIRED FIELD TESTS
    // ==========================================

    public function testValidateRequiredFieldAcceptsExistingField(): void
    {
        $data = ['field' => 'value'];

        // Should not throw
        $this->validator->validateRequiredField($data, 'field');

        $this->assertTrue(true);
    }

    public function testValidateRequiredFieldRejectsMissingField(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Required field');

        $data = ['other' => 'value'];

        $this->validator->validateRequiredField($data, 'field');
    }

    // ==========================================
    // JOURNAL ENTRY VALIDATION TESTS
    // ==========================================

    public function testValidateJournalEntryAcceptsValidJournal(): void
    {
        $journal = [
            'TxnDate' => '2024-01-31',
            'DocNumber' => 'Payroll_2024_01',
            'Line' => [
                [
                    'Description' => 'Test',
                    'Amount' => 100,
                    'DetailType' => 'JournalEntryLineDetail',
                    'JournalEntryLineDetail' => [
                        'PostingType' => 'Debit',
                        'AccountRef' => '100',
                        'ClassRef' => '200',
                    ]
                ],
                [
                    'Description' => 'Test',
                    'Amount' => 100,
                    'DetailType' => 'JournalEntryLineDetail',
                    'JournalEntryLineDetail' => [
                        'PostingType' => 'Credit',
                        'AccountRef' => '101',
                        'ClassRef' => '200',
                    ]
                ],
            ]
        ];

        // Should not throw
        $this->validator->validateJournalEntry($journal);

        $this->assertTrue(true);
    }

    public function testValidateJournalEntryRejectsMissingDate(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('TxnDate');

        $journal = [
            'DocNumber' => 'Test',
            'Line' => []
        ];

        $this->validator->validateJournalEntry($journal);
    }

    public function testValidateJournalEntryRejectsEmptyLines(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('at least one line');

        $journal = [
            'TxnDate' => '2024-01-31',
            'DocNumber' => 'Test',
            'Line' => []
        ];

        $this->validator->validateJournalEntry($journal);
    }

    public function testValidateJournalEntryRejectsUnbalancedJournal(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('not balanced');

        $journal = [
            'TxnDate' => '2024-01-31',
            'DocNumber' => 'Test',
            'Line' => [
                [
                    'Description' => 'Test',
                    'Amount' => 100,
                    'DetailType' => 'JournalEntryLineDetail',
                    'JournalEntryLineDetail' => [
                        'PostingType' => 'Debit',
                        'AccountRef' => '100',
                    ]
                ],
                [
                    'Description' => 'Test',
                    'Amount' => 50, // Unbalanced
                    'DetailType' => 'JournalEntryLineDetail',
                    'JournalEntryLineDetail' => [
                        'PostingType' => 'Credit',
                        'AccountRef' => '101',
                    ]
                ],
            ]
        ];

        $this->validator->validateJournalEntry($journal);
    }

    public function testValidateJournalEntryRejectsTooManyLines(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('maximum line count');

        // Create journal with too many lines
        $lines = [];
        for ($i = 0; $i < PayrollConfig::MAX_JOURNAL_LINES + 10; $i++) {
            $lines[] = [
                'Description' => 'Test',
                'Amount' => 1,
                'DetailType' => 'JournalEntryLineDetail',
                'JournalEntryLineDetail' => [
                    'PostingType' => $i % 2 === 0 ? 'Debit' : 'Credit',
                    'AccountRef' => '100',
                ]
            ];
        }

        $journal = [
            'TxnDate' => '2024-01-31',
            'DocNumber' => 'Test',
            'Line' => $lines
        ];

        $this->validator->validateJournalEntry($journal);
    }

    // ==========================================
    // JOURNAL LINE VALIDATION TESTS
    // ==========================================

    public function testValidateJournalLineRejectsMissingAmount(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Missing \'Amount\'');

        $line = [
            'Description' => 'Test',
            'DetailType' => 'JournalEntryLineDetail',
            'JournalEntryLineDetail' => [
                'PostingType' => 'Debit',
                'AccountRef' => '100',
            ]
        ];

        $this->validator->validateJournalLine($line, 0);
    }

    public function testValidateJournalLineRejectsInvalidPostingType(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid \'PostingType\'');

        $line = [
            'Description' => 'Test',
            'Amount' => 100,
            'DetailType' => 'JournalEntryLineDetail',
            'JournalEntryLineDetail' => [
                'PostingType' => 'Invalid',
                'AccountRef' => '100',
            ]
        ];

        $this->validator->validateJournalLine($line, 0);
    }

    // ==========================================
    // ALLOCATION VALIDATION TESTS
    // ==========================================

    public function testValidateAllocationsAcceptsValid100Percent(): void
    {
        $allocations = [
            ['percentage' => 50.0],
            ['percentage' => 30.0],
            ['percentage' => 20.0],
        ];

        // Should not throw
        $this->validator->validateAllocations($allocations);

        $this->assertTrue(true);
    }

    public function testValidateAllocationsRejectsInvalidSum(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('must sum to 100%');

        $allocations = [
            ['percentage' => 50.0],
            ['percentage' => 30.0],
            ['percentage' => 10.0], // Only 90%
        ];

        $this->validator->validateAllocations($allocations);
    }

    public function testValidateAllocationsRejectsNegativePercentage(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('between 0 and 100');

        $allocations = [
            ['percentage' => 150.0],
            ['percentage' => -50.0], // Negative
        ];

        $this->validator->validateAllocations($allocations);
    }

    public function testValidateAllocationsRejectsEmptyArray(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('cannot be empty');

        $this->validator->validateAllocations([]);
    }

    // ==========================================
    // TRANSACTION TYPE VALIDATION TESTS
    // ==========================================

    public function testValidateTransactionTypeAcceptsValid(): void
    {
        // Should not throw
        $this->validator->validateTransactionType('employee');
        $this->validator->validateTransactionType('employer_ni');
        $this->validator->validateTransactionType('pensions');
        $this->validator->validateTransactionType('shop_payroll');

        $this->assertTrue(true);
    }

    public function testValidateTransactionTypeRejectsInvalid(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid transaction type');

        $this->validator->validateTransactionType('invalid');
    }

    // ==========================================
    // EMPLOYEE ID VALIDATION TESTS
    // ==========================================

    public function testValidateEmployeeIdAcceptsNumeric(): void
    {
        // Should not throw
        $this->validator->validateEmployeeId('123');
        $this->validator->validateEmployeeId(456);

        $this->assertTrue(true);
    }

    public function testValidateEmployeeIdRejectsEmpty(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('cannot be empty');

        $this->validator->validateEmployeeId('');
    }

    public function testValidateEmployeeIdRejectsNonNumeric(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('must be numeric');

        $this->validator->validateEmployeeId('abc');
    }

    // ==========================================
    // REALM ID VALIDATION TESTS
    // ==========================================

    public function testValidateRealmIdAcceptsCharityRealm(): void
    {
        // Should not throw
        $this->validator->validateRealmId(PayrollConfig::CHARITY_REALM_ID);

        $this->assertTrue(true);
    }

    public function testValidateRealmIdAcceptsEnterprisesRealm(): void
    {
        // Should not throw
        $this->validator->validateRealmId(PayrollConfig::ENTERPRISES_REALM_ID);

        $this->assertTrue(true);
    }

    public function testValidateRealmIdRejectsInvalid(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid realm ID');

        $this->validator->validateRealmId('invalid-realm-id');
    }
}
