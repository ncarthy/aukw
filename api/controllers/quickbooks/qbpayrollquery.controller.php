<?php

namespace Controllers\QuickBooks;

use Core\QuickbooksConstants as QBO;
use Models\Payslip;
use Models\QuickbooksQuery;
use Models\QuickbooksEmployee;
use Core\ErrorResponse as Error;
use Exception;

/**
 * Controller to query QBO and return (in Payslip format) details of current month's payroll
 *
 * @category  Controller
*/
class QBPayrollQueryCtl
{
    /**
     * Query QBO and return (in Payslip format) details of any payroll entries for the given month and year.
     *
     * @param string $realmid The company ID for the QBO company.
     * @param int $year The year of the payroll run. e.g. 2024
     * @param int $month The number of the month of the payroll run. e.g. '3' for March
     * @return void Output is echo'd directly to response
     */
    public static function query(string $realmid, int $year, int $month): void
    {
        try {
            $payslips = array(); // this will be returned

            $payrollIdentifier = QBO::payrollDocNumber($year . '-' . $month . '-25'); // day of month is irrelevent, using 25

            $employees = QuickbooksEmployee::getInstance()
              ->setRealmID($realmid)
              ->readAllAssociatedByName();

            $bills = QuickbooksQuery::getInstance()
              ->setRealmID($realmid)
              ->query_by_docnumber('Bill', $payrollIdentifier);

            QBPayrollQueryCtl::parsePensionBills($employees, $bills, $payslips);

            $journals = QuickbooksQuery::getInstance()
              ->setRealmID($realmid)
              ->query_by_docnumber('JournalEntry', $payrollIdentifier);

            QBPayrollQueryCtl::parsePayrollJournals($employees, $journals, $payslips);

            echo json_encode(array_values($payslips));
        } catch (Exception $e) {
            Error::response("Unable to complete the query of payroll entities.", $e);
        }
    }

