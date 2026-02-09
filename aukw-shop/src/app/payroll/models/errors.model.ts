/**
 * Error models and handlers for payroll system
 *
 * Provides:
 * - Standardized error interfaces
 * - Error handler class with retry logic
 * - Error code enums for consistent identification
 * - HTTP error mapping
 */

import { HttpErrorResponse } from '@angular/common/http';
import { Observable, throwError, timer } from 'rxjs';
import { mergeMap, retryWhen } from 'rxjs/operators';

// ==========================================
// ERROR CODE ENUMS
// ==========================================

/**
 * Error codes for validation errors
 */
export enum ValidationErrorCode {
  INVALID_DATE = 'VALIDATION_INVALID_DATE',
  INVALID_AMOUNT = 'VALIDATION_INVALID_AMOUNT',
  UNBALANCED_JOURNAL = 'VALIDATION_UNBALANCED_JOURNAL',
  MISSING_REQUIRED_FIELD = 'VALIDATION_MISSING_REQUIRED_FIELD',
  INVALID_TRANSACTION_TYPE = 'VALIDATION_INVALID_TRANSACTION_TYPE',
  INVALID_EMPLOYEE_ID = 'VALIDATION_INVALID_EMPLOYEE_ID',
  INVALID_ACCOUNT_ID = 'VALIDATION_INVALID_ACCOUNT_ID',
  INVALID_CLASS_ID = 'VALIDATION_INVALID_CLASS_ID',
  DATE_RANGE_INVALID = 'VALIDATION_DATE_RANGE_INVALID',
  AMOUNT_NEGATIVE = 'VALIDATION_AMOUNT_NEGATIVE',
  PERCENTAGE_SUM = 'VALIDATION_PERCENTAGE_SUM',
}

/**
 * Error codes for Staffology API errors
 */
export enum StaffologyErrorCode {
  HTTP_ERROR = 'STAFFOLOGY_HTTP_ERROR',
  NETWORK_FAILURE = 'STAFFOLOGY_NETWORK_FAILURE',
  TIMEOUT = 'STAFFOLOGY_TIMEOUT',
  INVALID_RESPONSE = 'STAFFOLOGY_INVALID_RESPONSE',
  AUTHENTICATION = 'STAFFOLOGY_AUTHENTICATION',
  RATE_LIMIT = 'STAFFOLOGY_RATE_LIMIT',
  NOT_FOUND = 'STAFFOLOGY_NOT_FOUND',
}

/**
 * Error codes for QuickBooks API errors
 */
export enum QuickBooksErrorCode {
  HTTP_ERROR = 'QUICKBOOKS_HTTP_ERROR',
  AUTHENTICATION = 'QUICKBOOKS_AUTHENTICATION',
  VALIDATION = 'QUICKBOOKS_VALIDATION',
  DUPLICATE_ENTRY = 'QUICKBOOKS_DUPLICATE_ENTRY',
  NETWORK_FAILURE = 'QUICKBOOKS_NETWORK_FAILURE',
  TIMEOUT = 'QUICKBOOKS_TIMEOUT',
  INVALID_RESPONSE = 'QUICKBOOKS_INVALID_RESPONSE',
  RATE_LIMIT = 'QUICKBOOKS_RATE_LIMIT',
  RESOURCE_NOT_FOUND = 'QUICKBOOKS_RESOURCE_NOT_FOUND',
  STALE_OBJECT = 'QUICKBOOKS_STALE_OBJECT',
}

/**
 * General payroll error codes
 */
export enum PayrollErrorCode {
  INTERNAL_ERROR = 'PAYROLL_INTERNAL_ERROR',
  UNKNOWN_ERROR = 'PAYROLL_UNKNOWN_ERROR',
}

export type ErrorCode =
  | ValidationErrorCode
  | StaffologyErrorCode
  | QuickBooksErrorCode
  | PayrollErrorCode;

// ==========================================
// ERROR INTERFACES
// ==========================================

/**
 * Base interface for all payroll errors
 */
export interface PayrollError {
  /** Error code for categorization */
  errorCode: ErrorCode | string;

  /** Human-readable error message */
  message: string;

  /** Additional context data */
  context?: Record<string, any>;

