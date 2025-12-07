<?php

namespace Controllers\Staffology;

use Core\ErrorResponse as Error;
use Models\Staffology\Employees;
use Models\Staffology\Employee;
use Models\Staffology\EmployeeStatus;
use Exception;

/**
 * Controller to accomplish PayRun related tasks.
 *
 * @category  Controller
*/
class EmployeeCtl
{
    /**
     * Return details of all Employees, in JSON format.
     *
     * @param string $employerId The Staffology Employer ID
     * @return void Output is echo'd directly to response
     */
    public static function read(string $employerId): void
    {
        try {

            $employees = Employees::getInstance()
              ->setEmployerId($employerId);

            parse_str($_SERVER['QUERY_STRING'], $queries);
            if ($queries && isset($queries['status'])) {
                $employees  = $employees->setStatus(
                    EmployeeStatus::from($queries['status'])
                );              
            }

            $employees = $employees->read();

            echo json_encode($employees, JSON_NUMERIC_CHECK);
        } catch (Exception $e) {
            Error::response("Error retrieving details of all Employees.", $e);
        }
    }

        /**
     * Return details of one employee, identified by tyhe Staffology employee if.
     *
     * @param string $employerId The Staffology Employer ID (uuid format)
     * @param string $employeeId The Staffology Employee ID (uuid format)
     * @return void Output is echo'd directly to response
     */
    public static function readOneById(string $employerId, string $employeeId): void
    {
        try {

            $employee = Employee::getInstance()
              ->setEmployerId($employerId)
              ->setEmployeeId($employeeId)
              ->read();

            echo json_encode($employee, JSON_NUMERIC_CHECK);
        } catch (Exception $e) {
            Error::response("Error retrieving details of employee with id=" . $employeeId . ".", $e);
        }
    }

    /**
     * Return details of all Employees, in JSON format.
     *
     * @param string $employerId The Staffology Employer ID (uuid format)
     * @param int $payrollNumber The payroll number of the Employee
     * @return void Output is echo'd directly to response
     */
    public static function readOneByPayrollNumber(string $employerId, int $payrollNumber): void
    {
        try {

            $employees = Employees::getInstance()
              ->setEmployerId($employerId)
              ->read();

            $employeeId ='';

            foreach ($employees as $key => $value) {
                if ($value['payrollNumber'] == $payrollNumber) {
                    $employeeId = $value['id'];
                    break;
                }
            }

            if ($employeeId != '') {
                $employee = Employee::getInstance()
                ->setEmployerId($employerId)
                ->setEmployeeId($employeeId)
                ->read();
            } else {
                throw new \Exception("Could not find that employee.");
            }

            echo json_encode($employee, JSON_NUMERIC_CHECK);
        } catch (Exception $e) {
            Error::response("Error retrieving details of employee with payrollNumber=" . $payrollNumber . ".", $e);
        }
    }

}
