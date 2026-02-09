<?php

namespace Errors;

use Throwable;

/**
 * Standardized error response handler for API endpoints
 *
 * Provides consistent error responses across all payroll controllers
 *
 * @category Error
 */
class ErrorResponseHandler
{
    /**
     * Convert exception to standardized JSON response
     *
     * @param Throwable $exception The exception to convert
     * @param bool $includeTrace Whether to include stack trace (only in development)
     * @return string JSON error response
     */
    public static function toJsonResponse(Throwable $exception, bool $includeTrace = false): string
    {
        $response = [
            'success' => false,
            'error' => true,
        ];

        if ($exception instanceof PayrollException) {
            // Use structured PayrollException data
            $response['errorCode'] = $exception->getErrorCode();
            $response['message'] = $exception->getMessage();
            $response['context'] = $exception->getContext();
            $response['timestamp'] = $exception->getTimestamp();

            // Add specific fields for ValidationException
            if ($exception instanceof ValidationException) {
                if ($exception->getField() !== null) {
                    $response['field'] = $exception->getField();
                }
                if ($exception->getValue() !== null) {
                    $response['value'] = $exception->getValue();
                }
            }

            // Add specific fields for QuickBooksApiException
            if ($exception instanceof QuickBooksApiException) {
                if ($exception->getHttpStatusCode() !== null) {
                    $response['httpStatusCode'] = $exception->getHttpStatusCode();
                }
                if ($exception->getOAuthHelperError() !== null) {
                    $response['oauthError'] = $exception->getOAuthHelperError();
                }
            }

            // Add specific fields for StaffologyApiException
            if ($exception instanceof StaffologyApiException) {
                if ($exception->getHttpStatusCode() !== null) {
                    $response['httpStatusCode'] = $exception->getHttpStatusCode();
                }
                if ($exception->getEndpoint() !== null) {
                    $response['endpoint'] = $exception->getEndpoint();
                }
            }
        } else {
            // Generic exception - less structured
            $response['errorCode'] = 'INTERNAL_ERROR';
            $response['message'] = $exception->getMessage();
            $response['timestamp'] = date('Y-m-d H:i:s');
        }

        // Include stack trace in development mode
        if ($includeTrace) {
            $response['trace'] = $exception->getTraceAsString();
            $response['file'] = $exception->getFile();
            $response['line'] = $exception->getLine();
        }

        return json_encode($response, JSON_PRETTY_PRINT);
    }

    /**
     * Send JSON error response and exit
     *
     * @param Throwable $exception The exception to respond with
     * @param int $httpStatusCode HTTP status code to send
     * @param bool $includeTrace Whether to include stack trace
     * @return never
     */
    public static function sendJsonResponse(
        Throwable $exception,
        int $httpStatusCode = 500,
        bool $includeTrace = false
    ): never {
        // Set HTTP status code
        http_response_code($httpStatusCode);

        // Set content type
        header('Content-Type: application/json');

        // Send JSON response
        echo self::toJsonResponse($exception, $includeTrace);

        exit;
    }

    /**
     * Determine appropriate HTTP status code from exception
     *
     * @param Throwable $exception The exception
     * @return int HTTP status code
     */
    public static function getHttpStatusCode(Throwable $exception): int
    {
        // ValidationException -> 400 Bad Request
        if ($exception instanceof ValidationException) {
            return 400;
        }

        // QuickBooksApiException -> use QB status code or 502 Bad Gateway
        if ($exception instanceof QuickBooksApiException) {
            $statusCode = $exception->getHttpStatusCode();
            if ($statusCode !== null && $statusCode >= 400 && $statusCode < 600) {
                return $statusCode;
            }
            return 502; // Bad Gateway (external service error)
        }

        // StaffologyApiException -> use Staffology status code or 502 Bad Gateway
        if ($exception instanceof StaffologyApiException) {
            $statusCode = $exception->getHttpStatusCode();
            if ($statusCode !== null && $statusCode >= 400 && $statusCode < 600) {
                return $statusCode;
            }
            return 502; // Bad Gateway (external service error)
        }

        // PayrollException -> 500 Internal Server Error
        if ($exception instanceof PayrollException) {
            return 500;
        }

        // Generic exception -> 500 Internal Server Error
        return 500;
    }

    /**
     * Handle exception with automatic status code determination
     *
     * @param Throwable $exception The exception
     * @param bool $includeTrace Whether to include stack trace
     * @return never
     */
    public static function handle(Throwable $exception, bool $includeTrace = false): never
    {
        $statusCode = self::getHttpStatusCode($exception);
        self::sendJsonResponse($exception, $statusCode, $includeTrace);
    }

    /**
     * Log exception to file
     *
     * @param Throwable $exception The exception to log
     * @param string $logFile Path to log file
     * @return void
     */
    public static function log(Throwable $exception, string $logFile = null): void
    {
        if ($logFile === null) {
            $logFile = __DIR__ . '/../../logs/payroll-errors.log';
        }

        // Create log directory if it doesn't exist
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        // Format log entry
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = sprintf(
            "[%s] %s: %s in %s:%d\n",
            $timestamp,
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        );

        // Add context for PayrollException
        if ($exception instanceof PayrollException) {
            $logEntry .= "Error Code: " . $exception->getErrorCode() . "\n";
            $context = $exception->getContext();
            if (!empty($context)) {
                $logEntry .= "Context: " . json_encode($context) . "\n";
            }
        }

        $logEntry .= "Stack Trace:\n" . $exception->getTraceAsString() . "\n";
        $logEntry .= str_repeat('-', 80) . "\n\n";

        // Append to log file
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }

    /**
     * Handle exception with logging
     *
     * @param Throwable $exception The exception
     * @param bool $includeTrace Whether to include stack trace in response
     * @param string|null $logFile Path to log file
     * @return never
     */
    public static function handleWithLogging(
        Throwable $exception,
        bool $includeTrace = false,
        ?string $logFile = null
    ): never {
        // Log the exception
        self::log($exception, $logFile);

        // Send response
        self::handle($exception, $includeTrace);
    }
}
