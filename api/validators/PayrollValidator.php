<?php

namespace Validators;

use Config\PayrollConfig;
use Errors\ValidationException;
use DateTime;

/**
 * Validator for payroll-related data
 *
 * Provides validation methods for:
 * - Journal entries
 * - Date ranges
 * - Amounts
 * - Transaction types
 * - Employee data
 *
 * @category Validator
 */
class PayrollValidator
{
    /**
     * Validate a complete journal entry
     *
     * Checks:
     * - Required fields are present
     * - Debits equal credits (within tolerance)
     * - All amounts are valid
     * - Line count is within limits
     *
     * @param array $journalData Journal entry data
     * @throws ValidationException If validation fails
     * @return void
     */
    public function validateJournalEntry(array $journalData): void
    {
        // Validate required fields
        $this->validateRequiredField($journalData, 'TxnDate');
        $this->validateRequiredField($journalData, 'DocNumber');
        $this->validateRequiredField($journalData, 'Line');

        // Validate date format
        $this->validateDate($journalData['TxnDate'], 'TxnDate');

        // Validate DocNumber length
        if (strlen($journalData['DocNumber']) > PayrollConfig::QBO_DOCNUMBER_MAX_LENGTH) {
            throw ValidationException::invalidField('DocNumber',
                "DocNumber exceeds maximum length of " . PayrollConfig::QBO_DOCNUMBER_MAX_LENGTH);
        }

        // Validate lines array
        if (!is_array($journalData['Line'])) {
            throw new ValidationException(
                "Journal 'Line' must be an array",
                ValidationException::ERROR_MISSING_REQUIRED_FIELD,
                'Line',
                $journalData['Line']
            );
        }

        // Validate line count
        $lineCount = count($journalData['Line']);
        if ($lineCount === 0) {
            throw new ValidationException(
                "Journal must have at least one line",
                ValidationException::ERROR_MISSING_REQUIRED_FIELD,
                'Line',
                $lineCount
            );
        }

        if ($lineCount > PayrollConfig::MAX_JOURNAL_LINES) {
            throw new ValidationException(
                "Journal exceeds maximum line count of " . PayrollConfig::MAX_JOURNAL_LINES,
                ValidationException::ERROR_MISSING_REQUIRED_FIELD,
                'Line',
                $lineCount,
                ['maxLines' => PayrollConfig::MAX_JOURNAL_LINES, 'actualLines' => $lineCount]
            );
        }

        // Validate each line and calculate balance
        $debits = 0;
        $credits = 0;

        foreach ($journalData['Line'] as $index => $line) {
            $this->validateJournalLine($line, $index);

            $amount = $line['Amount'];
            $postingType = $line['JournalEntryLineDetail']['PostingType'];

            if ($postingType === 'Debit') {
                $debits += $amount;
            } elseif ($postingType === 'Credit') {
                $credits += $amount;
            }
        }

        // Validate balance
        $balance = $debits - $credits;
        if (!PayrollConfig::isBalanced($balance)) {
            throw ValidationException::unbalancedJournal(
                $balance,
                [
                    'debits' => $debits,
                    'credits' => $credits,
                    'lineCount' => $lineCount
                ]
            );
        }
    }

