/**
 * Unit tests for PayrollErrorHandler and error factory functions
 *
 * Tests:
 * - transformHttpError: maps HttpErrorResponse to PayrollError
 * - getDefaultErrorMessage: correct message per HTTP status code (tested via transformHttpError)
 * - isRetryableError: 5xx, 408, 429, 0 are retryable; 4xx are not (tested via transformHttpError)
 * - getUserMessage: returns message from PayrollError or HttpErrorResponse
 * - shouldShowTechnicalDetails: true for ValidationErrorCode, false otherwise
 * - formatForDisplay: correct title based on error code prefix, details, retryable flag
 * - createValidationError / createApiError factory functions
 */

import { HttpErrorResponse } from '@angular/common/http';
import {
  PayrollErrorHandler,
  PayrollErrorCode,
  ValidationErrorCode,
  StaffologyErrorCode,
  QuickBooksErrorCode,
  createValidationError,
  createApiError,
} from './errors.model';

describe('PayrollErrorHandler', () => {
  let handler: PayrollErrorHandler;

  beforeEach(() => {
    handler = new PayrollErrorHandler();
  });

  // ==========================================
  // transformHttpError
  // ==========================================

  describe('transformHttpError()', () => {
    it('should use the backend errorCode and message when the response has a structured error body', () => {
      const error = httpError(422, {
        errorCode: 'VALIDATION_INVALID_AMOUNT',
        message: 'Amount must be positive',
        success: false,
        error: true,
      });

      const result = handler.transformHttpError(error);

      expect(result.errorCode).toBe('VALIDATION_INVALID_AMOUNT');
      expect(result.message).toBe('Amount must be positive');
      expect(result.httpStatusCode).toBe(422);
    });

    it('should use UNKNOWN_ERROR and a default message for a generic HTTP error', () => {
      const error = httpError(500);

      const result = handler.transformHttpError(error);

      expect(result.errorCode).toBe(PayrollErrorCode.UNKNOWN_ERROR);
      expect(result.message).toBe('Internal server error');
    });

    it('should include the request URL in context for generic errors', () => {
      const error = new HttpErrorResponse({
        status: 500,
        url: 'https://api.example.com/payroll',
      });

      const result = handler.transformHttpError(error);

      expect(result.context?.['url']).toBe('https://api.example.com/payroll');
    });

    describe('default messages by status code', () => {
      const cases: [number, string][] = [
        [400, 'Invalid request data'],
        [401, 'Authentication required'],
        [403, 'Access forbidden'],
        [404, 'Resource not found'],
        [408, 'Request timeout'],
        [429, 'Too many requests - please try again later'],
        [500, 'Internal server error'],
        [502, 'Bad gateway - external service error'],
        [503, 'Service unavailable'],
        [504, 'Gateway timeout'],
        [422, 'Client error'],      // generic 4xx
        [0,   'Unknown error occurred'],
      ];

      cases.forEach(([status, expectedMessage]) => {
        it(`status ${status} → "${expectedMessage}"`, () => {
          const result = handler.transformHttpError(httpError(status));
          expect(result.message).toBe(expectedMessage);
        });
      });
    });

    describe('retryable flag', () => {
      it('should mark 5xx errors as retryable', () => {
        expect(handler.transformHttpError(httpError(500)).retryable).toBe(true);
        expect(handler.transformHttpError(httpError(503)).retryable).toBe(true);
      });

      it('should mark 408 (timeout) as retryable', () => {
        expect(handler.transformHttpError(httpError(408)).retryable).toBe(true);
      });

      it('should mark 429 (rate limit) as retryable', () => {
        expect(handler.transformHttpError(httpError(429)).retryable).toBe(true);
      });

      it('should mark status 0 (network error) as retryable', () => {
        expect(handler.transformHttpError(httpError(0)).retryable).toBe(true);
      });

      it('should not mark 4xx client errors as retryable', () => {
        expect(handler.transformHttpError(httpError(400)).retryable).toBe(false);
        expect(handler.transformHttpError(httpError(401)).retryable).toBe(false);
        expect(handler.transformHttpError(httpError(403)).retryable).toBe(false);
        expect(handler.transformHttpError(httpError(404)).retryable).toBe(false);
      });
    });
  });

  // ==========================================
  // getUserMessage
  // ==========================================

  describe('getUserMessage()', () => {
    it('should return the message from a PayrollError directly', () => {
      const error = {
        errorCode: PayrollErrorCode.UNKNOWN_ERROR,
        message: 'Something went wrong',
      };

      expect(handler.getUserMessage(error)).toBe('Something went wrong');
    });

    it('should transform and return the message from an HttpErrorResponse', () => {
      const error = httpError(404);

      expect(handler.getUserMessage(error)).toBe('Resource not found');
    });
  });

  // ==========================================
  // shouldShowTechnicalDetails
  // ==========================================

  describe('shouldShowTechnicalDetails()', () => {
    it('should return true for any ValidationErrorCode', () => {
      Object.values(ValidationErrorCode).forEach((code) => {
        expect(
          handler.shouldShowTechnicalDetails({ errorCode: code, message: '' }),
        ).toBe(true);
      });
    });

    it('should return false for PayrollErrorCode', () => {
      expect(
        handler.shouldShowTechnicalDetails({
          errorCode: PayrollErrorCode.UNKNOWN_ERROR,
          message: '',
        }),
      ).toBe(false);
    });

    it('should return false for StaffologyErrorCode', () => {
      expect(
        handler.shouldShowTechnicalDetails({
          errorCode: StaffologyErrorCode.HTTP_ERROR,
          message: '',
        }),
      ).toBe(false);
    });

    it('should return false for QuickBooksErrorCode', () => {
      expect(
        handler.shouldShowTechnicalDetails({
          errorCode: QuickBooksErrorCode.HTTP_ERROR,
          message: '',
        }),
      ).toBe(false);
    });
  });

  // ==========================================
  // formatForDisplay
  // ==========================================

  describe('formatForDisplay()', () => {
    it('should use "Validation Error" title for VALIDATION_ error codes', () => {
      const error = {
        errorCode: ValidationErrorCode.INVALID_AMOUNT,
        message: 'Amount is invalid',
        retryable: false,
      };

      const result = handler.formatForDisplay(error);

      expect(result.title).toBe('Validation Error');
      expect(result.message).toBe('Amount is invalid');
    });

    it('should use "Staffology API Error" title for STAFFOLOGY_ error codes', () => {
      const error = {
        errorCode: StaffologyErrorCode.HTTP_ERROR,
        message: 'Staffology failed',
        retryable: false,
      };

      expect(handler.formatForDisplay(error).title).toBe('Staffology API Error');
    });

    it('should use "QuickBooks Error" title for QUICKBOOKS_ error codes', () => {
      const error = {
        errorCode: QuickBooksErrorCode.HTTP_ERROR,
        message: 'QB failed',
        retryable: false,
      };

      expect(handler.formatForDisplay(error).title).toBe('QuickBooks Error');
    });

    it('should use "Error" title for other error codes', () => {
      const error = {
        errorCode: PayrollErrorCode.UNKNOWN_ERROR,
        message: 'Something failed',
        retryable: false,
      };

      expect(handler.formatForDisplay(error).title).toBe('Error');
    });

    it('should include context as details for validation errors that have context', () => {
      const error = {
        errorCode: ValidationErrorCode.INVALID_AMOUNT,
        message: 'Amount invalid',
        context: { field: 'totalPay', value: -100 },
        retryable: false,
      };

      const result = handler.formatForDisplay(error);

      expect(result.details).toBeDefined();
      expect(result.details).toContain('totalPay');
    });

    it('should not include details for non-validation errors', () => {
      const error = {
        errorCode: QuickBooksErrorCode.HTTP_ERROR,
        message: 'QB failed',
        context: { endpoint: '/api/journals' },
        retryable: false,
      };

      const result = handler.formatForDisplay(error);

      expect(result.details).toBeUndefined();
    });

    it('should pass through the retryable flag', () => {
      const retryable = {
        errorCode: PayrollErrorCode.UNKNOWN_ERROR,
        message: '',
        retryable: true,
      };
      const nonRetryable = {
        errorCode: PayrollErrorCode.UNKNOWN_ERROR,
        message: '',
        retryable: false,
      };

      expect(handler.formatForDisplay(retryable).retryable).toBe(true);
      expect(handler.formatForDisplay(nonRetryable).retryable).toBe(false);
    });

    it('should accept an HttpErrorResponse and transform it', () => {
      const result = handler.formatForDisplay(httpError(404));

      expect(result.message).toBe('Resource not found');
    });
  });
});

