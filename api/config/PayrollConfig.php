<?php

namespace Config;

/**
 * Centralized configuration for payroll system
 *
 * This file consolidates all payroll-related constants including:
 * - QuickBooks account IDs, class IDs, company file IDs
 * - Date formats and validation rules
 * - Transaction type definitions
 * - Validation thresholds
 *
 * @category Config
 */
class PayrollConfig
{
    // ==========================================
    // COMPANY & REALM IDS
    // ==========================================

    public const CHARITY_REALM_ID = "123145825016867";
    public const ENTERPRISES_REALM_ID = "9130350604308576";

    // ==========================================
    // CHARITY ACCOUNT IDS
    // ==========================================

    public const CHARITY_ACCOUNTS = [
        'AUEW_ACCOUNT' => "65",
        'EMPLOYEE_PENSION_CONTRIB_ACCOUNT' => "66",
        'EMPLOYER_NI_ACCOUNT' => "95",
        'SALARY_SACRIFICE_ACCOUNT' => "375",
        'NET_PAY_ACCOUNT' => "98",
        'OTHER_DEDUCTIONS_ACCOUNT' => "503",
        'PENSION_COSTS_ACCOUNT' => "285",
        'STAFF_SALARIES_ACCOUNT' => "261",
        'TAX_ACCOUNT' => "256",
        'PLEO_ACCOUNT' => "429",
    ];

    // ==========================================
    // ENTERPRISES ACCOUNT IDS
    // ==========================================

    public const ENTERPRISES_ACCOUNTS = [
        'AUKW_INTERCO_ACCOUNT' => "80",
        'AUEW_PAIDBYPARENT_ACCOUNT' => "102",
        'AUEW_SALARIES_ACCOUNT' => "106",
        'AUEW_NI_ACCOUNT' => "150",
        'AUEW_PENSIONS_ACCOUNT' => "139",
    ];

    // ==========================================
    // CLASS IDS
    // ==========================================

    public const CLASSES = [
        'ADMIN_CLASS' => "1400000000000130710",
        'HARROW_ROAD_CLASS' => "400000000000618070",
    ];

    // ==========================================
    // TRANSACTION DESCRIPTIONS
    // ==========================================

    public const DESCRIPTIONS = [
        'EMPLOYEE_NI' => "Employee NI",
        'EMPLOYER_NI' => "Employer NI",
        'EMPLOYEE_PENSION_CONT' => "Employee Pension Contribution",
        'EMPLOYER_PENSION_CONT' => "Employer Pension Contribution",
        'GROSS_SALARY' => "Gross Salary",
        'NET_PAY' => "Net Pay",
        'OTHER_DEDUCTIONS' => "Other Deductions",
        'PAYE' => "PAYE",
        'SALARY_SACRIFICE' => "Salary Sacrifice",
        'STUDENT_LOAN' => "Student Loan Deductions",
    ];

    // ==========================================
    // TAX & VENDOR CONSTANTS
    // ==========================================

    public const NOVAT_TAX_CODE = "20";
    public const LEGAL_AND_GENERAL_VENDOR = "357";

    public const TAX_CODES = [
        'ZERO_RATED' => [
            'value' => 4,
            'rate' => 0
        ],
        'STANDARD_RATED' => [
            'value' => 2,
            'rate' => 20
        ],
        'ZERO_RATED_PURCHASES' => [
            'value' => 8
        ],
        'STANDARD_RATED_PURCHASES' => [
            'value' => 4
        ],
    ];

    // ==========================================
    // QUICKBOOKS CONSTRAINTS
    // ==========================================

    public const QBO_DOCNUMBER_MAX_LENGTH = 21;
    public const QBO_DESCRIPTION_MAX_LENGTH = 4000;

    // ==========================================
    // DATE FORMATS
    // ==========================================

    public const DATE_FORMAT_DISPLAY = 'Y-m-d';
    public const DATE_FORMAT_DOCNUMBER = 'Y_m';
    public const DATE_FORMAT_API = 'Y-m-d';

    // ==========================================
    // VALIDATION RULES
    // ==========================================

    /**
     * Minimum threshold for considering an amount as non-zero
     * Used to account for floating point precision issues
     */
    public const AMOUNT_ZERO_THRESHOLD = 0.005;

    /**
     * Maximum allowed difference for journal balance validation
     * (debits must equal credits within this tolerance)
     */
    public const BALANCE_TOLERANCE = 0.005;

    /**
     * Minimum amount for a journal line
     * Lines with amounts below this are not created
     */
    public const MIN_LINE_AMOUNT = 0.01;

    /**
     * Maximum number of lines allowed in a single journal entry
     */
    public const MAX_JOURNAL_LINES = 100;