    /**
     * Validate a single journal line
     *
     * @param array $line Line data
     * @param int $index Line index for error reporting
     * @throws ValidationException If validation fails
     * @return void
     */
    public function validateJournalLine(array $line, int $index): void
    {
        $lineContext = ['lineIndex' => $index];

        // Validate required fields
        if (!isset($line['Amount'])) {
            throw new ValidationException(
                "Line {$index}: Missing 'Amount'",
                ValidationException::ERROR_MISSING_REQUIRED_FIELD,
                "Line[{$index}].Amount",
                null,
                $lineContext
            );
        }

        if (!isset($line['DetailType'])) {
            throw new ValidationException(
                "Line {$index}: Missing 'DetailType'",
                ValidationException::ERROR_MISSING_REQUIRED_FIELD,
                "Line[{$index}].DetailType",
                null,
                $lineContext
            );
        }

        if (!isset($line['JournalEntryLineDetail'])) {
            throw new ValidationException(
                "Line {$index}: Missing 'JournalEntryLineDetail'",
                ValidationException::ERROR_MISSING_REQUIRED_FIELD,
                "Line[{$index}].JournalEntryLineDetail",
                null,
                $lineContext
            );
        }

        $detail = $line['JournalEntryLineDetail'];

        // Validate posting type
        if (!isset($detail['PostingType'])) {
            throw new ValidationException(
                "Line {$index}: Missing 'PostingType'",
                ValidationException::ERROR_MISSING_REQUIRED_FIELD,
                "Line[{$index}].JournalEntryLineDetail.PostingType",
                null,
                $lineContext
            );
        }

        if (!in_array($detail['PostingType'], ['Debit', 'Credit'], true)) {
            throw new ValidationException(
                "Line {$index}: Invalid 'PostingType'. Must be 'Debit' or 'Credit'",
                ValidationException::ERROR_MISSING_REQUIRED_FIELD,
                "Line[{$index}].JournalEntryLineDetail.PostingType",
                $detail['PostingType'],
                $lineContext
            );
        }

        // Validate amount
        $this->validateAmount($line['Amount'], "Line[{$index}].Amount", false);

        // Validate account reference
        if (!isset($detail['AccountRef'])) {
            throw new ValidationException(
                "Line {$index}: Missing 'AccountRef'",
                ValidationException::ERROR_INVALID_ACCOUNT_ID,
                "Line[{$index}].JournalEntryLineDetail.AccountRef",
                null,
                $lineContext
            );
        }
    }

    /**
     * Validate date format and value
     *
     * @param string $date Date string to validate
     * @param string $fieldName Field name for error reporting
     * @param string $format Expected date format (default: Y-m-d)
     * @throws ValidationException If date is invalid
     * @return void
     */
    public function validateDate(string $date, string $fieldName, string $format = PayrollConfig::DATE_FORMAT_API): void
    {
        $d = DateTime::createFromFormat($format, $date);

        if ($d === false || $d->format($format) !== $date) {
            throw ValidationException::invalidDate($fieldName, $date, $format);
        }
    }

    /**
     * Validate date range (start date before end date)
     *
     * @param string $startDate Start date
     * @param string $endDate End date
     * @param string $format Date format (default: Y-m-d)
     * @throws ValidationException If date range is invalid
     * @return void
     */
    public function validateDateRange(string $startDate, string $endDate, string $format = PayrollConfig::DATE_FORMAT_API): void
    {
        // Validate both dates
        $this->validateDate($startDate, 'startDate', $format);
        $this->validateDate($endDate, 'endDate', $format);

        // Check that start is before end
        $start = DateTime::createFromFormat($format, $startDate);
        $end = DateTime::createFromFormat($format, $endDate);

        if ($start > $end) {
            throw ValidationException::invalidDateRange($startDate, $endDate);
        }
    }

    /**
     * Validate amount
     *
     * @param mixed $amount Amount to validate
     * @param string $fieldName Field name for error reporting
     * @param bool $allowNegative Whether negative amounts are allowed
     * @throws ValidationException If amount is invalid
     * @return void
     */
    public function validateAmount($amount, string $fieldName, bool $allowNegative = true): void
    {
        // Must be numeric
        if (!is_numeric($amount)) {
            throw ValidationException::invalidAmount($fieldName, $amount);
        }

        $numericAmount = (float) $amount;

        // Check for negative
        if (!$allowNegative && $numericAmount < 0) {
            throw ValidationException::negativeAmount($fieldName, $numericAmount);
        }

        // Check for NaN or Infinity
        if (!is_finite($numericAmount)) {
            throw ValidationException::invalidAmount($fieldName, $amount);
        }
    }