  /** Timestamp when error occurred */
  timestamp?: string;

  /** HTTP status code (if applicable) */
  httpStatusCode?: number;

  /** Whether this error is retryable */
  retryable?: boolean;
}

/**
 * Validation error interface
 */
export interface ValidationError extends PayrollError {
  errorCode: ValidationErrorCode;

  /** Field that failed validation */
  field?: string;

  /** Value that failed validation */
  value?: any;
}

/**
 * API error interface (Staffology or QuickBooks)
 */
export interface ApiError extends PayrollError {
  errorCode: StaffologyErrorCode | QuickBooksErrorCode;

  /** HTTP status code */
  httpStatusCode: number;

  /** API endpoint that failed */
  endpoint?: string;

  /** Response body from API */
  responseBody?: string;
}

/**
 * HTTP error response from backend
 */
export interface BackendErrorResponse {
  success: false;
  error: true;
  errorCode: string;
  message: string;
  context?: Record<string, any>;
  timestamp?: string;
  field?: string;
  value?: any;
  httpStatusCode?: number;
  endpoint?: string;
  responseBody?: string;
  oauthError?: string;
}

// ==========================================
// ERROR HANDLER CLASS
// ==========================================

/**
 * Payroll error handler with retry logic and error transformation
 */
export class PayrollErrorHandler {
  /**
   * Maximum number of retry attempts
   */
  private maxRetries: number = 3;

  /**
   * Delay between retries in milliseconds
   */
  private retryDelay: number = 1000;

  /**
   * Multiplier for exponential backoff
   */
  private backoffMultiplier: number = 2;

  constructor(
    maxRetries?: number,
    retryDelay?: number,
    backoffMultiplier?: number
  ) {
    if (maxRetries !== undefined) {
      this.maxRetries = maxRetries;
    }
    if (retryDelay !== undefined) {
      this.retryDelay = retryDelay;
    }
    if (backoffMultiplier !== undefined) {
      this.backoffMultiplier = backoffMultiplier;
    }
  }

  /**
   * Transform HTTP error response to PayrollError
   */
  transformHttpError(error: HttpErrorResponse): PayrollError {
    // If backend sent structured error response
    if (error.error && typeof error.error === 'object' && error.error.errorCode) {
      const backendError = error.error as BackendErrorResponse;

      return {
        errorCode: backendError.errorCode,
        message: backendError.message,
        context: backendError.context,
        timestamp: backendError.timestamp,
        httpStatusCode: error.status,
        retryable: this.isRetryableError(error.status),
      };
    }

    // Generic HTTP error
    return {
      errorCode: PayrollErrorCode.UNKNOWN_ERROR,
      message: this.getDefaultErrorMessage(error.status),
      httpStatusCode: error.status,
      timestamp: new Date().toISOString(),
      retryable: this.isRetryableError(error.status),
      context: {
        url: error.url || undefined,
        statusText: error.statusText,
      },
    };
  }

  /**
   * Get default error message for HTTP status code
   */
  private getDefaultErrorMessage(statusCode: number): string {
    switch (statusCode) {
      case 400:
        return 'Invalid request data';
      case 401:
        return 'Authentication required';
      case 403:
        return 'Access forbidden';
      case 404:
        return 'Resource not found';
      case 408:
        return 'Request timeout';
      case 429:
        return 'Too many requests - please try again later';
      case 500:
        return 'Internal server error';
      case 502:
        return 'Bad gateway - external service error';
      case 503:
        return 'Service unavailable';
      case 504:
        return 'Gateway timeout';
      default:
        if (statusCode >= 400 && statusCode < 500) {
          return 'Client error';
        } else if (statusCode >= 500) {
          return 'Server error';
        }
        return 'Unknown error occurred';
    }
  }

  /**
   * Determine if error is retryable based on status code
   */
  private isRetryableError(statusCode: number): boolean {
    // Retry on server errors (5xx) and timeout (408)
    return (
      statusCode >= 500 ||
      statusCode === 408 ||
      statusCode === 429 || // Rate limit
      statusCode === 0 // Network error
    );
  }

