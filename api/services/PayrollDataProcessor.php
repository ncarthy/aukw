<?php

namespace Services;

use Models\ApiResponse;
use Models\Payslip;

/**
 * Process payroll data and detect issues to report to the user
 *
 * This service analyzes payroll data and adds informational messages
 * without stopping processing
 */
class PayrollDataProcessor
{
    /**
     * Process payslips and detect issues
     *
     * @param array $payslips Array of payslip data from Staffology
     * @return ApiResponse Response with data and any informational messages
     */
    public static function processPayslips(array $payslips): ApiResponse
    {
        $response = new ApiResponse(true, $payslips);

        foreach ($payslips as $payslip) {
            $payrollNumber = $payslip->getPayrollNumber();
            if ($payrollNumber == 93) {
                //do nothing
                echo "";
            }

            // Check for negative pension contributions
            if ($payslip->getEmployeePension() < 0) {
                $response->addInfo(
                    sprintf(
                        'Employee %s has negative pension contribution: £%.2f',
                        $payslip->getEmployeeName() ?? 'Unknown',
                        $payslip->getEmployeePension()
                    ),
                    [
                        'employeeName' => $payslip->getEmployeeName() ?? null,
                        'payrollNumber' => $payslip->getPayrollNumber() ?? null,
                        'field' => 'employeePension',
                        'value' => $payslip->getEmployeePension()
                    ]
                );
            }

            // Check for negative employer pension
            if ($payslip->getEmployerPension() < 0) {
                $response->addWarning(
                    sprintf(
                        'Employee %s has negative employer pension: £%.2f, this will be processed as additional salary',
                        $payslip->getEmployeeName() ?? 'Unknown',
                        $payslip->getEmployerPension()
                    ),
                    [
                        'employeeName' => $payslip->getEmployeeName() ?? null,
                        'payrollNumber' => $payslip->getPayrollNumber() ?? null,
                        'field' => 'employerPension',
                        'value' => $payslip->getEmployerPension()
                    ]
                );
            }

            // Check for unusually high deductions
            if ($payslip->getTotalPay() > 0 && $payslip->getNetPay() > 0) {
                $totalPay = $payslip->getTotalPay();
                $netPay = $payslip->getNetPay();

                if ($totalPay > 0) {
                    $deductionPercentage = (($totalPay - $netPay) / $totalPay) * 100;
                } else {
                    $deductionPercentage = 0;
                }

                if ($deductionPercentage > 50) {
                    $response->addWarning(
                        sprintf(
                            'Employee %s has unusually high deductions: %.1f%% of gross pay',
                            $payslip->getEmployeeName() ?? 'Unknown',
                            $deductionPercentage
                        ),
                        [
                            'employeeName' => $payslip->getEmployeeName() ?? null,
                            'payrollNumber' => $payslip->getPayrollNumber() ?? null,
                            'totalPay' => $payslip->getTotalPay(),
                            'netPay' => $payslip->getNetPay(),
                            'deductionPercentage' => round($deductionPercentage, 2)
                        ]
                    );
                }
            }

            // Check for zero pay
            if ($payslip->getTotalPay() == 0) {
                $response->addInfo(
                    sprintf(
                        'Employee %s has zero total pay for this period',
                        $payslip->getEmployeeName() ?? 'Unknown'
                    ),
                    [
                        'employeeName' => $payslip->getEmployeeName() ?? null,
                        'payrollNumber' => $payslip->getPayrollNumber() ?? null,
                        'netPay' => $payslip->getNetPay(),
                    ]
                );
            }

            // Check for negative pay
            if ($payslip->getTotalPay() < 0) {
                $response->addInfo(
                    sprintf(
                        'Employee %s has negative total pay: £%.2f',
                        $payslip->getEmployeeName() ?? 'Unknown',
                        $payslip->getTotalPay()

                    ),
                    [
                        'employeeName' => $payslip->getEmployeeName() ?? null,
                        'payrollNumber' => $payslip->getPayrollNumber() ?? null,
                        'netPay' => $payslip->getNetPay(),
                    ]
                );
            }
        }

        return $response;
    }

    /**
     * Validate journal entry and add warnings for edge cases
     *
     * @param array $journalEntry The journal entry to validate
     * @return ApiResponse Response with validation messages
     */
    public function validateJournalEntry(array $journalEntry): ApiResponse
    {
        $response = new ApiResponse(true, $journalEntry);

        // Check for unbalanced entries (should never happen with factory, but just in case)
        $debits = 0;
        $credits = 0;

        foreach ($journalEntry['Line'] ?? [] as $line) {
            $amount = $line['Amount'] ?? 0;
            $postingType = $line['JournalEntryLineDetail']['PostingType'] ?? '';

            if ($postingType === 'Debit') {
                $debits += $amount;
            } else {
                $credits += $amount;
            }
        }

        $difference = abs($debits - $credits);
        if ($difference > 0.01) {
            $response->addWarning(
                sprintf('Journal entry is unbalanced by £%.2f', $difference),
                [
                    'debits' => $debits,
                    'credits' => $credits,
                    'difference' => $difference
                ]
            );
        }

        return $response;
    }
}
