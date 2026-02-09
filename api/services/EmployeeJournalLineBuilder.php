<?php

namespace Services;

use Config\PayrollConfig;
use Core\QuickbooksConstants as QBO;
use Errors\ValidationException;

/**
 * Builder for employee journal lines
 *
 * Creates journal lines for employee payroll including:
 * - Gross salary (with allocations)
 * - PAYE (income tax)
 * - Employee NI
 * - Salary sacrifice
 * - Employee pension contributions
 * - Other deductions
 * - Student loan deductions
 * - Net pay
 *
 * @category Service
 */
class EmployeeJournalLineBuilder extends BaseJournalLineBuilder
{
    /**
     * {@inheritdoc}
     */
    public function getTransactionType(): string
    {
        return PayrollConfig::TRANSACTION_TYPES['EMPLOYEE_JOURNAL'];
    }

    /**
     * {@inheritdoc}
     */
    public function validateData(array $data): void
    {
        // Required fields
        if (!isset($data['quickbooksEmployeeId'])) {
            throw ValidationException::missingRequiredField('quickbooksEmployeeId');
        }

        if (!isset($data['grossSalary']) || !is_array($data['grossSalary'])) {
            throw ValidationException::missingRequiredField('grossSalary');
        }

        if (empty($data['grossSalary'])) {
            throw new ValidationException(
                "Employee journal must have at least one gross salary allocation",
                ValidationException::ERROR_MISSING_REQUIRED_FIELD,
                'grossSalary',
                $data['grossSalary']
            );
        }

        // Validate each allocation
        foreach ($data['grossSalary'] as $index => $allocation) {
            if (!isset($allocation->amount)) {
                throw new ValidationException(
                    "Gross salary allocation {$index}: Missing 'amount'",
                    ValidationException::ERROR_MISSING_REQUIRED_FIELD,
                    "grossSalary[{$index}].amount",
                    null
                );
            }

            if (!isset($allocation->class)) {
                throw new ValidationException(
                    "Gross salary allocation {$index}: Missing 'class'",
                    ValidationException::ERROR_MISSING_REQUIRED_FIELD,
                    "grossSalary[{$index}].class",
                    null
                );
            }

            if (!isset($allocation->account)) {
                throw new ValidationException(
                    "Gross salary allocation {$index}: Missing 'account'",
                    ValidationException::ERROR_MISSING_REQUIRED_FIELD,
                    "grossSalary[{$index}].account",
                    null
                );
            }
        }

        // Numeric fields (can be zero or missing)
        $numericFields = [
            'netSalary', 'paye', 'employeeNI', 'salarySacrifice',
            'employeePensionContribution', 'studentLoan', 'otherDeduction'
        ];

        foreach ($numericFields as $field) {
            if (isset($data[$field]) && !is_numeric($data[$field])) {
                throw ValidationException::invalidAmount($field, $data[$field]);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function buildLines(array $data): array
    {
        $lines = [];

        $employeeId = $data['quickbooksEmployeeId'];
        $grossSalaryAllocations = $data['grossSalary'];

        // Get amounts (default to 0 if not provided)
        $netSalary = $data['netSalary'] ?? 0;
        $paye = $data['paye'] ?? 0;
        $employeeNI = $data['employeeNI'] ?? 0;
        $salarySacrifice = $data['salarySacrifice'] ?? 0;
        $employeePensionContribution = $data['employeePensionContribution'] ?? 0;
        $studentLoan = $data['studentLoan'] ?? 0;
        $otherDeduction = $data['otherDeduction'] ?? 0;

        // Gross salary lines (one per allocation)
        foreach ($grossSalaryAllocations as $allocation) {
            // Determine account: AUEW or STAFF_SALARIES
            $account = ($allocation->account == QBO::AUEW_ACCOUNT)
                ? $this->getAccount('AUEW_ACCOUNT')
                : $this->getAccount('STAFF_SALARIES_ACCOUNT');

            $line = $this->createLine(
                $this->getDescription('GROSS_SALARY'),
                $allocation->amount,
                $employeeId,
                $allocation->class,
                $account
            );

            $this->addLine($lines, $line);
        }

        // PAYE (income tax) - credit (negative amount)
        $line = $this->createLine(
            $this->getDescription('PAYE'),
            -$paye,
            $employeeId,
            $this->getClass('ADMIN_CLASS'),
            $this->getAccount('TAX_ACCOUNT')
        );
        $this->addLine($lines, $line);

        // Employee NI - credit (negative amount)
        $line = $this->createLine(
            $this->getDescription('EMPLOYEE_NI'),
            -$employeeNI,
            $employeeId,
            $this->getClass('ADMIN_CLASS'),
            $this->getAccount('TAX_ACCOUNT')
        );
        $this->addLine($lines, $line);

        // Salary sacrifice - credit (negative amount)
        $line = $this->createLine(
            $this->getDescription('SALARY_SACRIFICE'),
            -$salarySacrifice,
            $employeeId,
            $this->getClass('ADMIN_CLASS'),
            $this->getAccount('SALARY_SACRIFICE_ACCOUNT')
        );
        $this->addLine($lines, $line);

        // Employee pension contribution - credit (negative amount)
        $line = $this->createLine(
            $this->getDescription('EMPLOYEE_PENSION_CONT'),
            -$employeePensionContribution,
            $employeeId,
            $this->getClass('ADMIN_CLASS'),
            $this->getAccount('EMPLOYEE_PENSION_CONTRIB_ACCOUNT')
        );
        $this->addLine($lines, $line);

        // Other deductions - credit (negative amount)
        $line = $this->createLine(
            $this->getDescription('OTHER_DEDUCTIONS'),
            -$otherDeduction,
            $employeeId,
            $this->getClass('ADMIN_CLASS'),
            $this->getAccount('OTHER_DEDUCTIONS_ACCOUNT')
        );
        $this->addLine($lines, $line);

        // Student loan - credit (negative amount)
        $line = $this->createLine(
            $this->getDescription('STUDENT_LOAN'),
            -$studentLoan,
            $employeeId,
            $this->getClass('ADMIN_CLASS'),
            $this->getAccount('TAX_ACCOUNT')
        );
        $this->addLine($lines, $line);

        // Net pay - credit (negative amount)
        $line = $this->createLine(
            $this->getDescription('NET_PAY'),
            -$netSalary,
            $employeeId,
            $this->getClass('ADMIN_CLASS'),
            $this->getAccount('NET_PAY_ACCOUNT')
        );
        $this->addLine($lines, $line);

        return $lines;
    }
}
