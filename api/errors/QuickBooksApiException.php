<?php

namespace Errors;

use Throwable;

/**
 * Exception thrown when QuickBooks Online API calls fail
 *
 * Used for:
 * - HTTP errors (4xx, 5xx)
 * - Authentication failures
 * - Invalid journal entries
 * - QB validation errors
 * - Network failures
 *
 * @category Error
 */
class QuickBooksApiException extends PayrollException
{
    // Error codes for specific QuickBooks API failures
    public const ERROR_HTTP_ERROR = 'QUICKBOOKS_HTTP_ERROR';
    public const ERROR_AUTHENTICATION = 'QUICKBOOKS_AUTHENTICATION';
    public const ERROR_VALIDATION = 'QUICKBOOKS_VALIDATION';
    public const ERROR_DUPLICATE_ENTRY = 'QUICKBOOKS_DUPLICATE_ENTRY';
    public const ERROR_NETWORK_FAILURE = 'QUICKBOOKS_NETWORK_FAILURE';
    public const ERROR_TIMEOUT = 'QUICKBOOKS_TIMEOUT';
    public const ERROR_INVALID_RESPONSE = 'QUICKBOOKS_INVALID_RESPONSE';
    public const ERROR_RATE_LIMIT = 'QUICKBOOKS_RATE_LIMIT';
    public const ERROR_RESOURCE_NOT_FOUND = 'QUICKBOOKS_RESOURCE_NOT_FOUND';
    public const ERROR_STALE_OBJECT = 'QUICKBOOKS_STALE_OBJECT';

    /**
     * HTTP status code (if applicable)
     *
     * @var int|null
     */
    protected ?int $httpStatusCode = null;

    /**
     * QuickBooks error helper message
     *
     * @var string|null
     */
    protected ?string $oauthHelperError = null;

    /**
     * QuickBooks response body
     *
     * @var string|null
     */
    protected ?string $responseBody = null;

    /**
     * Journal entry data that failed
     *
     * @var array|null
     */
    protected ?array $journalData = null;

    /**
     * Realm ID (company file ID)
     *
     * @var string|null
     */
    protected ?string $realmId = null;

    /**
     * Constructor
     *
     * @param string $message Error message
     * @param string $errorCode Error code
     * @param int|null $httpStatusCode HTTP status code
     * @param string|null $oauthHelperError OAuth helper error message
     * @param string|null $responseBody Response body
     * @param array|null $journalData Journal entry data
     * @param string|null $realmId Realm ID
     * @param array $context Additional context data
     * @param int $code Numeric error code (optional)
     * @param Throwable|null $previous Previous exception (optional)
     */
    public function __construct(
        string $message = "",
        string $errorCode = self::ERROR_HTTP_ERROR,
        ?int $httpStatusCode = null,
        ?string $oauthHelperError = null,
        ?string $responseBody = null,
        ?array $journalData = null,
        ?string $realmId = null,
        array $context = [],
        int $code = 0,
        ?Throwable $previous = null
    ) {
        $this->httpStatusCode = $httpStatusCode;
        $this->oauthHelperError = $oauthHelperError;
        $this->responseBody = $responseBody;
        $this->journalData = $journalData;
        $this->realmId = $realmId;

        // Add QB details to context
        if ($httpStatusCode !== null) {
            $context['httpStatusCode'] = $httpStatusCode;
        }
        if ($oauthHelperError !== null) {
            $context['oauthHelperError'] = $oauthHelperError;
        }
        if ($responseBody !== null) {
            $context['responseBody'] = $responseBody;
        }
        if ($realmId !== null) {
            $context['realmId'] = $realmId;
        }

        parent::__construct($message, $errorCode, $context, $code, $previous);
    }

    /**
     * Get HTTP status code
     *
     * @return int|null
     */
    public function getHttpStatusCode(): ?int
    {
        return $this->httpStatusCode;
    }

    /**
     * Get OAuth helper error message
     *
     * @return string|null
     */
    public function getOAuthHelperError(): ?string
    {
        return $this->oauthHelperError;
    }

    /**
     * Get response body
     *
     * @return string|null
     */
    public function getResponseBody(): ?string
    {
        return $this->responseBody;
    }

