<?php

namespace Services;

use Config\PayrollConfig;
use Errors\ValidationException;

/**
 * Builder for employer NI journal lines
 *
 * Creates journal lines for employer NI contributions:
 * - Multiple debit lines (one per employee allocation)
 * - Single credit line (total to TAX_ACCOUNT)
 *
 * @category Service
 */
class EmployerNILineBuilder extends BaseJournalLineBuilder
{
    /**
     * {@inheritdoc}
     */
    public function getTransactionType(): string
    {
        return PayrollConfig::TRANSACTION_TYPES['EMPLOYER_NI'];
    }

    /**
     * {@inheritdoc}
     */
    public function validateData(array $data): void
    {
        // Required field
        if (!isset($data['entries']) || !is_array($data['entries'])) {
            throw ValidationException::missingRequiredField('entries');
        }

        if (empty($data['entries'])) {
            throw new ValidationException(
                "Employer NI journal must have at least one entry",
                ValidationException::ERROR_MISSING_REQUIRED_FIELD,
                'entries',
                $data['entries']
            );
        }

        // Validate each entry
        foreach ($data['entries'] as $index => $entry) {
            if (!isset($entry->amount)) {
                throw new ValidationException(
                    "Employer NI entry {$index}: Missing 'amount'",
                    ValidationException::ERROR_MISSING_REQUIRED_FIELD,
                    "entries[{$index}].amount",
                    null
                );
            }

            if (!isset($entry->quickbooksId)) {
                throw new ValidationException(
                    "Employer NI entry {$index}: Missing 'quickbooksId'",
                    ValidationException::ERROR_MISSING_REQUIRED_FIELD,
                    "entries[{$index}].quickbooksId",
                    null
                );
            }

            if (!isset($entry->class)) {
                throw new ValidationException(
                    "Employer NI entry {$index}: Missing 'class'",
                    ValidationException::ERROR_MISSING_REQUIRED_FIELD,
                    "entries[{$index}].class",
                    null
                );
            }

            if (!isset($entry->account)) {
                throw new ValidationException(
                    "Employer NI entry {$index}: Missing 'account'",
                    ValidationException::ERROR_MISSING_REQUIRED_FIELD,
                    "entries[{$index}].account",
                    null
                );
            }

            if (!is_numeric($entry->amount)) {
                throw ValidationException::invalidAmount("entries[{$index}].amount", $entry->amount);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function buildLines(array $data): array
    {
        $lines = [];
        $entries = $data['entries'];
        $sum = 0;

        // Create a debit line for each employee's employer NI
        foreach ($entries as $entry) {
            $line = $this->createLine(
                $this->getDescription('EMPLOYER_NI'),
                $entry->amount,
                $entry->quickbooksId,
                $entry->class,
                $entry->account
            );

            $this->addLine($lines, $line);

            // Accumulate total for credit line
            $sum += $entry->amount;
        }

        // Create single credit line for total employer NI
        $line = $this->createLine(
            'Total of ' . $this->getDescription('EMPLOYER_NI'),
            -$sum, // Negative = credit
            '', // No employee for the total line
            $this->getClass('ADMIN_CLASS'),
            $this->getAccount('TAX_ACCOUNT')
        );

        $this->addLine($lines, $line);

        return $lines;
    }
}
