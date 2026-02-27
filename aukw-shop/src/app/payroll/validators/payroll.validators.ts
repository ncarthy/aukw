/**
 * Validators for payroll forms and data
 *
 * Provides:
 * - Angular form validators
 * - Standalone validation functions
 * - Custom validation rules for payroll-specific fields
 */

import {
  AbstractControl,
  ValidationErrors,
  ValidatorFn,
  FormGroup,
} from '@angular/forms';
import {
  VALIDATION_RULES,
  isValidTransactionType,
  isBalanced,
} from '../config/payroll.constants';

// ==========================================
// DATE VALIDATORS
// ==========================================

/**
 * Validator for payroll date format (yyyy-MM-dd)
 */
export function payrollDateValidator(): ValidatorFn {
  return (control: AbstractControl): ValidationErrors | null => {
    if (!control.value) {
      return null; // Don't validate empty values (use Validators.required for that)
    }

    const dateStr = control.value;

    // Check format with regex
    const dateRegex = /^\d{4}-\d{2}-\d{2}$/;
    if (!dateRegex.test(dateStr)) {
      return {
        invalidDateFormat: {
          value: dateStr,
          expectedFormat: 'yyyy-MM-dd',
        },
      };
    }

    // Check if it's a valid date
    const date = new Date(dateStr);
    if (isNaN(date.getTime())) {
      return {
        invalidDate: {
          value: dateStr,
        },
      };
    }

    // Check that the string representation matches (catches invalid dates like 2024-02-31)
    const year = date.getFullYear();
    const month = (date.getMonth() + 1).toString().padStart(2, '0');
    const day = date.getDate().toString().padStart(2, '0');
    const reconstructed = `${year}-${month}-${day}`;

    if (dateStr !== reconstructed) {
      return {
        invalidDate: {
          value: dateStr,
        },
      };
    }

    return null;
  };
}

/**
 * Validator for future dates
 */
export function notFutureDateValidator(): ValidatorFn {
  return (control: AbstractControl): ValidationErrors | null => {
    if (!control.value) {
      return null;
    }

    const date = new Date(control.value);
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    if (date > today) {
      return {
        futureDate: {
          value: control.value,
          today: today.toISOString().split('T')[0],
        },
      };
    }

    return null;
  };
}

/**
 * Validator for date range (start date must be before end date)
 * Use as a form group validator
 */
export function dateRangeValidator(
  startDateField: string,
  endDateField: string,
): ValidatorFn {
  return (formGroup: AbstractControl): ValidationErrors | null => {
    if (!(formGroup instanceof FormGroup)) {
      return null;
    }

    const startControl = formGroup.get(startDateField);
    const endControl = formGroup.get(endDateField);

    if (!startControl || !endControl) {
      return null;
    }

    const startDate = startControl.value;
    const endDate = endControl.value;

    if (!startDate || !endDate) {
      return null;
    }

    const start = new Date(startDate);
    const end = new Date(endDate);

    if (start > end) {
      return {
        invalidDateRange: {
          startDate,
          endDate,
        },
      };
    }

    return null;
  };
}

// ==========================================
// AMOUNT VALIDATORS
// ==========================================

/**
 * Validator for positive amounts
 */
export function positiveAmountValidator(): ValidatorFn {
  return (control: AbstractControl): ValidationErrors | null => {
    if (
      control.value === null ||
      control.value === undefined ||
      control.value === ''
    ) {
      return null;
    }

    const amount = Number(control.value);

    if (isNaN(amount)) {
      return {
        invalidAmount: {
          value: control.value,
        },
      };
    }

    if (amount < 0) {
      return {
        negativeAmount: {
          value: amount,
        },
      };
    }

    if (amount < VALIDATION_RULES.MIN_LINE_AMOUNT) {
      return {
        amountTooSmall: {
          value: amount,
          minimum: VALIDATION_RULES.MIN_LINE_AMOUNT,
        },
      };
    }

    return null;
  };
}

/**
 * Validator for numeric values
 */
export function numericValidator(): ValidatorFn {
  return (control: AbstractControl): ValidationErrors | null => {
    if (
      control.value === null ||
      control.value === undefined ||
      control.value === ''
    ) {
      return null;
    }

    const value = Number(control.value);

    if (isNaN(value) || !isFinite(value)) {
      return {
        invalidNumber: {
          value: control.value,
        },
      };
    }

    return null;
  };
}

/**
 * Validator for percentage values (0-100)
 */
export function percentageValidator(): ValidatorFn {
  return (control: AbstractControl): ValidationErrors | null => {
    if (
      control.value === null ||
      control.value === undefined ||
      control.value === ''
    ) {
      return null;
    }

    const value = Number(control.value);

    if (isNaN(value)) {
      return {
        invalidPercentage: {
          value: control.value,
        },
      };
    }

    if (value < 0 || value > 100) {
      return {
        percentageOutOfRange: {
          value,
          min: 0,
          max: 100,
        },
      };
    }

    return null;
  };
}