  /**
   * Create retry strategy for observables with exponential backoff
   */
  retryStrategy<T>(
    maxRetries?: number,
    excludeStatusCodes: number[] = [400, 401, 403, 404]
  ) {
    const maxAttempts = maxRetries ?? this.maxRetries;

    return (errors: Observable<HttpErrorResponse>) =>
      errors.pipe(
        mergeMap((error, index) => {
          const retryAttempt = index + 1;

          // Don't retry if max attempts reached
          if (retryAttempt > maxAttempts) {
            return throwError(() => error);
          }

          // Don't retry client errors (except 408 timeout and 429 rate limit)
          if (
            error.status >= 400 &&
            error.status < 500 &&
            error.status !== 408 &&
            error.status !== 429
          ) {
            return throwError(() => error);
          }

          // Don't retry excluded status codes
          if (excludeStatusCodes.includes(error.status)) {
            return throwError(() => error);
          }

          // Calculate delay with exponential backoff
          const delay =
            this.retryDelay * Math.pow(this.backoffMultiplier, retryAttempt - 1);

          console.warn(
            `Retry attempt ${retryAttempt}/${maxAttempts} after ${delay}ms for error:`,
            error.status,
            error.message
          );

          // Retry after delay
          return timer(delay);
        })
      );
  }

  /**
   * Apply retry logic to an observable
   */
  withRetry<T>(
    source: Observable<T>,
    maxRetries?: number,
    excludeStatusCodes?: number[]
  ): Observable<T> {
    return source.pipe(
      retryWhen(this.retryStrategy<T>(maxRetries, excludeStatusCodes))
    );
  }

  /**
   * Handle error and return user-friendly message
   */
  getUserMessage(error: PayrollError | HttpErrorResponse): string {
    if (error instanceof HttpErrorResponse) {
      const transformedError = this.transformHttpError(error);
      return transformedError.message;
    }

    return error.message;
  }

  /**
   * Check if error should show technical details to user
   */
  shouldShowTechnicalDetails(error: PayrollError): boolean {
    // Show technical details for validation errors (user can fix them)
    if (
      Object.values(ValidationErrorCode).includes(
        error.errorCode as ValidationErrorCode
      )
    ) {
      return true;
    }

    // Hide technical details for API errors (user can't fix them)
    return false;
  }

  /**
   * Format error for display to user
   */
  formatForDisplay(error: PayrollError | HttpErrorResponse): {
    title: string;
    message: string;
    details?: string;
    retryable: boolean;
  } {
    const transformedError =
      error instanceof HttpErrorResponse
        ? this.transformHttpError(error)
        : error;

    let title = 'Error';
    if (transformedError.errorCode.startsWith('VALIDATION_')) {
      title = 'Validation Error';
    } else if (transformedError.errorCode.startsWith('STAFFOLOGY_')) {
      title = 'Staffology API Error';
    } else if (transformedError.errorCode.startsWith('QUICKBOOKS_')) {
      title = 'QuickBooks Error';
    }

    const message = transformedError.message;

    let details: string | undefined;
    if (this.shouldShowTechnicalDetails(transformedError)) {
      if (transformedError.context) {
        details = JSON.stringify(transformedError.context, null, 2);
      }
    }

    return {
      title,
      message,
      details,
      retryable: transformedError.retryable ?? false,
    };
  }
}

// ==========================================
// ERROR FACTORY FUNCTIONS
// ==========================================

/**
 * Create a validation error
 */
export function createValidationError(
  code: ValidationErrorCode,
  message: string,
  field?: string,
  value?: any,
  context?: Record<string, any>
): ValidationError {
  return {
    errorCode: code,
    message,
    field,
    value,
    context,
    timestamp: new Date().toISOString(),
    retryable: false,
  };
}

/**
 * Create an API error
 */
export function createApiError(
  code: StaffologyErrorCode | QuickBooksErrorCode,
  message: string,
  httpStatusCode: number,
  endpoint?: string,
  responseBody?: string,
  context?: Record<string, any>
): ApiError {
  return {
    errorCode: code,
    message,
    httpStatusCode,
    endpoint,
    responseBody,
    context,
    timestamp: new Date().toISOString(),
    retryable: httpStatusCode >= 500 || httpStatusCode === 408 || httpStatusCode === 429,
  };
}
