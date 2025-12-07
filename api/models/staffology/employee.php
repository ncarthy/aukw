<?php

namespace Models\Staffology;

use Core\Config;
use Services\PayrollApiService;

enum EmployeeStatus: string
{
    case Current = 'Current';
    case Former = 'Former';
    case Upcoming = 'Upcoming';
}

/**
 * Factory class that provides a method to query PayRuns
 *
 * @category Model
 */
class Employee
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
     * The Staffology Employee Id
     *
     * @var string
     */
    protected string $employeeId;
    /**
   * The Staffology payrollCode,
   *
   * @var string
   */
    protected string $payrollCode;

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
     * Employee Id setter
     */
    public function setEmployeeId(string $employeeId)
    {
        $this->employeeId = $employeeId;
        return $this;
    }

    /**
     * Employee ID getter.
     */
    public function getEmployeeId(): string
    {
        return $this->employeeId;
    }

    /**
    * Payroll Code setter
    */
    public function setPayrollCode(string $payrollCode)
    {
        $this->payrollCode = $payrollCode;
        return $this;
    }

    /**
     * Payroll Code getter.
     */
    public function getPayrollCode(): string
    {
        return $this->payrollCode;
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
                      '/employees/' .
                      $this->employeeId ;

        $employee = $this->apiService->get($endpoint);

        if (is_array($employee)) {

                $personalDetails = $employee['personalDetails'];
                $employmentDetails = $employee['employmentDetails'];

                // Extract relevant fields
                $return_obj = array(
                        "id" => $employee['id'] ?? '',
                        "status" => $employee['status'] ?? '',
                        "title" => $personalDetails['title'] ?? '',
                        "firstName" => $personalDetails['firstName'] ?? '',
                        "lastName" => $personalDetails['lastName'] ?? '',
                        "email" => $personalDetails['email'] ?? '',
                        "gender" => $personalDetails['gender'] ?? '',
                        "niNumber" => $personalDetails['niNumber'] ?? '',
                        "maritalStatus" => $personalDetails['maritalStatus'] ?? '',
                        "payrollNumber" => $employmentDetails['payrollCode'] ?? '',
                    );

        } else {
            throw new \Exception("Unexpected response from Staffology API when retrieving Employee. Not an Array.");
        }

        return $return_obj;

    }


}