// ==========================================
// JOURNAL VALIDATORS
// ==========================================

/**
 * Interface for journal line
 */
export interface JournalLine {
  amount: number;
  postingType: 'Debit' | 'Credit';
  description?: string;
  account?: string;
  class?: string;
}

/**
 * Validate journal lines (debits = credits)
 */
export function validateJournalLines(
  lines: JournalLine[],
): ValidationErrors | null {
  if (!lines || lines.length === 0) {
    return {
      emptyJournal: {
        message: 'Journal must have at least one line',
      },
    };
  }

  if (lines.length > VALIDATION_RULES.MAX_JOURNAL_LINES) {
    return {
      tooManyLines: {
        count: lines.length,
        maximum: VALIDATION_RULES.MAX_JOURNAL_LINES,
      },
    };
  }

  let debits = 0;
  let credits = 0;

  for (const line of lines) {
    if (!line.amount || isNaN(line.amount)) {
      return {
        invalidLineAmount: {
          line,
        },
      };
    }

    if (line.postingType === 'Debit') {
      debits += line.amount;
    } else if (line.postingType === 'Credit') {
      credits += line.amount;
    } else {
      return {
        invalidPostingType: {
          line,
          postingType: line.postingType,
        },
      };
    }
  }

  const balance = debits - credits;

  if (!isBalanced(balance)) {
    return {
      unbalancedJournal: {
        debits,
        credits,
        balance,
        tolerance: VALIDATION_RULES.BALANCE_TOLERANCE,
      },
    };
  }

  return null;
}

/**
 * Angular form validator for journal lines
 */
export function journalLinesValidator(): ValidatorFn {
  return (control: AbstractControl): ValidationErrors | null => {
    if (!control.value) {
      return null;
    }

    return validateJournalLines(control.value);
  };
}

// ==========================================
// PAYROLL-SPECIFIC VALIDATORS
// ==========================================

/**
 * Validator for transaction type
 */
export function transactionTypeValidator(): ValidatorFn {
  return (control: AbstractControl): ValidationErrors | null => {
    if (!control.value) {
      return null;
    }

    if (!isValidTransactionType(control.value)) {
      return {
        invalidTransactionType: {
          value: control.value,
        },
      };
    }

    return null;
  };
}

/**
 * Validator for employee ID
 */
export function employeeIdValidator(): ValidatorFn {
  return (control: AbstractControl): ValidationErrors | null => {
    if (!control.value) {
      return null;
    }

    const value = control.value;

    // Must be numeric
    const numValue = Number(value);
    if (isNaN(numValue) || !isFinite(numValue)) {
      return {
        invalidEmployeeId: {
          value,
          message: 'Employee ID must be numeric',
        },
      };
    }

    // Must be positive
    if (numValue <= 0) {
      return {
        invalidEmployeeId: {
          value,
          message: 'Employee ID must be positive',
        },
      };
    }

    return null;
  };
}

/**
 * Validate pay period selection
 */
export function validatePayPeriod(
  taxYear: string,
  fiscalMonth: number,
): ValidationErrors | null {
  if (!taxYear) {
    return {
      missingTaxYear: {
        message: 'Tax year is required',
      },
    };
  }

  if (!fiscalMonth) {
    return {
      missingFiscalMonth: {
        message: 'Fiscal month is required',
      },
    };
  }

  // Validate tax year format (yyyy-yyyy)
  const taxYearRegex = /^\d{4}-\d{4}$/;
  if (!taxYearRegex.test(taxYear)) {
    return {
      invalidTaxYear: {
        value: taxYear,
        expectedFormat: 'yyyy-yyyy',
      },
    };
  }

  // Validate fiscal month is between 1 and 12
  if (fiscalMonth < 1 || fiscalMonth > 12) {
    return {
      invalidFiscalMonth: {
        value: fiscalMonth,
        min: 1,
        max: 12,
      },
    };
  }

  return null;
}

/**
 * Angular form validator for pay period
 */
export function payPeriodValidator(
  taxYearField: string,
  fiscalMonthField: string,
): ValidatorFn {
  return (formGroup: AbstractControl): ValidationErrors | null => {
    if (!(formGroup instanceof FormGroup)) {
      return null;
    }

    const taxYearControl = formGroup.get(taxYearField);
    const fiscalMonthControl = formGroup.get(fiscalMonthField);

    if (!taxYearControl || !fiscalMonthControl) {
      return null;
    }

    const taxYear = taxYearControl.value;
    const fiscalMonth = fiscalMonthControl.value;

    return validatePayPeriod(taxYear, fiscalMonth);
  };
}

// ==========================================
// ALLOCATION VALIDATORS
// ==========================================

/**
 * Interface for allocation
 */
export interface Allocation {
  percentage: number;
  class?: string;
  account?: string;
}

/**
 * Validate allocations sum to 100%
 */