    /**
     * Get journal data
     *
     * @return array|null
     */
    public function getJournalData(): ?array
    {
        return $this->journalData;
    }

    /**
     * Get realm ID
     *
     * @return string|null
     */
    public function getRealmId(): ?string
    {
        return $this->realmId;
    }

    /**
     * Create exception from QuickBooks SDK error object
     *
     * @param object $error QB SDK error object
     * @param array|null $journalData Journal entry data
     * @param string|null $realmId Realm ID
     * @return static
     */
    public static function fromSdkError(object $error, ?array $journalData = null, ?string $realmId = null): static
    {
        $statusCode = method_exists($error, 'getHttpStatusCode') ? $error->getHttpStatusCode() : null;
        $helperError = method_exists($error, 'getOAuthHelperError') ? $error->getOAuthHelperError() : null;
        $responseBody = method_exists($error, 'getResponseBody') ? $error->getResponseBody() : null;

        // Determine error code based on status code or response content
        $errorCode = self::ERROR_HTTP_ERROR;
        if ($statusCode === 401 || $statusCode === 403) {
            $errorCode = self::ERROR_AUTHENTICATION;
        } elseif ($statusCode === 400) {
            $errorCode = self::ERROR_VALIDATION;
        } elseif ($statusCode === 429) {
            $errorCode = self::ERROR_RATE_LIMIT;
        } elseif ($statusCode === 404) {
            $errorCode = self::ERROR_RESOURCE_NOT_FOUND;
        }

        return new static(
            "QuickBooks API error: " . ($responseBody ?? 'Unknown error'),
            $errorCode,
            $statusCode,
            $helperError,
            $responseBody,
            $journalData,
            $realmId
        );
    }

    /**
     * Create exception for authentication failure
     *
     * @param string|null $realmId Realm ID
     * @return static
     */
    public static function authenticationFailed(?string $realmId = null): static
    {
        return new static(
            "QuickBooks authentication failed",
            self::ERROR_AUTHENTICATION,
            401,
            null,
            null,
            null,
            $realmId
        );
    }

    /**
     * Create exception for validation error
     *
     * @param string $validationMessage Validation error message
     * @param array|null $journalData Journal entry data that failed validation
     * @return static
     */
    public static function validationError(string $validationMessage, ?array $journalData = null): static
    {
        return new static(
            "QuickBooks validation error: {$validationMessage}",
            self::ERROR_VALIDATION,
            400,
            null,
            $validationMessage,
            $journalData
        );
    }

    /**
     * Create exception for duplicate entry
     *
     * @param string $docNumber Document number that is duplicate
     * @return static
     */
    public static function duplicateEntry(string $docNumber): static
    {
        return new static(
            "QuickBooks duplicate entry: Journal with DocNumber '{$docNumber}' already exists",
            self::ERROR_DUPLICATE_ENTRY,
            400,
            null,
            null,
            null,
            null,
            ['docNumber' => $docNumber]
        );
    }

    /**
     * Create exception for network failure
     *
     * @param string $reason Reason for failure
     * @return static
     */
    public static function networkFailure(string $reason): static
    {
        return new static(
            "QuickBooks API network failure: {$reason}",
            self::ERROR_NETWORK_FAILURE,
            null,
            null,
            null,
            null,
            null,
            ['reason' => $reason]
        );
    }

    /**
     * Create exception for timeout
     *
     * @param int $timeoutSeconds Timeout duration in seconds
     * @return static
     */
    public static function timeout(int $timeoutSeconds): static
    {
        return new static(
            "QuickBooks API timeout after {$timeoutSeconds} seconds",
            self::ERROR_TIMEOUT,
            null,
            null,
            null,
            null,
            null,
            ['timeoutSeconds' => $timeoutSeconds]
        );
    }

    /**
     * Create exception for stale object error
     *
     * @param string $objectType Type of object (e.g., "JournalEntry")
     * @param string $objectId Object ID
     * @return static
     */
    public static function staleObject(string $objectType, string $objectId): static
    {
        return new static(
            "QuickBooks stale object error: {$objectType} with ID {$objectId} has been modified",
            self::ERROR_STALE_OBJECT,
            400,
            null,
            null,
            null,
            null,
            ['objectType' => $objectType, 'objectId' => $objectId]
        );
    }
}
