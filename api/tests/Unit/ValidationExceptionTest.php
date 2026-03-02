<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Errors\ValidationException;
use Errors\PayrollException;

/**
 * Unit tests for ValidationException and its factory methods
 *
 * Tests:
 * - Constructor: field, value, error code, context propagation
 * - getField / getValue getters
 * - All 9 static factory methods
 * - PayrollException base: getErrorCode, getContext, getTimestamp, toArray, toJson
 */
class ValidationExceptionTest extends TestCase
{
    // ==========================================
    // Constructor and getters
    // ==========================================

    public function testConstructorStoresMessageErrorCodeFieldAndValue(): void
    {
        $ex = new ValidationException(
            'Test message',
            ValidationException::ERROR_INVALID_AMOUNT,
            'totalPay',
            -50.0
        );

        $this->assertSame('Test message',                            $ex->getMessage());
        $this->assertSame(ValidationException::ERROR_INVALID_AMOUNT, $ex->getErrorCode());
        $this->assertSame('totalPay',                                $ex->getField());
        $this->assertSame(-50.0,                                     $ex->getValue());
    }

    public function testGetFieldReturnsNullWhenNotProvided(): void
    {
        $ex = new ValidationException('msg');

        $this->assertNull($ex->getField());
    }

    public function testGetValueReturnsNullWhenNotProvided(): void
    {
        $ex = new ValidationException('msg');

        $this->assertNull($ex->getValue());
    }

    public function testFieldAndValueAreInjectedIntoContext(): void
    {
        $ex = new ValidationException('msg', 'CODE', 'myField', 'myValue');

        $context = $ex->getContext();

        $this->assertSame('myField', $context['field']);
        $this->assertSame('myValue', $context['value']);
    }

    public function testContextOmitsFieldAndValueKeysWhenBothNull(): void
    {
        $ex = new ValidationException('msg', 'CODE', null, null, ['extra' => 'data']);

        $context = $ex->getContext();

        $this->assertArrayNotHasKey('field', $context);
        $this->assertArrayNotHasKey('value', $context);
        $this->assertSame('data', $context['extra']);
    }

    public function testIsInstanceOfPayrollException(): void
    {
        $ex = new ValidationException('msg');

        $this->assertInstanceOf(PayrollException::class, $ex);
    }

    // ==========================================
    // invalidDate
    // ==========================================

    public function testInvalidDateFactoryReturnsCorrectExceptionWithDefaultFormat(): void
    {
        $ex = ValidationException::invalidDate('startDate', '31-01-2024');

        $this->assertInstanceOf(ValidationException::class, $ex);
        $this->assertSame(ValidationException::ERROR_INVALID_DATE, $ex->getErrorCode());
        $this->assertSame('startDate',    $ex->getField());
        $this->assertSame('31-01-2024',   $ex->getValue());
        $this->assertStringContainsString('startDate', $ex->getMessage());
        $this->assertStringContainsString('Y-m-d',     $ex->getMessage());
    }

    public function testInvalidDateFactoryUsesCustomFormatInMessageAndContext(): void
    {
        $ex = ValidationException::invalidDate('myDate', 'bad', 'd/m/Y');

        $this->assertStringContainsString('d/m/Y', $ex->getMessage());
        $this->assertSame('d/m/Y', $ex->getContext()['expectedFormat']);
    }

    // ==========================================
    // invalidAmount
    // ==========================================

    public function testInvalidAmountFactoryReturnsCorrectException(): void
    {
        $ex = ValidationException::invalidAmount('salary', 'abc');

        $this->assertSame(ValidationException::ERROR_INVALID_AMOUNT, $ex->getErrorCode());
        $this->assertSame('salary', $ex->getField());
        $this->assertSame('abc',    $ex->getValue());
        $this->assertStringContainsString('salary', $ex->getMessage());
    }

    // ==========================================
    // negativeAmount
    // ==========================================

    public function testNegativeAmountFactoryReturnsCorrectException(): void
    {
        $ex = ValidationException::negativeAmount('grossPay', -100.0);

        $this->assertSame(ValidationException::ERROR_AMOUNT_NEGATIVE, $ex->getErrorCode());
        $this->assertSame('grossPay', $ex->getField());
        $this->assertStringContainsString('grossPay', $ex->getMessage());
    }

    // ==========================================
    // unbalancedJournal
    // ==========================================

    public function testUnbalancedJournalFactoryIncludesBalanceInContext(): void
    {
        $ex = ValidationException::unbalancedJournal(12.50, ['debits' => 1000.0, 'credits' => 987.50]);

        $this->assertSame(ValidationException::ERROR_UNBALANCED_JOURNAL, $ex->getErrorCode());
        $this->assertStringContainsString('12.50', $ex->getMessage());
        $this->assertSame(12.50,  $ex->getContext()['balance']);
        $this->assertSame(1000.0, $ex->getContext()['debits']);
    }

