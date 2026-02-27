/**
 * Centralized configuration constants for payroll system
 *
 * This file consolidates all payroll-related constants including:
 * - Transaction types
 * - Account mappings
 * - Class mappings
 * - Validation rules
 * - Date formats
 * - UI configuration
 */

// ==========================================
// COMPANY & REALM IDS
// ==========================================

export const COMPANY_IDS = {
  CHARITY_REALM_ID: '123145825016867',
  ENTERPRISES_REALM_ID: '9130350604308576',
} as const;

// ==========================================
// TRANSACTION TYPES
// ==========================================

export const TRANSACTION_TYPES = {
  EMPLOYEE_JOURNAL: 'employee',
  EMPLOYER_NI: 'employer_ni',
  PENSIONS: 'pensions',
  SHOP_PAYROLL: 'shop_payroll',
} as const;

export type TransactionType =
  (typeof TRANSACTION_TYPES)[keyof typeof TRANSACTION_TYPES];

// ==========================================
// ACCOUNT IDS (CHARITY)
// ==========================================

export const CHARITY_ACCOUNTS = {
  AUEW_ACCOUNT: '65',
  EMPLOYEE_PENSION_CONTRIB_ACCOUNT: '66',
  EMPLOYER_NI_ACCOUNT: '95',
  SALARY_SACRIFICE_ACCOUNT: '375',
  NET_PAY_ACCOUNT: '98',
  OTHER_DEDUCTIONS_ACCOUNT: '503',
  PENSION_COSTS_ACCOUNT: '285',
  STAFF_SALARIES_ACCOUNT: '261',
  TAX_ACCOUNT: '256',
  PLEO_ACCOUNT: '429',
} as const;

// ==========================================
// ACCOUNT IDS (ENTERPRISES)
// ==========================================

export const ENTERPRISES_ACCOUNTS = {
  AUKW_INTERCO_ACCOUNT: '80',
  AUEW_PAIDBYPARENT_ACCOUNT: '102',
  AUEW_SALARIES_ACCOUNT: '106',
  AUEW_NI_ACCOUNT: '150',
  AUEW_PENSIONS_ACCOUNT: '139',
} as const;

// ==========================================
// CLASS IDS
// ==========================================

export const CLASSES = {
  ADMIN_CLASS: '1400000000000130710',
  HARROW_ROAD_CLASS: '400000000000618070',
} as const;

// ==========================================
// TRANSACTION DESCRIPTIONS
// ==========================================

export const DESCRIPTIONS = {
  EMPLOYEE_NI: 'Employee NI',
  EMPLOYER_NI: 'Employer NI',
  EMPLOYEE_PENSION_CONT: 'Employee Pension Contribution',
  EMPLOYER_PENSION_CONT: 'Employer Pension Contribution',
  GROSS_SALARY: 'Gross Salary',
  NET_PAY: 'Net Pay',
  OTHER_DEDUCTIONS: 'Other Deductions',
  PAYE: 'PAYE',
  SALARY_SACRIFICE: 'Salary Sacrifice',
  STUDENT_LOAN: 'Student Loan Deductions',
} as const;

// ==========================================
// VALIDATION RULES
// ==========================================

/**
 * Validation thresholds and rules for payroll processing
 */
export const VALIDATION_RULES = {
  /**
   * Minimum threshold for considering an amount as non-zero
   * Used to account for floating point precision issues
   */
  AMOUNT_ZERO_THRESHOLD: 0.005,

  /**
   * Maximum allowed difference for journal balance validation
   * (debits must equal credits within this tolerance)
   */
  BALANCE_TOLERANCE: 0.005,

  /**
   * Minimum amount for a journal line
   * Lines with amounts below this are not created
   */
  MIN_LINE_AMOUNT: 0.01,

  /**
   * Maximum number of lines allowed in a single journal entry
   */
  MAX_JOURNAL_LINES: 100,

  /**
   * Maximum threshold for allocation calculation edge case
   * If calculated allocation is within this amount of the remainder, use the remainder
   * (Original code: line 146 of payroll.service.ts)
   */
  ALLOCATION_REMAINDER_THRESHOLD: 1.0,

  /**
   * Maximum number of retries for failed API calls
   */
  MAX_API_RETRIES: 3,

  /**
   * Delay in milliseconds between API retry attempts
   */
  API_RETRY_DELAY_MS: 1000,
} as const;

