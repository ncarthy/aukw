<?php

namespace Models;

use Core\QuickbooksConstants as QBO;
use QuickBooksOnline\API\Facades\JournalEntry;

/**
 * Factory class that all creation of QB Payroll jopurnal entries
 *
 * @category Model
 */
class QuickbooksPayrollJournal extends QuickbooksJournal
{
    /**
     * The ID of the employee in Quickbooks
     *
     * @var string
     */
    protected string $quickbooksEmployeeId;

    /**
     * The transaction date of the Journal entry
     *
     * @var string
     */
    protected string $TxnDate;

    /**
     * The Reference number for the transaction. Does not have to be unique.
     *
     * @var string
     */
    protected string $DocNumber;

    /**
     * The total amount of salary, split into allocations
     *
     * @var Array
     */
    protected array $grossSalary;

    /**
     * The amount actually paid to the employee.
     *
     * @var float
     */
    protected float $netSalary;

    /**
     * The amount of income tax deducted from the employee gross salary.
     *
     * @var float
     */
    protected float $paye;

    /**
     * The amount of NI deducted from the employee gross salary.
     *
     * @var float
     */
    protected float $employeeNI;

    /**
     * The amount of NI paid by the charity.
     *
     * @var float
     */
    protected float $employerNI;

    /**
     * The student loan repayment deducted from the employee gross salary.
     *
     * @var float
     */
    protected float $studentLoan;

    /**
     * The total amount of any other deduction from the employee gross salary.
     *
     * @var float
     */
    protected float $otherDeduction;

    /**
     * The amount of extra pension contribution made by the employee and deducted from gross salary.
     *
     * @var float
     */
    protected float $employeePensionContribution;

    /**
     * The amount of extra pension contribution made by the employee via a salary sacrifice.
     *
     * @var float
     */
    protected float $salarySacrifice;

    /**
     * Employee Id (QBO) setter.
     */
    public function setQuickbooksEmployeeId(string $quickbooksEmployeeId)
    {
        $this->quickbooksEmployeeId = $quickbooksEmployeeId;
        return $this;
    }

    /**
     * Gross Salary setter.
     */
    public function setGrossSalary(array $grossSalary)
    {
        $this->grossSalary = $grossSalary;
        return $this;
    }

    /**
     * Net Salary setter.
     */
    public function setNetSalary(float $netSalary)
    {
        $this->netSalary = $netSalary;
        return $this;
    }
    /**
     * PAYE (income tax) setter.
     */
    public function setPAYE(float $paye)
    {
        $this->paye = $paye;
        return $this;
    }

    /**
     * Employee NI setter.
     */
    public function setEmployeeNI(float $employeeNI)
    {
        $this->employeeNI = $employeeNI;
        return $this;
    }

    /**
     * Other Deductions setter.
     */
    public function setOtherDeduction(float $otherDeduction)
    {
        $this->otherDeduction = $otherDeduction;
        return $this;
    }
    /**
     * Salary Sacrifice setter.
     */
    public function setSalarySacrifice(float $salarySacrifice)
    {
        $this->salarySacrifice = $salarySacrifice;
        return $this;
    }
    /**
     * Student Loan setter.
     */
    public function setStudentLoan(float $studentLoan)
    {
        $this->studentLoan = $studentLoan;
        return $this;
    }

    /**
     * Pension contribution from employee for the month setter.
     */
    public function setEmployeePension(float $employeePensionContribution)
    {
        $this->employeePensionContribution = $employeePensionContribution;
        return $this;
    }

    /**
     * Constructor
     */
    protected function __construct()
    {
    }

    /**
     * Static constructor / factory
     */
    public static function getInstance()
    {
        return new self();
    }

