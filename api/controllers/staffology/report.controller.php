<?php

namespace Controllers\Staffology;

use Core\ErrorResponse as Error;
use Exception;
use Models\Staffology\GrossToNetReport;
use Models\Staffology\GrossToNetSortBy;
use Models\Staffology\ParseGrosstoNetReport;
use Services\PayrollDataProcessor;

/**
 * Controller to retrieve Payroll report from Staffology API and parse it for use in the system.
 *
 * @category  Controller
*/
class PayrollReportCtl
{
    /**
     * Retrieve Gross-To-Net report from Staffology API and parse it into payslip details
     *
     * @param string $employerId The Staffology Employer ID
     * @param string $taxYear The Staffology Tax Year
     * @param int $month The month number (1-12)
     * @return void Output is echo'd directly to response
     */
    public static function gross_to_net(string $employerId, string $taxYear, int $month): void
    {
        try {

            parse_str($_SERVER['QUERY_STRING'], $queries);
            $sortDescending = isset($queries['sortDescending']) &&
                              ($queries['sortDescending'] == 'true' ||
                                $queries['sortDescending'] == '1') ? true : false;
            if (isset($queries['sortBy'])) {
                $sortBy = GrossToNetSortBy::from($queries['sortBy']);
            } else {
                $sortBy = GrossToNetSortBy::PayrollCode;
            }
            if (isset($queries['payrollDate']) && $queries['payrollDate'] != '') {
                if (!\Core\DatesHelper::validateDate($queries['payrollDate'])) {
                    throw new \InvalidArgumentException("'payrollDate' parameter is not in the correct format. Value provided: " .
                                      $queries['payrollDate'] . ", but expected yyyy-mm-dd format.");
                }
                $payrollDate = $queries['payrollDate'];
            } else {
                $payrollDate = sprintf(
                    '%04d-%02d-25',
                    intval(substr($taxYear, 4)),
                    ($month > 9) ? $month - 9 : $month + 3
                );
            }

            $salaryData = GrossToNetReport::getInstance()
              ->setEmployerId($employerId)
              ->setTaxYear($taxYear)
              ->setFromPeriod($month)
              ->setToPeriod($month)
              ->setSortBy($sortBy)
              ->setSortDescending($sortDescending)
              ->generate();

            $payslips = ParseGrosstoNetReport::parse($salaryData, $payrollDate);

            echo json_encode( PayrollDataProcessor::processPayslips($payslips), JSON_NUMERIC_CHECK);
        } catch (Exception $e) {
            Error::response("Error retrieving Gross-To-Net report for " . $taxYear . " Month " . $month, $e);
        }
    }



}