// ==========================================
// FACTORY FUNCTIONS
// ==========================================

describe('createValidationError()', () => {
  it('should create a ValidationError with the provided fields', () => {
    const error = createValidationError(
      ValidationErrorCode.INVALID_AMOUNT,
      'Amount must be positive',
      'totalPay',
      -100,
      { min: 0 },
    );

    expect(error.errorCode).toBe(ValidationErrorCode.INVALID_AMOUNT);
    expect(error.message).toBe('Amount must be positive');
    expect(error.field).toBe('totalPay');
    expect(error.value).toBe(-100);
    expect(error.context).toEqual({ min: 0 });
    expect(error.retryable).toBe(false);
    expect(error.timestamp).toBeDefined();
  });
});

describe('createApiError()', () => {
  it('should create an ApiError with the provided fields', () => {
    const error = createApiError(
      StaffologyErrorCode.HTTP_ERROR,
      'Staffology request failed',
      503,
      '/api/payslips',
    );

    expect(error.errorCode).toBe(StaffologyErrorCode.HTTP_ERROR);
    expect(error.message).toBe('Staffology request failed');
    expect(error.httpStatusCode).toBe(503);
    expect(error.endpoint).toBe('/api/payslips');
  });

  it('should mark 5xx errors as retryable', () => {
    const error = createApiError(
      StaffologyErrorCode.HTTP_ERROR,
      'Server error',
      500,
    );
    expect(error.retryable).toBe(true);
  });

  it('should not mark 4xx errors as retryable (except 408 and 429)', () => {
    const error = createApiError(
      QuickBooksErrorCode.AUTHENTICATION,
      'Auth failed',
      401,
    );
    expect(error.retryable).toBe(false);
  });

  it('should mark 408 and 429 as retryable', () => {
    expect(
      createApiError(StaffologyErrorCode.TIMEOUT, 'Timeout', 408).retryable,
    ).toBe(true);
    expect(
      createApiError(StaffologyErrorCode.RATE_LIMIT, 'Rate limit', 429).retryable,
    ).toBe(true);
  });
});

// ==========================================
// HELPER
// ==========================================

function httpError(status: number, errorBody?: object): HttpErrorResponse {
  return new HttpErrorResponse({ status, error: errorBody ?? null });
}