    /**
     * Run through the payroll journals and extract details of the month's salary and deduction amounts.
     * Works for journals in both Charity and Enterprises company files.
     *
     * @param array $employees An array of QBO Employees, associated by Name
     * @param array $journals An array of QBO Journal Entry entities
     * @param array $payslips An array of payslips (type is /Models/Payslip)
     */
    private static function parsePayrollJournals(array $employees, array $journals, array &$payslips): void
    {
        try {
            // Convert $employees from keyed on employee name to keyed on QB id.
            $employeesById = array();
            foreach ($employees as $employee) {
                $employeesById[$employee['quickbooksId']] = $employee;
            }

            foreach ($journals as $journal) {
                // Check that $journal is of type IPPTransaction
                if (property_exists($journal, 'Line') && is_array($journal->Line)) {

                    foreach ($journal->Line as $line) {
                        $detail = $line->JournalEntryLineDetail;

                        if (!isset($detail->Entity) || !property_exists($detail->Entity, 'Type')
                          || $detail->Entity->Type != 'Employee' || !isset($detail->Entity->EntityRef)) {
                            continue;
                        }

                        // Handle case where EntityRef is an name/value object or a string
                        if (property_exists($detail->Entity->EntityRef, 'value')) {
                            $employeeId = $detail->Entity->EntityRef->value;
                        } else {
                            $employeeId = $detail->Entity->EntityRef;
                        }
                        $payrollNumber = $employeesById[$employeeId]['payrollNumber'];

                        if (!array_key_exists($payrollNumber, $payslips)) {
                            // Create a new payslip if none found
                            $payslips[$payrollNumber] = QBPayrollQueryCtl::createPayslip(
                                $payrollNumber,
                                $employeeId,
                                $employeesById[$employeeId]['name']
                            );
                        }
                        $payslip = $payslips[$payrollNumber];

                        $amount = $line->Amount;

                        // Handle case where AccountRef is an name/value object or a string
                        if (property_exists($detail->AccountRef, 'value')) {
                            $account = $detail->AccountRef->value;
                        } else {
                            $account = $detail->AccountRef;
                        }

                        // The type of GJ line that we are parsing is largely determined
                        // by its account. For shop employees we have to examine Description too.
                        switch ($account) {
                            case QBO::AUEW_ACCOUNT:
                                switch ($line->Description) {
                                    case QBO::EMPLOYER_NI_DESCRIPTION:
                                        $payslip->addToEmployerNI($amount);
                                        break;
                                    case QBO::GROSS_SALARY_DESCRIPTION:
                                        $payslip->addToTotalPay($amount);
                                        break;
                                    default:
                                }
                                break;
                            case QBO::AUEW_SALARIES_ACCOUNT:
                            case QBO::STAFF_SALARIES_ACCOUNT:
                                $payslip->addToTotalPay($amount);
                                break;
                            case QBO::TAX_ACCOUNT:
                                switch ($line->Description) {
                                    case QBO::PAYE_DESCRIPTION:
                                        if ($detail->PostingType == "Credit") {
                                            $amount *= -1;
                                        }
                                        $payslip->addToPAYE($amount);
                                        break;
                                    case QBO::EMPLOYEE_NI_DESCRIPTION:
                                        if ($detail->PostingType == "Credit") {
                                            $amount *= -1;
                                        }
                                        $payslip->addToEmployeeNI($amount);
                                        break;
                                    case QBO::STUDENT_LOAN_DESCRIPTION:
                                        if ($detail->PostingType == "Credit") {
                                            $amount *= -1;
                                        }
                                        $payslip->addToStudentLoan($amount);
                                        break;
                                    default:
                                }
                                break;
                            case QBO::OTHER_DEDUCTIONS_ACCOUNT:
                                if ($detail->PostingType == "Credit") {
                                    $amount *= -1;
                                }
                                $payslip->addToOtherDeductions($amount);
                                break;
                            case QBO::SALARY_SACRIFICE_ACCOUNT:
                                $payslip->addToSalarySacrifice($amount);
                                break;
                            case QBO::EMPLOYEE_PENSION_CONTRIB_ACCOUNT:
                                $payslip->addToEmployeePension($amount);
                                break;
                            case QBO::NET_PAY_ACCOUNT:
                                $payslip->addToNetPay($amount);
                                break;
                            case QBO::AUEW_NI_ACCOUNT:
                            case QBO::EMPLOYER_NI_ACCOUNT:
                                $payslip->addToEmployerNI($amount);
                                break;
                            case QBO::AUEW_PENSIONS_ACCOUNT:
                                $payslip->addToEmployerPension($amount);
                                break;
                            case QBO::AUKW_INTERCO_ACCOUNT:
                                // If Enterprises' AUKW interco account then do nothing
                                break;
                            default:
                                // If unrecognised account then do nothing.
                                break;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            Error::response("Unable to parse payroll journals.", $e);
        }
    }

    /**
   * Run through the pension bills and extract details of the month's pension amounts
   *
   * @param array $employees An array of QBO Employees, associated by Name
   * @param array $bills An array of QBO Bill entities
   * @param array An array of type \Models\Payslip, passed by reference
   * @return void
   */
    private static function parsePensionBills(array $employees, array $bills, array &$payslips): void
    {
        try {
            foreach ($bills as $bill) {
                // $bill is of type QuickBooksOnline\API\Data\IPPBill with extends IPPTransaction
                if (property_exists($bill, 'Line') && is_array($bill->Line)) {

                    foreach ($bill->Line as $line) {

                        // It's expected that the Description/Memo of the employer pension contribution lines contains
                        // the employee name, optionally with the payroll number in brackets. 
                        // We use this to associate the pension costs with the relevant employee payslip.
                        if (!preg_match('/(\d+)/', $line->Description, $matches)) {                        
                            $name = trim(preg_replace('/\([^)]*\)/', '', $line->Description)); // remove anything in brackets to get employee name
                            // Determine their iris Payroll number
                            if (array_key_exists($name, $employees)) {
                                $payrollNumber = $employees[$name]['payrollNumber'];                            
                            }
                        } else {
                            $payrollNumber = $matches[0];
                            $matches = array_filter($employees, fn($row) => $row['payrollNumber'] === $payrollNumber);
                            if ($matches == null || count($matches) == 0) {
                                continue; // if we can't find an employee with this payroll number then skip this line
                            }
                            $name = $matches[array_key_first($matches)]['name'];
                        }

                        if (empty($name) || !array_key_exists($name, $employees) || empty($payrollNumber)) {
                            continue; // if we can't find an employee name or payroll number then we can't associate this pension contribution with a payslip, so skip it
                        }

                        // Determine the account number and if it is a pension costs account
                        $isPensionContributionAccount = false;
                        if (property_exists($line, 'AccountBasedExpenseLineDetail')
                              && property_exists($line->AccountBasedExpenseLineDetail, 'AccountRef')) {

                            $accountRef = $line->AccountBasedExpenseLineDetail->AccountRef;
                            // AccountRef can be a string or an object of type {"value": number, "name": string}
                            if (property_exists($accountRef, 'value')) {
                                $accountNumber = $accountRef->value;
                            } else {
                                $accountNumber = $accountRef;
                            }
                            $isPensionContributionAccount = $accountNumber == QBO::PENSION_COSTS_ACCOUNT ||
                              $accountNumber == QBO::AUEW_ACCOUNT; // for shop employees
                        }

                        // Is it a pension contribution account?
                        // and is the employee found in the QBO list of employees?
                        if ($isPensionContributionAccount ) {

                            if (!array_key_exists($payrollNumber, $payslips)) {

                                // Create a new payslip
                                $payslips[$payrollNumber] = QBPayrollQueryCtl::createPayslip(
                                    $payrollNumber,
                                    $employees[$name]['quickbooksId'],
                                    $name
                                )->setEmployerPension($line->Amount);

                            } else {
                                $payslips[$payrollNumber]->addToEmployerPension($line->Amount);
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            Error::response("Unable to parse pension bills.", $e);
        }
    }

    /** Helper function to create a new employee Payslip
     * @param $payrollNumber The number associated with the employee in the Iris spreadsheet
     * @param $quickbooksId Id associated with the employee in Quickbooks
     * @param string $name Display name of the employee.
    */
    private static function createPayslip($payrollNumber, $quickbooksId, string $name): Payslip
    {
        return Payslip::getInstance()
        ->setPayrollNumber($payrollNumber)
        ->setQuickbooksId($quickbooksId)
        ->setEmployeeName($name);
    }
}