    public function testUnbalancedJournalFactoryWithNoExtraContext(): void
    {
        $ex = ValidationException::unbalancedJournal(5.0);

        $this->assertSame(5.0, $ex->getContext()['balance']);
    }

    // ==========================================
    // missingRequiredField
    // ==========================================

    public function testMissingRequiredFieldFactoryReturnsCorrectException(): void
    {
        $ex = ValidationException::missingRequiredField('employeeId');

        $this->assertSame(ValidationException::ERROR_MISSING_REQUIRED_FIELD, $ex->getErrorCode());
        $this->assertSame('employeeId', $ex->getField());
        $this->assertStringContainsString('employeeId', $ex->getMessage());
    }

    // ==========================================
    // invalidField
    // ==========================================

    public function testInvalidFieldFactoryIncludesFieldNameAndMessageInExceptionMessage(): void
    {
        $ex = ValidationException::invalidField('realmId', 'must be numeric');

        $this->assertSame(ValidationException::ERROR_INVALID_FIELD, $ex->getErrorCode());
        $this->assertSame('realmId', $ex->getField());
        $this->assertStringContainsString('realmId',       $ex->getMessage());
        $this->assertStringContainsString('must be numeric', $ex->getMessage());
    }

    // ==========================================
    // invalidTransactionType
    // ==========================================

    public function testInvalidTransactionTypeFactoryStoresValueAndValidTypesInContext(): void
    {
        $validTypes = ['EMPLOYEE', 'EMPLOYER_NI', 'SHOP'];
        $ex = ValidationException::invalidTransactionType('UNKNOWN', $validTypes);

        $this->assertSame(ValidationException::ERROR_INVALID_TRANSACTION_TYPE, $ex->getErrorCode());
        $this->assertSame('UNKNOWN',   $ex->getValue());
        $this->assertSame($validTypes, $ex->getContext()['validTypes']);
        $this->assertStringContainsString('UNKNOWN', $ex->getMessage());
    }

    public function testInvalidTransactionTypeFactoryWithEmptyValidTypes(): void
    {
        $ex = ValidationException::invalidTransactionType('BAD');

        $this->assertSame([], $ex->getContext()['validTypes']);
    }

    // ==========================================
    // invalidDateRange
    // ==========================================

    public function testInvalidDateRangeFactoryIncludesBothDatesInMessageAndContext(): void
    {
        $ex = ValidationException::invalidDateRange('2024-12-31', '2024-01-01');

        $this->assertSame(ValidationException::ERROR_DATE_RANGE_INVALID, $ex->getErrorCode());
        $this->assertSame('2024-12-31', $ex->getContext()['startDate']);
        $this->assertSame('2024-01-01', $ex->getContext()['endDate']);
        $this->assertStringContainsString('2024-12-31', $ex->getMessage());
        $this->assertStringContainsString('2024-01-01', $ex->getMessage());
    }

    // ==========================================
    // invalidPercentageSum
    // ==========================================

    public function testInvalidPercentageSumFactoryStoresActualAndExpectedSums(): void
    {
        $ex = ValidationException::invalidPercentageSum(85.0);

        $this->assertSame(ValidationException::ERROR_PERCENTAGE_SUM, $ex->getErrorCode());
        $this->assertSame(85.0,  $ex->getValue());
        $this->assertSame(85.0,  $ex->getContext()['actualSum']);
        $this->assertSame(100.0, $ex->getContext()['expectedSum']);
        $this->assertStringContainsString('85', $ex->getMessage());
    }

    // ==========================================
    // PayrollException base methods (via ValidationException)
    // ==========================================

    public function testGetTimestampReturnsDateTimeFormattedString(): void
    {
        $ex = new ValidationException('msg');

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
            $ex->getTimestamp()
        );
    }

    public function testToArrayContainsRequiredKeys(): void
    {
        $ex = ValidationException::invalidAmount('field', 'bad');

        $array = $ex->toArray();

        $this->assertTrue($array['error']);
        $this->assertSame(ValidationException::ERROR_INVALID_AMOUNT, $array['errorCode']);
        $this->assertArrayHasKey('message',   $array);
        $this->assertArrayHasKey('context',   $array);
        $this->assertArrayHasKey('timestamp', $array);
        $this->assertArrayHasKey('file',      $array);
        $this->assertArrayHasKey('line',      $array);
    }

    public function testToJsonProducesValidJsonWithRequiredFields(): void
    {
        $ex = ValidationException::missingRequiredField('date');

        $decoded = json_decode($ex->toJson(), true);

        $this->assertNotNull($decoded, 'toJson() should produce valid JSON');
        $this->assertTrue($decoded['error']);
        $this->assertSame(ValidationException::ERROR_MISSING_REQUIRED_FIELD, $decoded['errorCode']);
    }
}
