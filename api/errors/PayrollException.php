<?php

namespace Errors;

use Exception;
use Throwable;

/**
 * Base exception class for all payroll-related errors
 *
 * Provides standardized error handling with error codes, context data, and timestamps
 *
 * @category Error
 */
class PayrollException extends Exception
{
    /**
     * Additional context data about the error
     *
     * @var array
     */
    protected array $context;

    /**
     * Timestamp when the error occurred
     *
     * @var string
     */
    protected string $timestamp;

    /**
     * Error code for categorization
     *
     * @var string
     */
    protected string $errorCode;

    /**
     * Constructor
     *
     * @param string $message Error message
     * @param string $errorCode Error code for categorization
     * @param array $context Additional context data
     * @param int $code Numeric error code (optional)
     * @param Throwable|null $previous Previous exception (optional)
     */
    public function __construct(
        string $message = "",
        string $errorCode = "PAYROLL_ERROR",
        array $context = [],
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);

        $this->errorCode = $errorCode;
        $this->context = $context;
        $this->timestamp = date('Y-m-d H:i:s');
    }

    /**
     * Get the error code
     *
     * @return string
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * Get the context data
     *
     * @return array
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Get the timestamp
     *
     * @return string
     */
    public function getTimestamp(): string
    {
        return $this->timestamp;
    }

    /**
     * Convert exception to array for JSON serialization
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'error' => true,
            'errorCode' => $this->errorCode,
            'message' => $this->getMessage(),
            'context' => $this->context,
            'timestamp' => $this->timestamp,
            'file' => $this->getFile(),
            'line' => $this->getLine(),
        ];
    }

    /**
     * Convert exception to JSON string
     *
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT);
    }
}