    // ==========================================
    // TRANSACTION TYPES
    // ==========================================

    public const TRANSACTION_TYPES = [
        'EMPLOYEE_JOURNAL' => 'employee',
        'EMPLOYER_NI' => 'employer_ni',
        'PENSIONS' => 'pensions',
        'SHOP_PAYROLL' => 'shop_payroll',
    ];

    // ==========================================
    // HELPER METHODS
    // ==========================================

    /**
     * Get account ID for charity by key
     *
     * @param string $accountKey The account key (e.g., 'AUEW_ACCOUNT')
     * @return string The account ID
     * @throws \InvalidArgumentException If account key is invalid
     */
    public static function getCharityAccount(string $accountKey): string
    {
        if (!isset(self::CHARITY_ACCOUNTS[$accountKey])) {
            throw new \InvalidArgumentException("Invalid charity account key: {$accountKey}");
        }
        return self::CHARITY_ACCOUNTS[$accountKey];
    }

    /**
     * Get account ID for enterprises by key
     *
     * @param string $accountKey The account key (e.g., 'AUKW_INTERCO_ACCOUNT')
     * @return string The account ID
     * @throws \InvalidArgumentException If account key is invalid
     */
    public static function getEnterprisesAccount(string $accountKey): string
    {
        if (!isset(self::ENTERPRISES_ACCOUNTS[$accountKey])) {
            throw new \InvalidArgumentException("Invalid enterprises account key: {$accountKey}");
        }
        return self::ENTERPRISES_ACCOUNTS[$accountKey];
    }

    /**
     * Get class ID by key
     *
     * @param string $classKey The class key (e.g., 'ADMIN_CLASS')
     * @return string The class ID
     * @throws \InvalidArgumentException If class key is invalid
     */
    public static function getClass(string $classKey): string
    {
        if (!isset(self::CLASSES[$classKey])) {
            throw new \InvalidArgumentException("Invalid class key: {$classKey}");
        }
        return self::CLASSES[$classKey];
    }

    /**
     * Get description by key
     *
     * @param string $descriptionKey The description key (e.g., 'GROSS_SALARY')
     * @return string The description text
     * @throws \InvalidArgumentException If description key is invalid
     */
    public static function getDescription(string $descriptionKey): string
    {
        if (!isset(self::DESCRIPTIONS[$descriptionKey])) {
            throw new \InvalidArgumentException("Invalid description key: {$descriptionKey}");
        }
        return self::DESCRIPTIONS[$descriptionKey];
    }

    /**
     * Generate payroll document number from date
     *
     * @param string $payrollDate Date in Y-m-d format
     * @param string $suffix Optional suffix to append
     * @return string Formatted document number (max 21 chars)
     * @throws \InvalidArgumentException If date format is invalid
     */
    public static function generateDocNumber(string $payrollDate, string $suffix = ''): string
    {
        $d = \DateTime::createFromFormat(self::DATE_FORMAT_API, $payrollDate);
        if ($d === false) {
            throw new \InvalidArgumentException("Invalid date format: {$payrollDate}. Expected format: " . self::DATE_FORMAT_API);
        }

        return substr('Payroll_' . $d->format(self::DATE_FORMAT_DOCNUMBER) . $suffix, 0, self::QBO_DOCNUMBER_MAX_LENGTH);
    }

    /**
     * Determine if an amount is effectively zero (within tolerance)
     *
     * @param float $amount The amount to check
     * @return bool True if amount is within zero threshold
     */
    public static function isZeroAmount(float $amount): bool
    {
        return abs($amount) <= self::AMOUNT_ZERO_THRESHOLD;
    }

    /**
     * Determine if journal is balanced (debits = credits within tolerance)
     *
     * @param float $balance The calculated balance (debits - credits)
     * @return bool True if journal is balanced
     */
    public static function isBalanced(float $balance): bool
    {
        return abs($balance) < self::BALANCE_TOLERANCE;
    }

    /**
     * Determine correct NI account based on employee type
     *
     * @param bool $isShopEmployee True if employee works for the shop
     * @return string The account ID
     */
    public static function getNIAccountForEmployee(bool $isShopEmployee): string
    {
        if ($isShopEmployee) {
            return self::CHARITY_ACCOUNTS['AUEW_ACCOUNT'];
        } else {
            return self::CHARITY_ACCOUNTS['EMPLOYER_NI_ACCOUNT'];
        }
    }

    /**
     * Validate transaction type
     *
     * @param string $transactionType The transaction type to validate
     * @return bool True if valid transaction type
     */
    public static function isValidTransactionType(string $transactionType): bool
    {
        return in_array($transactionType, self::TRANSACTION_TYPES, true);
    }
}
