<?php

namespace Services;

use Config\PayrollConfig;
use Errors\ValidationException;

/**
 * Builder for pension journal lines
 *
 * Note: Pensions are typically created as Bills (not Journal Entries) in QuickBooks.
 * This builder is provided for consistency and future use if pension journal entries
 * are needed.
 *
 * Creates journal lines for pension costs:
 * - Salary sacrifice totals
 * - Employee pension contribution totals
 * - Employer pension costs (with allocations)
 *
 * @category Service
 */
class PensionLineBuilder extends BaseJournalLineBuilder
{
    /**
     * {@inheritdoc}
     */
    public function getTransactionType(): string
    {
        return PayrollConfig::TRANSACTION_TYPES['PENSIONS'];
    }

    /**
     * {@inheritdoc}
     */
    public function validateData(array $data): void
    {
        // Numeric fields (can be zero or missing)
        $numericFields = ['salarySacrificeTotal', 'employeePensContribTotal'];

        foreach ($numericFields as $field) {
            if (isset($data[$field]) && !is_numeric($data[$field])) {
                throw ValidationException::invalidAmount($field, $data[$field]);
            }
        }

        // Pension costs array (optional)
        if (isset($data['pensionCosts'])) {
            if (!is_array($data['pensionCosts'])) {
                throw new ValidationException(
                    "pensionCosts must be an array",
                    ValidationException::ERROR_MISSING_REQUIRED_FIELD,
                    'pensionCosts',
                    $data['pensionCosts']
                );
            }

            // Validate each allocation
            foreach ($data['pensionCosts'] as $index => $allocation) {
                if (!isset($allocation->amount)) {
                    throw new ValidationException(
                        "Pension cost allocation {$index}: Missing 'amount'",
                        ValidationException::ERROR_MISSING_REQUIRED_FIELD,
                        "pensionCosts[{$index}].amount",
                        null
                    );
                }

                if (!isset($allocation->class)) {
                    throw new ValidationException(
                        "Pension cost allocation {$index}: Missing 'class'",
                        ValidationException::ERROR_MISSING_REQUIRED_FIELD,
                        "pensionCosts[{$index}].class",
                        null
                    );
                }

                if (!isset($allocation->account)) {
                    throw new ValidationException(
                        "Pension cost allocation {$index}: Missing 'account'",
                        ValidationException::ERROR_MISSING_REQUIRED_FIELD,
                        "pensionCosts[{$index}].account",
                        null
                    );
                }

                if (!is_numeric($allocation->amount)) {
                    throw ValidationException::invalidAmount("pensionCosts[{$index}].amount", $allocation->amount);
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function buildLines(array $data): array
    {
        $lines = [];

        // Get amounts (default to 0 if not provided)
        $salarySacrificeTotal = $data['salarySacrificeTotal'] ?? 0;
        $employeePensContribTotal = $data['employeePensContribTotal'] ?? 0;
        $pensionCosts = $data['pensionCosts'] ?? [];

        // Salary sacrifice total - debit
        $line = $this->createLine(
            'Monthly total of salary sacrifices',
            $salarySacrificeTotal,
            '', // No employee for totals
            $this->getClass('ADMIN_CLASS'),
            $this->getAccount('SALARY_SACRIFICE_ACCOUNT')
        );
        $this->addLine($lines, $line);

        // Employee pension contribution total - debit
        $line = $this->createLine(
            'Monthly total of employee pension contributions',
            $employeePensContribTotal,
            '', // No employee for totals
            $this->getClass('ADMIN_CLASS'),
            $this->getAccount('EMPLOYEE_PENSION_CONTRIB_ACCOUNT')
        );
        $this->addLine($lines, $line);

        // Employer pension costs - one line per allocation
        foreach ($pensionCosts as $allocation) {
            // Determine account: AUEW or PENSION_COSTS
            $account = ($allocation->account == $this->getAccount('AUEW_ACCOUNT'))
                ? $this->getAccount('AUEW_ACCOUNT')
                : $this->getAccount('PENSION_COSTS_ACCOUNT');

            $line = $this->createLine(
                $allocation->name ?? 'Employer pension contribution',
                $allocation->amount,
                '', // No employee for employer costs
                $allocation->class,
                $account
            );

            $this->addLine($lines, $line);
        }

        // Note: In the actual implementation, pensions are created as Bills, not Journal Entries
        // A credit line to balance the journal would be needed if this is used

        return $lines;
    }
}