export function validateAllocations(
  allocations: Allocation[],
): ValidationErrors | null {
  if (!allocations || allocations.length === 0) {
    return {
      emptyAllocations: {
        message: 'At least one allocation is required',
      },
    };
  }

  let sum = 0;
  for (let i = 0; i < allocations.length; i++) {
    const allocation = allocations[i];

    if (!allocation.percentage || isNaN(allocation.percentage)) {
      return {
        invalidAllocationPercentage: {
          index: i,
          value: allocation.percentage,
        },
      };
    }

    const percentage = Number(allocation.percentage);

    if (percentage < 0 || percentage > 100) {
      return {
        allocationPercentageOutOfRange: {
          index: i,
          value: percentage,
          min: 0,
          max: 100,
        },
      };
    }

    sum += percentage;
  }

  // Check sum is 100% (within tolerance)
  if (Math.abs(sum - 100) > VALIDATION_RULES.AMOUNT_ZERO_THRESHOLD) {
    return {
      allocationsDoNotSumTo100: {
        sum,
        expected: 100,
        difference: sum - 100,
      },
    };
  }

  return null;
}

/**
 * Angular form validator for allocations array
 */
export function allocationsValidator(): ValidatorFn {
  return (control: AbstractControl): ValidationErrors | null => {
    if (!control.value) {
      return null;
    }

    return validateAllocations(control.value);
  };
}

// ==========================================
// HELPER FUNCTIONS
// ==========================================

/**
 * Get user-friendly error message from validation errors
 */
export function getValidationErrorMessage(errors: ValidationErrors): string {
  if (!errors) {
    return '';
  }

  // Date errors
  if (errors['invalidDateFormat']) {
    return `Invalid date format. Expected: ${errors['invalidDateFormat'].expectedFormat}`;
  }
  if (errors['invalidDate']) {
    return 'Invalid date';
  }
  if (errors['futureDate']) {
    return 'Date cannot be in the future';
  }
  if (errors['invalidDateRange']) {
    return 'Start date must be before end date';
  }

  // Amount errors
  if (errors['invalidAmount']) {
    return 'Invalid amount';
  }
  if (errors['negativeAmount']) {
    return 'Amount cannot be negative';
  }
  if (errors['amountTooSmall']) {
    return `Amount must be at least ${errors['amountTooSmall'].minimum}`;
  }
  if (errors['invalidNumber']) {
    return 'Must be a valid number';
  }

  // Percentage errors
  if (errors['invalidPercentage']) {
    return 'Invalid percentage';
  }
  if (errors['percentageOutOfRange']) {
    return 'Percentage must be between 0 and 100';
  }

  // Journal errors
  if (errors['emptyJournal']) {
    return 'Journal must have at least one line';
  }
  if (errors['tooManyLines']) {
    return `Too many journal lines (maximum: ${errors['tooManyLines'].maximum})`;
  }
  if (errors['invalidLineAmount']) {
    return 'Invalid line amount';
  }
  if (errors['invalidPostingType']) {
    return 'Invalid posting type';
  }
  if (errors['unbalancedJournal']) {
    const err = errors['unbalancedJournal'];
    return `Journal is not balanced. Debits: ${err.debits}, Credits: ${err.credits}, Difference: ${err.balance}`;
  }

  // Payroll-specific errors
  if (errors['invalidTransactionType']) {
    return 'Invalid transaction type';
  }
  if (errors['invalidEmployeeId']) {
    return errors['invalidEmployeeId'].message || 'Invalid employee ID';
  }
  if (errors['missingTaxYear']) {
    return 'Tax year is required';
  }
  if (errors['missingFiscalMonth']) {
    return 'Fiscal month is required';
  }
  if (errors['invalidTaxYear']) {
    return 'Invalid tax year format';
  }
  if (errors['invalidFiscalMonth']) {
    return 'Invalid fiscal month';
  }

  // Allocation errors
  if (errors['emptyAllocations']) {
    return 'At least one allocation is required';
  }
  if (errors['invalidAllocationPercentage']) {
    return `Invalid percentage for allocation ${errors['invalidAllocationPercentage'].index + 1}`;
  }
  if (errors['allocationPercentageOutOfRange']) {
    return `Percentage for allocation ${errors['allocationPercentageOutOfRange'].index + 1} must be between 0 and 100`;
  }
  if (errors['allocationsDoNotSumTo100']) {
    const err = errors['allocationsDoNotSumTo100'];
    return `Allocations must sum to 100% (current sum: ${err.sum}%)`;
  }

  // Angular built-in errors
  if (errors['required']) {
    return 'This field is required';
  }
  if (errors['min']) {
    return `Value must be at least ${errors['min'].min}`;
  }
  if (errors['max']) {
    return `Value must be at most ${errors['max'].max}`;
  }
  if (errors['email']) {
    return 'Invalid email address';
  }

  // Unknown error
  return 'Validation error';
}
