<?php

namespace Core;

use DateTime;

/**
 * A static class that holds application-level constants used in Quickbooks Online processes.
 *
 * @category Core
 */
class QuickbooksConstants
{
    /** The maximum length of DocNumber, as enforced by QBO */
    public const QBO_DOCNUMBER_MAX_LENGTH = 21;

    // Charity Constants
    public const CHARITY_REALMID = "123145825016867";

    // if any account number clashes with AUEW account numbers then check parsePayrollJournals() carefully
    public const AUEW_ACCOUNT = "65";
    public const EMPLOYEE_PENSION_CONTRIB_ACCOUNT = "66";
    public const EMPLOYER_NI_ACCOUNT = "95";
    public const SALARY_SACRIFICE_ACCOUNT = "375";
    public const NET_PAY_ACCOUNT = "98";
    public const OTHER_DEDUCTIONS_ACCOUNT = "503";
    public const PENSION_COSTS_ACCOUNT = "285";
    public const STAFF_SALARIES_ACCOUNT = "261";
    public const TAX_ACCOUNT = "256";
    public const PLEO_ACCOUNT = "429";

    public const ADMIN_CLASS = "1400000000000130710";

    public const EMPLOYEE_NI_DESCRIPTION = "Employee NI";
    public const EMPLOYER_NI_DESCRIPTION = "Employer NI";
    public const EMPLOYEE_PENSION_CONT_DESCRIPTION = "Employee Pension Contribution";
    public const EMPLOYER_PENSION_CONT_DESCRIPTION = "Employer Pension Contribution";
    public const GROSS_SALARY_DESCRIPTION = "Gross Salary";
    public const NET_PAY_DESCRIPTION = "Net Pay";
    public const OTHER_DEDUCTIONS_DESCRIPTION = "Other Deductions";
    public const PAYE_DESCRIPTION = "PAYE";
    public const SALARY_SACRIFICE_DESCRIPTION = "Salary Sacrifice";
    public const STUDENT_LOAN_DESCRIPTION = "Student Loan Deductions";

    public const NOVAT_TAX_CODE = "20";

    public const LEGAL_AND_GENERAL_VENDOR = "357";

    /**
     * Determine the correct NI account by considering whether the employee works for the shop or not
     * @param bool $isShopEmployee 'true' if the employee works for the Shop, not the Charity
     * @return int The QuickBooks ID of an account
     */
    public static function payrollAccountFromEmployeeStatus(bool $isShopEmployee): int
    {
        if ($isShopEmployee) {
            return QuickbooksConstants::AUEW_ACCOUNT;
        } else {
            return QuickbooksConstants::EMPLOYER_NI_ACCOUNT;
        }

    }

    // Enterprises Constants
    public const ENTERPRISES_REALMID = "9130350604308576";

    public const AUKW_INTERCO_ACCOUNT = "80";
    public const AUEW_PAIDBYPARENT_ACCOUNT = "102";
    public const AUEW_SALARIES_ACCOUNT = "106";
    public const AUEW_NI_ACCOUNT = "150";
    public const AUEW_PENSIONS_ACCOUNT = "139";

    public const HARROW_ROAD_CLASS = "400000000000618070";

    public static $zero_rated_taxcode = array(
      "value" => 4,
      "rate" => 0
    );
    public static $standard_rated_taxcode = array(
      "value" => 2,
      "rate" => 20
    );
    public static $zero_rated_purchases_taxrate = array(
      "value" => 8
    );
    public static $standard_rated_purchases_taxrate = array(
      "value" => 4
    );

    /**
     * Helper function to regularise the DocNumber for payroll transactions. The
     * return value is limited to a maximum of 21 characters
     * @param string $payrollDate A string representation of the date of the
     * payroll in 'YYYY-mm-dd' format.
     * @param string $suffix A string to place at the end of the calculated DocNumber (Optional)
     * @return string A string, limited in length to 21 characters
     * @throws \InvalidArgumentException If the date format is invalid
     */
    public static function payrollDocNumber(
        string $payrollDate,
        string $suffix = ''
    ): string {

        $d = DateTime::createFromFormat('Y-m-d', $payrollDate);
        if ($d === false) {
            throw new \InvalidArgumentException("Invalid date format: $payrollDate");
        }
        return substr('Payroll_' . $d->format('Y_m').$suffix, 0, QuickbooksConstants::QBO_DOCNUMBER_MAX_LENGTH);
    }
}
