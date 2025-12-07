<?php

namespace Controllers\Staffology;

use Core\ErrorResponse as Error;
use Exception;
use Models\Staffology\PayRuns;
use Controllers\Staffology\TaxYearCtl;

/**
 * Controller to accomplish PayRun related tasks.
 *
 * @category  Controller
*/
class PayRunCtl
{
    /**
     * Return details of all PayRuns, in JSON format.
     *
     * @param string $employerId The Staffology Employer ID
     * @param string $taxYear The Staffology Tax Year
     * @return void Output is echo'd directly to response
     */
    public static function read_all(string $employerId, string $taxYear): void
    {
        try {
            $payruns = PayRuns::getInstance()
              ->setEmployerId($employerId)
              ->setTaxYear($taxYear)
              ->read();

            echo json_encode($payruns, JSON_NUMERIC_CHECK);
        } catch (Exception $e) {
            Error::response("Error retrieving details of all Payruns.", $e);
        }
    }

    /**
   * Return the most recent PayRun, in JSON format.
   *
   * @param string $employerId The Staffology Employer ID
   * @return void Output is echo'd directly to response
   */
    public static function read_most_recent(string $employerId, ?string $taxYear = null): void
    {
        try {
            if ($taxYear === null) {
                $taxYears = TaxYearCtl::read_names_as_array();
                if ($taxYears === null || count($taxYears) === 0) {
                    throw new Exception("No tax years found.");
                } else {
                    // Sort descending by year to get the latest first
                    usort($taxYears, function ($a, $b) {
                        return $b['year'] <=> $a['year'];
                    });
                }

                foreach ($taxYears as $taxYearInfo) {
                    $payruns = PayRuns::getInstance()
                      ->setEmployerId($employerId)
                      ->setTaxYear($taxYearInfo['value'])
                      ->read();

                    if (is_array($payruns) && count($payruns) > 0) {
                        echo json_encode($payruns[count($payruns) - 1], JSON_NUMERIC_CHECK);
                        return;
                    }
                    break;
                }
            } else {
                $payruns = PayRuns::getInstance()
                  ->setEmployerId($employerId)
                  ->setTaxYear($taxYear)
                  ->read();

                echo json_encode($payruns[count($payruns) - 1], JSON_NUMERIC_CHECK);
            }

        } catch (Exception $e) {
            Error::response("Error retrieving details of most recent Payrun.", $e);
        }
    }

}
