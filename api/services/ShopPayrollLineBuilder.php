<?php

namespace Services;

use Config\PayrollConfig;
use Errors\ValidationException;

/**
 * Builder for shop payroll journal lines (Enterprises)
 *
 * Creates journal lines for shop employees:
 * - Gross salary (debit to AUEW_SALARIES_ACCOUNT, credit to AUKW_INTERCO_ACCOUNT)
 * - Employer NI (debit to AUEW_NI_ACCOUNT, credit to AUKW_INTERCO_ACCOUNT)
 * - Employer pension (debit to AUEW_PENSIONS_ACCOUNT, credit to AUKW_INTERCO_ACCOUNT)
 *
 * All lines use HARROW_ROAD_CLASS
 *
 * @category Service
 */
class ShopPayrollLineBuilder extends BaseJournalLineBuilder
{
    /**
     * {@inheritdoc}
     */
    public function getTransactionType(): string
    {
        return PayrollConfig::TRANSACTION_TYPES['SHOP_PAYROLL'];
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
                "Shop payroll journal must have at least one entry",
                ValidationException::ERROR_MISSING_REQUIRED_FIELD,
                'entries',
                $data['entries']
            );
        }

        // Validate each entry
        foreach ($data['entries'] as $index => $entry) {
            if (!isset($entry->totalPay)) {
                throw new ValidationException(
                    "Shop payroll entry {$index}: Missing 'totalPay'",
                    ValidationException::ERROR_MISSING_REQUIRED_FIELD,
                    "entries[{$index}].totalPay",
                    null
                );
            }

            if (!isset($entry->quickbooksId)) {
                throw new ValidationException(
                    "Shop payroll entry {$index}: Missing 'quickbooksId'",
                    ValidationException::ERROR_MISSING_REQUIRED_FIELD,
                    "entries[{$index}].quickbooksId",
                    null
                );
            }

            if (!is_numeric($entry->totalPay)) {
                throw ValidationException::invalidAmount("entries[{$index}].totalPay", $entry->totalPay);
            }

            // Optional fields
            if (isset($entry->employerNI) && !is_numeric($entry->employerNI)) {
                throw ValidationException::invalidAmount("entries[{$index}].employerNI", $entry->employerNI);
            }

            if (isset($entry->employerPension) && !is_numeric($entry->employerPension)) {
                throw ValidationException::invalidAmount("entries[{$index}].employerPension", $entry->employerPension);
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
        $harrowRoadClass = $this->getClass('HARROW_ROAD_CLASS');

        foreach ($entries as $entry) {
            $employeeId = $entry->quickbooksId;

            // Gross salary - debit to AUEW_SALARIES_ACCOUNT
            $line = $this->createLine(
                $this->getDescription('GROSS_SALARY'),
                $entry->totalPay,
                $employeeId,
                $harrowRoadClass,
                $this->getAccount('AUEW_SALARIES_ACCOUNT', true) // true = enterprises account
            );
            $this->addLine($lines, $line);

            // Gross salary - credit to AUKW_INTERCO_ACCOUNT
            $line = $this->createLine(
                $this->getDescription('GROSS_SALARY'),
                -$entry->totalPay, // Negative = credit
                $employeeId,
                $harrowRoadClass,
                $this->getAccount('AUKW_INTERCO_ACCOUNT', true)
            );
            $this->addLine($lines, $line);

            // Employer NI (if present)
            if (isset($entry->employerNI) && !PayrollConfig::isZeroAmount($entry->employerNI)) {
                // Debit to AUEW_NI_ACCOUNT
                $line = $this->createLine(
                    $this->getDescription('EMPLOYER_NI'),
                    $entry->employerNI,
                    $employeeId,
                    $harrowRoadClass,
                    $this->getAccount('AUEW_NI_ACCOUNT', true)
                );
                $this->addLine($lines, $line);

                // Credit to AUKW_INTERCO_ACCOUNT
                $line = $this->createLine(
                    $this->getDescription('EMPLOYER_NI'),
                    -$entry->employerNI,
                    $employeeId,
                    $harrowRoadClass,
                    $this->getAccount('AUKW_INTERCO_ACCOUNT', true)
                );
                $this->addLine($lines, $line);
            }

            // Employer pension (if present)
            if (isset($entry->employerPension) && !PayrollConfig::isZeroAmount($entry->employerPension)) {
                // Debit to AUEW_PENSIONS_ACCOUNT
                $line = $this->createLine(
                    $this->getDescription('EMPLOYER_PENSION_CONT'),
                    $entry->employerPension,
                    $employeeId,
                    $harrowRoadClass,
                    $this->getAccount('AUEW_PENSIONS_ACCOUNT', true)
                );
                $this->addLine($lines, $line);

                // Credit to AUKW_INTERCO_ACCOUNT
                $line = $this->createLine(
                    $this->getDescription('EMPLOYER_PENSION_CONT'),
                    -$entry->employerPension,
                    $employeeId,
                    $harrowRoadClass,
                    $this->getAccount('AUKW_INTERCO_ACCOUNT', true)
                );
                $this->addLine($lines, $line);
            }
        }

        return $lines;
    }
}
