<?php

namespace Services;

use Config\PayrollConfig;

/**
 * Base class for journal line builders
 *
 * Provides common functionality for building journal lines
 *
 * @category Service
 */
abstract class BaseJournalLineBuilder implements JournalLineBuilderInterface
{
    /**
     * Create a single journal line
     *
     * This is the equivalent of the payrolljournal_line() method in QuickbooksJournal
     * but returns the line instead of pushing it to an array
     *
     * @param string $description Line description
     * @param float $amount Line amount (positive = debit, negative = credit)
     * @param string $employeeId QuickBooks employee ID (can be empty string)
     * @param string $classId QuickBooks class ID
     * @param string $accountId QuickBooks account ID
     * @return array|null Journal line array, or null if amount is effectively zero
     */
    protected function createLine(
        string $description,
        float $amount,
        string $employeeId,
        string $classId,
        string $accountId
    ): ?array {
        // Skip lines with effectively zero amounts
        if (PayrollConfig::isZeroAmount($amount)) {
            return null;
        }

        return [
            'Description' => $description,
            'Amount' => abs($amount),
            'DetailType' => 'JournalEntryLineDetail',
            'JournalEntryLineDetail' => [
                'PostingType' => ($amount < 0 ? 'Credit' : 'Debit'),
                'Entity' => [
                    'Type' => 'Employee',
                    'EntityRef' => $employeeId
                ],
                'AccountRef' => $accountId,
                'ClassRef' => $classId,
            ]
        ];
    }

    /**
     * Add a line to the lines array if it's not null
     *
     * @param array $lines Array of lines (passed by reference)
     * @param array|null $line Line to add (or null to skip)
     * @return void
     */
    protected function addLine(array &$lines, ?array $line): void
    {
        if ($line !== null) {
            $lines[] = $line;
        }
    }

    /**
     * Get description from configuration
     *
     * @param string $descriptionKey Description key from PayrollConfig
     * @return string Description text
     */
    protected function getDescription(string $descriptionKey): string
    {
        return PayrollConfig::getDescription($descriptionKey);
    }

    /**
     * Get account ID from configuration
     *
     * @param string $accountKey Account key from PayrollConfig
     * @param bool $isEnterprises Whether to use enterprises accounts (default: false for charity)
     * @return string Account ID
     */
    protected function getAccount(string $accountKey, bool $isEnterprises = false): string
    {
        if ($isEnterprises) {
            return PayrollConfig::getEnterprisesAccount($accountKey);
        }
        return PayrollConfig::getCharityAccount($accountKey);
    }

    /**
     * Get class ID from configuration
     *
     * @param string $classKey Class key from PayrollConfig
     * @return string Class ID
     */
    protected function getClass(string $classKey): string
    {
        return PayrollConfig::getClass($classKey);
    }
}