    /**
     * Create a general journal in QBO using the new factory approach
     *
     * @param bool $useFactory Whether to use the new factory method (default: true)
     * @return array|false On success return an array with details of the new object. On failure return 'false'.
     */
    public function create_employee_journal(bool $useFactory = true): array|false
    {
        if ($useFactory) {
            // New approach: use factory
            return $this->createJournalEntry('employee', [
                'quickbooksEmployeeId' => $this->quickbooksEmployeeId,
                'grossSalary' => $this->grossSalary,
                'netSalary' => $this->netSalary,
                'paye' => $this->paye,
                'employeeNI' => $this->employeeNI,
                'salarySacrifice' => $this->salarySacrifice,
                'employeePensionContribution' => $this->employeePensionContribution,
                'studentLoan' => $this->studentLoan,
                'otherDeduction' => $this->otherDeduction,
            ]);
        }

        // Old approach: manual line creation (kept for backward compatibility)
        $payrolljournal = array(
            "TxnDate" => $this->TxnDate,
            "DocNumber" => $this->DocNumber,
            "Line" => [],
            "TotalAmt" => 0
        );

        // For each line below it will only add the respective line if amount != 0

        foreach ($this->grossSalary as $grossSalaryAllocation) {
            //&$line_array, $description, $amount, $emploee, $class, $account)
            $this->payrolljournal_line(
                $payrolljournal['Line'],
                QBO::GROSS_SALARY_DESCRIPTION,
                $grossSalaryAllocation->amount,
                $this->quickbooksEmployeeId,
                $grossSalaryAllocation->class,
                $grossSalaryAllocation->account == QBO::AUEW_ACCOUNT ? QBO::AUEW_ACCOUNT : QBO::STAFF_SALARIES_ACCOUNT
            );
        }

        $this->payrolljournal_line(
            $payrolljournal['Line'],
            QBO::PAYE_DESCRIPTION,
            $this->paye,
            $this->quickbooksEmployeeId,
            QBO::ADMIN_CLASS,
            QBO::TAX_ACCOUNT
        );

        $this->payrolljournal_line(
            $payrolljournal['Line'],
            QBO::EMPLOYEE_NI_DESCRIPTION,
            $this->employeeNI,
            $this->quickbooksEmployeeId,
            QBO::ADMIN_CLASS,
            QBO::TAX_ACCOUNT
        );

        $this->payrolljournal_line(
            $payrolljournal['Line'],
            QBO::SALARY_SACRIFICE_DESCRIPTION,
            $this->salarySacrifice,
            $this->quickbooksEmployeeId,
            QBO::ADMIN_CLASS,
            QBO::SALARY_SACRIFICE_ACCOUNT
        );

        $this->payrolljournal_line(
            $payrolljournal['Line'],
            QBO::EMPLOYEE_PENSION_CONT_DESCRIPTION,
            $this->employeePensionContribution,
            $this->quickbooksEmployeeId,
            QBO::ADMIN_CLASS,
            QBO::EMPLOYEE_PENSION_CONTRIB_ACCOUNT
        );

        $this->payrolljournal_line(
            $payrolljournal['Line'],
            QBO::OTHER_DEDUCTIONS_DESCRIPTION,
            $this->otherDeduction,
            $this->quickbooksEmployeeId,
            QBO::ADMIN_CLASS,
            QBO::OTHER_DEDUCTIONS_ACCOUNT
        );

        $this->payrolljournal_line(
            $payrolljournal['Line'],
            QBO::STUDENT_LOAN_DESCRIPTION,
            $this->studentLoan,
            $this->quickbooksEmployeeId,
            QBO::ADMIN_CLASS,
            QBO::TAX_ACCOUNT
        );

        $this->payrolljournal_line(
            $payrolljournal['Line'],
            QBO::NET_PAY_DESCRIPTION,
            $this->netSalary,
            $this->quickbooksEmployeeId,
            QBO::ADMIN_CLASS,
            QBO::NET_PAY_ACCOUNT
        );


        $theResourceObj = JournalEntry::create($payrolljournal);

        $auth = new QuickbooksAuth();
        $dataService = $auth->prepare($this->getrealmId());

        $resultingObj = $dataService->Add($theResourceObj);

        $error = $dataService->getLastError();
        if ($error) {
            throw new \Exception("The QBO Response message is: " . $error->getResponseBody() . "\n");
        } else {
            return array(
                "id" => $resultingObj->Id,
                "date" => $this->TxnDate,
                "label" => $this->DocNumber
            );
        }
    }

    /**
     * Check the provided values make sense. Is transaction in balance?
     *
     * @return bool 'True' if the transaction is in balance, 'false' otherwise.
     */
    public function validate(): bool
    {

        if (!$this->grossSalary || !count($this->grossSalary)) {
            return false;
        }

        // Sum of Gross Salary
        $grossSalary = 0;
        foreach ($this->grossSalary as $salaryAllocation) {
            $grossSalary += $salaryAllocation->amount;
        }

        $balance = $grossSalary + $this->paye + $this->employeeNI + $this->otherDeduction
                        + $this->employeePensionContribution
                        + $this->salarySacrifice + $this->studentLoan + $this->netSalary;

        if (abs($balance) >= 0.005) {
            return false;
        }

        return true;
    }

}
