<?php

namespace Errors;

use Throwable;

/**
 * Exception thrown when input validation fails
 *
 * Used for:
 * - Invalid date formats
 * - Unbalanced journal entries
 * - Missing required fields
 * - Invalid amounts
 * - Out of range values
 *
 * @category Error
 */
class ValidationException extends PayrollException
{
    // Error codes for specific validation failures
    public const ERROR_INVALID_DATE = 'VALIDATION_INVALID_DATE';
    public const ERROR_INVALID_AMOUNT = 'VALIDATION_INVALID_AMOUNT';
    public const ERROR_UNBALANCED_JOURNAL = 'VALIDATION_UNBALANCED_JOURNAL';
    public const ERROR_INVALID_FIELD = 'VALIDATION_INVALID_FIELD';
    public const ERROR_MISSING_REQUIRED_FIELD = 'VALIDATION_MISSING_REQUIRED_FIELD';
    public const ERROR_INVALID_TRANSACTION_TYPE = 'VALIDATION_INVALID_TRANSACTION_TYPE';
    public const ERROR_INVALID_EMPLOYEE_ID = 'VALIDATION_INVALID_EMPLOYEE_ID';
    public const ERROR_INVALID_ACCOUNT_ID = 'VALIDATION_INVALID_ACCOUNT_ID';
    public const ERROR_INVALID_CLASS_ID = 'VALIDATION_INVALID_CLASS_ID';
    public const ERROR_DATE_RANGE_INVALID = 'VALIDATION_DATE_RANGE_INVALID';
    public const ERROR_AMOUNT_NEGATIVE = 'VALIDATION_AMOUNT_NEGATIVE';
    public const ERROR_PERCENTAGE_SUM = 'VALIDATION_PERCENTAGE_SUM';

    /**
     * The field that failed validation
     *
     * @var string|null
     */
    protected ?string $field = null;

    /**
     * The value that failed validation
     *
     * @var mixed
     */
    protected mixed $value = null;

    /**
     * Constructor
     *
     * @param string $message Error message
     * @param string $errorCode Error code
     * @param string|null $field The field that failed validation
     * @param mixed $value The value that failed validation
     * @param array $context Additional context data
     * @param int $code Numeric error code (optional)
     * @param Throwable|null $previous Previous exception (optional)
     */
    public function __construct(
        string $message = "",
        string $errorCode = self::ERROR_MISSING_REQUIRED_FIELD,
        ?string $field = null,
        mixed $value = null,
        array $context = [],
        int $code = 0,
        ?Throwable $previous = null
    ) {
        $this->field = $field;
        $this->value = $value;

        // Add field and value to context
        if ($field !== null) {
            $context['field'] = $field;
        }
        if ($value !== null) {
            $context['value'] = $value;
        }

        parent::__construct($message, $errorCode, $context, $code, $previous);
    }

    /**
     * Get the field that failed validation
     *
     * @return string|null
     */
    public function getField(): ?string
    {
        return $this->field;
    }

    /**
     * Get the value that failed validation
     *
     * @return mixed
     */
    public function getValue(): mixed
    {
        return $this->value;
    }

    /**
     * Create exception for invalid date format
     *
     * @param string $field Field name
     * @param mixed $value Invalid date value
     * @param string $expectedFormat Expected date format
     * @return static
     */
    public static function invalidDate(string $field, mixed $value, string $expectedFormat = 'Y-m-d'): static
    {
        return new static(
            "Invalid date format for field '{$field}'. Expected format: {$expectedFormat}",
            self::ERROR_INVALID_DATE,
            $field,
            $value,
            ['expectedFormat' => $expectedFormat]
        );
    }

    /**
     * Create exception for invalid amount
     *
     * @param string $field Field name
     * @param mixed $value Invalid amount value
     * @return static
     */
    public static function invalidAmount(string $field, mixed $value): static
    {
        return new static(
            "Invalid amount for field '{$field}'. Amount must be a valid number",
            self::ERROR_INVALID_AMOUNT,
            $field,
            $value
        );
    }

    /**
     * Create exception for negative amount where positive expected
     *
     * @param string $field Field name
     * @param mixed $value Negative amount value
     * @return static
     */
    public static function negativeAmount(string $field, mixed $value): static
    {
        return new static(
            "Amount for field '{$field}' cannot be negative",
            self::ERROR_AMOUNT_NEGATIVE,
            $field,
            $value
        );
    }

    /**
     * Create exception for unbalanced journal entry
     *
     * @param float $balance The imbalance amount
     * @param array $context Additional context (debits, credits, lines)
     * @return static
     */
    public static function unbalancedJournal(float $balance, array $context = []): static
    {
        $context['balance'] = $balance;

        return new static(
            "Journal entry is not balanced. Debits must equal credits. Imbalance: " . number_format($balance, 2),
            self::ERROR_UNBALANCED_JOURNAL,
            null,
            $balance,
            $context
        );
    }

    /**
     * Create exception for missing required field
     *
     * @param string $field Field name
     * @return static
     */
    public static function missingRequiredField(string $field): static
    {
        return new static(
            "Required field '{$field}' is missing",
            self::ERROR_MISSING_REQUIRED_FIELD,
            $field,
            null
        );
    }

    /**
     * Create exception for invalid field
     *
     * @param string $field Field name
     * @return static
     */
    public static function invalidField(string $field, string $message): static
    {
        return new static(
            "Invalid field '{$field}': {$message}",
            self::ERROR_INVALID_FIELD,
            $field,
            null
        );
    }    

    /**
     * Create exception for invalid transaction type
     *
     * @param mixed $value Invalid transaction type
     * @param array $validTypes List of valid transaction types
     * @return static
     */
    public static function invalidTransactionType(mixed $value, array $validTypes = []): static
    {
        return new static(
            "Invalid transaction type: '{$value}'",
            self::ERROR_INVALID_TRANSACTION_TYPE,
            'transactionType',
            $value,
            ['validTypes' => $validTypes]
        );
    }

    /**
     * Create exception for invalid date range
     *
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return static
     */
    public static function invalidDateRange(string $startDate, string $endDate): static
    {
        return new static(
            "Invalid date range: start date ({$startDate}) must be before end date ({$endDate})",
            self::ERROR_DATE_RANGE_INVALID,
            'dateRange',
            null,
            ['startDate' => $startDate, 'endDate' => $endDate]
        );
    }

    /**
     * Create exception for allocation percentages not summing to 100%
     *
     * @param float $actualSum Actual sum of percentages
     * @return static
     */
    public static function invalidPercentageSum(float $actualSum): static
    {
        return new static(
            "Allocation percentages must sum to 100%. Actual sum: {$actualSum}%",
            self::ERROR_PERCENTAGE_SUM,
            'allocations',
            $actualSum,
            ['expectedSum' => 100.0, 'actualSum' => $actualSum]
        );
    }
}