    /**
     * Validate that a required field exists
     *
     * @param array $data Data array
     * @param string $fieldName Field name
     * @throws ValidationException If field is missing
     * @return void
     */
    public function validateRequiredField(array $data, string $fieldName): void
    {
        if (!isset($data[$fieldName])) {
            throw ValidationException::missingRequiredField($fieldName);
        }
    }

    /**
     * Validate transaction type
     *
     * @param string $transactionType Transaction type to validate
     * @throws ValidationException If transaction type is invalid
     * @return void
     */
    public function validateTransactionType(string $transactionType): void
    {
        if (!PayrollConfig::isValidTransactionType($transactionType)) {
            throw ValidationException::invalidTransactionType(
                $transactionType,
                PayrollConfig::TRANSACTION_TYPES
            );
        }
    }

    /**
     * Validate employee ID
     *
     * @param mixed $employeeId Employee ID to validate
     * @param string $fieldName Field name for error reporting
     * @throws ValidationException If employee ID is invalid
     * @return void
     */
    public function validateEmployeeId($employeeId, string $fieldName = 'employeeId'): void
    {
        if (empty($employeeId)) {
            throw new ValidationException(
                "Employee ID cannot be empty",
                ValidationException::ERROR_INVALID_EMPLOYEE_ID,
                $fieldName,
                $employeeId
            );
        }

        // Must be numeric or numeric string
        if (!is_numeric($employeeId)) {
            throw new ValidationException(
                "Employee ID must be numeric",
                ValidationException::ERROR_INVALID_EMPLOYEE_ID,
                $fieldName,
                $employeeId
            );
        }
    }

    /**
     * Validate allocation percentages sum to 100%
     *
     * @param array $allocations Array of allocation objects with 'percentage' field
     * @throws ValidationException If percentages don't sum to 100%
     * @return void
     */
    public function validateAllocations(array $allocations): void
    {
        if (empty($allocations)) {
            throw new ValidationException(
                "Allocations cannot be empty",
                ValidationException::ERROR_MISSING_REQUIRED_FIELD,
                'allocations',
                $allocations
            );
        }

        $sum = 0;
        foreach ($allocations as $index => $allocation) {
            if (!isset($allocation['percentage'])) {
                throw new ValidationException(
                    "Allocation {$index}: Missing 'percentage'",
                    ValidationException::ERROR_MISSING_REQUIRED_FIELD,
                    "allocations[{$index}].percentage",
                    null
                );
            }

            $percentage = (float) $allocation['percentage'];
            $this->validateAmount($percentage, "allocations[{$index}].percentage", false);

            if ($percentage < 0 || $percentage > 100) {
                throw new ValidationException(
                    "Allocation {$index}: Percentage must be between 0 and 100",
                    ValidationException::ERROR_PERCENTAGE_SUM,
                    "allocations[{$index}].percentage",
                    $percentage
                );
            }

            $sum += $percentage;
        }

        // Check sum is 100% (within tolerance)
        if (abs($sum - 100) > PayrollConfig::AMOUNT_ZERO_THRESHOLD) {
            throw ValidationException::invalidPercentageSum($sum);
        }
    }

    /**
     * Validate realm ID
     *
     * @param string $realmId Realm ID to validate
     * @throws ValidationException If realm ID is invalid
     * @return void
     */
    public function validateRealmId(string $realmId): void
    {
        if (empty($realmId)) {
            throw new ValidationException(
                "Realm ID cannot be empty",
                ValidationException::ERROR_MISSING_REQUIRED_FIELD,
                'realmId',
                $realmId
            );
        }

        // Validate it's one of the known realm IDs
        $validRealmIds = [
            PayrollConfig::CHARITY_REALM_ID,
            PayrollConfig::ENTERPRISES_REALM_ID
        ];

        if (!in_array($realmId, $validRealmIds, true)) {
            throw new ValidationException(
                "Invalid realm ID: {$realmId}",
                ValidationException::ERROR_MISSING_REQUIRED_FIELD,
                'realmId',
                $realmId,
                ['validRealmIds' => $validRealmIds]
            );
        }
    }
}