// ==========================================
// DATE CONFIGURATION
// ==========================================

/**
 * Date formats used throughout the payroll system
 */
export const DATE_FORMATS = {
  /**
   * Display format for dates in UI
   */
  DISPLAY: 'yyyy-MM-dd',

  /**
   * Format for API requests/responses
   */
  API: 'yyyy-MM-dd',

  /**
   * Format for document numbers
   */
  DOCNUMBER: 'yyyy_MM',

  /**
   * Format for payroll date calculations
   */
  PAYROLL_DATE: 'yyyy-MM-dd',
} as const;

/**
 * Fiscal month mapping
 * Maps fiscal month numbers to calendar month indices (0-based)
 */
export const FISCAL_MONTHS = [10, 11, 12, 1, 2, 3, 4, 5, 6, 7, 8, 9] as const;

/**
 * Day of month for payroll date calculation
 * (Original code: line 356 of payroll.component.ts)
 */
export const PAYROLL_DAY_OF_MONTH = 25;

/**
 * Fiscal months that belong to the next calendar year
 * (months 10, 11, 12 = Oct, Nov, Dec)
 */
export const FISCAL_MONTHS_NEXT_YEAR_THRESHOLD = 10;

// ==========================================
// QUICKBOOKS CONSTRAINTS
// ==========================================

export const QB_CONSTRAINTS = {
  /**
   * Maximum length of DocNumber field in QuickBooks
   */
  DOCNUMBER_MAX_LENGTH: 21,

  /**
   * Maximum length of Description field in QuickBooks
   */
  DESCRIPTION_MAX_LENGTH: 4000,

  /**
   * Prefix for payroll document numbers
   */
  DOCNUMBER_PREFIX: 'Payroll_',
} as const;

// ==========================================
// UI CONFIGURATION
// ==========================================

/**
 * Loading state indices for component
 * Used in loading array: [downloadButton, reloadButton]
 */
export const LOADING_INDICES = {
  DOWNLOAD_BUTTON: 0,
  RELOAD_BUTTON: 1,
} as const;

/**
 * Navigation tab indices
 */
export const NAV_TABS = {
  PAYSLIPS: 1,
  TRANSACTIONS: 2,
  ALLOCATIONS: 3,
} as const;

// ==========================================
// ERROR MESSAGES
// ==========================================

export const ERROR_MESSAGES = {
  INVALID_DATE_FORMAT: 'Invalid date format. Expected format: yyyy-MM-dd',
  INVALID_TRANSACTION_TYPE: 'Invalid transaction type',
  UNBALANCED_JOURNAL: 'Journal entry is not balanced (debits ≠ credits)',
  MISSING_REQUIRED_FIELD: 'Missing required field',
  INVALID_AMOUNT: 'Amount must be a positive number',
  INVALID_FISCAL_MONTH: 'Invalid fiscal month number',
  ALLOCATION_PERCENTAGE_SUM: 'Allocation percentages must sum to 100%',
  API_REQUEST_FAILED: 'API request failed',
  QUICKBOOKS_ERROR: 'QuickBooks error occurred',
  STAFFOLOGY_ERROR: 'Staffology API error occurred',
} as const;

// ==========================================
// SUCCESS MESSAGES
// ==========================================

export const SUCCESS_MESSAGES = {
  JOURNAL_CREATED: 'Journal entry created successfully',
  PAYSLIPS_LOADED: 'Payslips loaded successfully',
  ALLOCATIONS_SAVED: 'Allocations saved successfully',
  TRANSACTION_POSTED: 'Transaction posted to QuickBooks',
} as const;

// ==========================================
// HELPER FUNCTIONS
// ==========================================

