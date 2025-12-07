<?php

namespace Models\Staffology;

use Core\Config;
use Services\PayrollApiService;

/**
 * Factory class that provides a method to query PayRuns
 *
 * @category Model
 */
class PayRuns
{
    /**
    * Service object to call Payroll API
    * @var PayrollApiService|null
    */
    private $apiService;
    /**
     * The Staffology Employer Id
     *
     * @var string
     */
    protected string $employerId;
    /**
   * The Staffology tax year. It's a string like 'Year2025', 'Year2024', etc.
   *
   * @var string
   */
    protected string $taxYear;

    /**
     * Employer Id setter
     */
    public function setEmployerId(string $employerId)
    {
        $this->employerId = $employerId;
        return $this;
    }

    /**
     * Employer ID getter.
     */
    public function getEmployerId(): string
    {
        return $this->employerId;
    }

    /**
    * Tax Year setter
    */
    public function setTaxYear(string $taxYear)
    {
        $this->taxYear = $taxYear;
        return $this;
    }

    /**
     * Tax Year getter.
     */
    public function getTaxYear(): string
    {
        return $this->taxYear;
    }
    /**
     * Constructor
     */
    protected function __construct()
    {
        $this->apiService = new PayrollApiService();
    }

    /**
     * Static constructor / factory
     */
    public static function getInstance()
    {
        return new self();
    }

    /**
     * Return an array of PayRuns
     * @return array Returns an array
     */
    public function read(): array
    {

        // Build endpoint
        $endpoint = 'employers/' . $this->employerId .
                      '/payrun/' .
                      $this->taxYear .
                      '/' .
                      Config::read('staffology.payperiod');

        $payruns = $this->apiService->get($endpoint);

        $items = array();

        if (is_array($payruns)) {
            foreach ($payruns as $payrun) {

                $metadata = $payrun['metadata'];

                $isClosed = $metadata['isClosed'] ?? false;
                if (!$isClosed) {
                    // Skip open payruns, only interested in finalised payruns
                    continue;
                }

                // Extract relevant fields
                $items[] = array(
                        "name" => $payrun['name'] ?? '',
                        "taxMonth" => $metadata['taxMonth'] ?? '',
                        "taxYear" => $metadata['taxYear'] ?? '',
                        "startDate" => $metadata['startDate'] ?? '',
                        "endDate" => $metadata['endDate'] ?? '',
                        // "paymentDate" is always empty in the API response
                        //"paymentDate" => $metadata['paymentDate'] ?? '',
                        "isClosed" => $isClosed,
                        "state" => $metadata['state'] ?? '',
                        "totalCost" => $metadata['totalCost'] ?? 0,
                        "gross" => $metadata['gross'] ?? 0,
                        "employerNi" => $metadata['employerNi'] ?? 0,
                        "employerPensionContribution" =>
                                  $metadata['employerPensionContribution'] ?? 0,
                        "employeeCount" => $metadata['employeeCount'] ?? 0,
                        "version" => $metadata['version'] ?? 0,
                        "isLatestVersion" => $metadata['isLatestVersion'] ?? true,
                    );
            }

            // Sort by tax month ascending
            usort($items, fn ($a, $b) => $a['taxMonth'] <=> $b['taxMonth']);            
        } else {
            throw new \Exception("Unexpected response from Staffology API when retrieving PayRuns. Not an Array.");
        }

        return $items;

    }


}
