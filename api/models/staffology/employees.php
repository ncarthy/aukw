<?php

namespace Models\Staffology;

use Services\PayrollApiService;
use Models\Staffology\EmployeeStatus;

enum EmployeesSortBy: string
{
    case PayrollCode = 'PayrollCode';
    case Employee = 'Employee';
    case Department = 'Department';
    case PaySchedule = 'PaySchedule';
}

/**
 * Factory class that provides a method to query PayRuns
 *
 * @category Model
 */
class Employees
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
     * The employee status. One of former, Upcoming or Current
     *
     * @var EmployeeStatus
     */
    protected EmployeeStatus $status;   
    
    /**
     * Defines the way to sort the data. Defaults to sorting by PayrollCode.
     *
     * @var EmployeesSortBy
     */
    protected EmployeesSortBy $sortBy;    

    /**
     * The sort order. Defaults to ascending.
     *
     * @var bool
     */
    protected bool $sortDescending = false;

    /**
     * Sort By setter
     */
    public function setSortBy(?EmployeesSortBy $sortBy)
    {
        if ($sortBy == null) {
            $sortBy = EmployeesSortBy::PayrollCode;
        }
        $this->sortBy = $sortBy;
        return $this;
    }

    /**
     * Sort By getter.
     */
    public function getSortBy(): string
    {
        if (isset($this->sortBy) == false) {
            return EmployeesSortBy::PayrollCode->value;
        }
        return $this->sortBy->value;
    }    

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
     * Employment status setter
     */
    public function setStatus(?EmployeeStatus $status)
    {
        if ($status == null) {
            $status = EmployeeStatus::Current;
        }
        $this->status = $status;
        return $this;
    }

    /**
     * Employment status getter.
     */
    public function getStatus(): string
    {
        if (isset($this->status) == false) {
            return EmployeeStatus::Current->value;
        }
        return $this->status->value;
    }


    /**
     * sortDescending setter
     */
    public function setSortDescending(bool $sortDescending)
    {
        $this->sortDescending = $sortDescending;
        return $this;
    }

    /**
     * sortDescending getter.
     */
    public function getSortDescending(): bool
    {
        return $this->sortDescending;
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
     * Return information about an employee
     * @return array Returns an array
     */
    public function read(): array
    {

        // Build endpoint
        $endpoint = 'employers/' . $this->employerId .
                      '/employees';

        if (isset($this->status)) {
            $params = array(
                'status' => $this->getStatus(),
                'sortBy' => $this->getSortBy(),
                'sortDescending' => $this->sortDescending ? 'true' : 'false'
            );
        } else {
            $params = array(
                'sortBy' => $this->getSortBy(),
                'sortDescending' => $this->sortDescending ? 'true' : 'false'
            );
        }

        $employees = $this->apiService->get($endpoint, $params);

        $items = array();

        if (is_array($employees)) {
            foreach ($employees as $employee) {

                $metadata = $employee['metadata'];

                // Extract relevant fields
                $items[] = array(
                        "id" => $employee['id'] ?? '',
                        "name" => $employee['name'] ?? '',
                        "payrollNumber" => $metadata['payrollCode'] ?? '',
                        "niNumber" => $metadata['niNumber'] ?? '',
                        "status" => $metadata['status'] ?? '',
                        "taxCode" => $metadata['taxCode'] ?? '',
                        "email" => $metadata['email'] ?? '',                        
                        "basicPay" => $metadata['basicPay'] ?? 0,
                        "pensionSchemeName" => $metadata['pensionSchemeName'] ?? '',
                        "pensionStartDate" => $metadata['pensionStartDate'] ?? '',
                    );
            }

            // Sort by payrollNumber ascending
            usort($items, fn ($a, $b) => $a['payrollNumber'] <=> $b['payrollNumber']);

        } else {
            throw new \Exception("Unexpected response from Staffology API when retrieving Employees. Not an Array.");
        }

        return $items;

    }


}