/**
 * Check if transaction type is valid
 */
export function isValidTransactionType(type: string): type is TransactionType {
  return Object.values(TRANSACTION_TYPES).includes(type as TransactionType);
}

/**
 * Check if amount is effectively zero (within tolerance)
 */
export function isZeroAmount(amount: number): boolean {
  return Math.abs(amount) <= VALIDATION_RULES.AMOUNT_ZERO_THRESHOLD;
}

/**
 * Check if journal is balanced (debits = credits within tolerance)
 */
export function isBalanced(balance: number): boolean {
  return Math.abs(balance) < VALIDATION_RULES.BALANCE_TOLERANCE;
}

/**
 * Check if allocation remainder should be used instead of calculated amount
 * (Original logic from line 146 of payroll.service.ts)
 */
export function shouldUseRemainder(
  remainder: number,
  calculatedAmount: number,
): boolean {
  return (
    Math.abs(remainder - calculatedAmount) <
    VALIDATION_RULES.ALLOCATION_REMAINDER_THRESHOLD
  );
}

/**
 * Generate payroll document number from date
 *
 * @param payrollDate Date string in yyyy-MM-dd format
 * @param suffix Optional suffix to append
 * @returns Formatted document number (max 21 chars)
 */
export function generateDocNumber(
  payrollDate: string,
  suffix: string = '',
): string {
  const date = new Date(payrollDate);
  if (isNaN(date.getTime())) {
    throw new Error(ERROR_MESSAGES.INVALID_DATE_FORMAT);
  }

  const year = date.getFullYear();
  const month = (date.getMonth() + 1).toString().padStart(2, '0');
  const docNumber = `${QB_CONSTRAINTS.DOCNUMBER_PREFIX}${year}_${month}${suffix}`;

  return docNumber.substring(0, QB_CONSTRAINTS.DOCNUMBER_MAX_LENGTH);
}

/**
 * Calculate payroll date from tax year and fiscal month
 * (Original logic from lines 340-363 of payroll.component.ts)
 *
 * @param taxYear Tax year in format "2023-2024"
 * @param fiscalMonthNumber Fiscal month number (1-12)
 * @returns Date string in yyyy-MM-dd format, or empty string on error
 */
export function calculatePayrollDate(
  taxYear: string,
  fiscalMonthNumber: number,
): string {
  try {
    const monthIndex = FISCAL_MONTHS.indexOf(fiscalMonthNumber as any);
    if (monthIndex === -1) {
      return '';
    }

    let year = Number(taxYear.substring(4));
    if (fiscalMonthNumber >= FISCAL_MONTHS_NEXT_YEAR_THRESHOLD) {
      // Months Oct, Nov, Dec belong to next calendar year
      year++;
    }

    const date = new Date(year, monthIndex, PAYROLL_DAY_OF_MONTH);

    // Format as yyyy-MM-dd
    const yyyy = date.getFullYear();
    const mm = (date.getMonth() + 1).toString().padStart(2, '0');
    const dd = date.getDate().toString().padStart(2, '0');

    return `${yyyy}-${mm}-${dd}`;
  } catch (error) {
    console.error('Error occurred calculating payroll date:', error);
    return '';
  }
}

/**
 * Get account ID for charity by key
 */
export function getCharityAccount(
  accountKey: keyof typeof CHARITY_ACCOUNTS,
): string {
  return CHARITY_ACCOUNTS[accountKey];
}

/**
 * Get account ID for enterprises by key
 */
export function getEnterprisesAccount(
  accountKey: keyof typeof ENTERPRISES_ACCOUNTS,
): string {
  return ENTERPRISES_ACCOUNTS[accountKey];
}

/**
 * Get class ID by key
 */
export function getClass(classKey: keyof typeof CLASSES): string {
  return CLASSES[classKey];
}

/**
 * Get description by key
 */
export function getDescription(
  descriptionKey: keyof typeof DESCRIPTIONS,
): string {
  return DESCRIPTIONS[descriptionKey];
}
