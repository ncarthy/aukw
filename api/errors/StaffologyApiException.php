<?php

namespace Errors;

use Throwable;

/**
 * Exception thrown when Staffology API calls fail
 *
 * Used for:
 * - HTTP errors (4xx, 5xx)
 * - Network failures
 * - Timeout errors
 * - Invalid API responses
 * - Authentication failures
 *
 * @category Error
 */
class StaffologyApiException extends PayrollException
{
    // Error codes for specific Staffology API failures
    public const ERROR_HTTP_ERROR = 'STAFFOLOGY_HTTP_ERROR';
    public const ERROR_NETWORK_FAILURE = 'STAFFOLOGY_NETWORK_FAILURE';
    public const ERROR_TIMEOUT = 'STAFFOLOGY_TIMEOUT';
    public const ERROR_INVALID_RESPONSE = 'STAFFOLOGY_INVALID_RESPONSE';
    public const ERROR_AUTHENTICATION = 'STAFFOLOGY_AUTHENTICATION';
    public const ERROR_RATE_LIMIT = 'STAFFOLOGY_RATE_LIMIT';
    public const ERROR_NOT_FOUND = 'STAFFOLOGY_NOT_FOUND';

    /**
     * HTTP status code (if applicable)
     *
     * @var int|null
     */
    protected ?int $httpStatusCode = null;

    /**
     * API endpoint that failed
     *
     * @var string|null
     */
    protected ?string $endpoint = null;

    /**
     * Request data that was sent
     *
     * @var array|null
     */
    protected ?array $requestData = null;

    /**
     * Response body from API
     *
     * @var string|null
     */
    protected ?string $responseBody = null;

    /**
     * Constructor
     *
     * @param string $message Error message
     * @param string $errorCode Error code
     * @param int|null $httpStatusCode HTTP status code
     * @param string|null $endpoint API endpoint
     * @param array|null $requestData Request data
     * @param string|null $responseBody Response body
     * @param array $context Additional context data
     * @param int $code Numeric error code (optional)
     * @param Throwable|null $previous Previous exception (optional)
     */
    public function __construct(
        string $message = "",
        string $errorCode = self::ERROR_HTTP_ERROR,
        ?int $httpStatusCode = null,
        ?string $endpoint = null,
        ?array $requestData = null,
        ?string $responseBody = null,
        array $context = [],
        int $code = 0,
        ?Throwable $previous = null
    ) {
        $this->httpStatusCode = $httpStatusCode;
        $this->endpoint = $endpoint;
        $this->requestData = $requestData;
        $this->responseBody = $responseBody;

        // Add HTTP details to context
        if ($httpStatusCode !== null) {
            $context['httpStatusCode'] = $httpStatusCode;
        }
        if ($endpoint !== null) {
            $context['endpoint'] = $endpoint;
        }
        if ($responseBody !== null) {
            $context['responseBody'] = $responseBody;
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
     * Get API endpoint
     *
     * @return string|null
     */
    public function getEndpoint(): ?string
    {
        return $this->endpoint;
    }

    /**
     * Get request data
     *
     * @return array|null
     */
    public function getRequestData(): ?array
    {
        return $this->requestData;
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
     * Create exception for HTTP error
     *
     * @param int $statusCode HTTP status code
     * @param string $endpoint API endpoint
     * @param string|null $responseBody Response body
     * @return static
     */
    public static function httpError(int $statusCode, string $endpoint, ?string $responseBody = null): static
    {
        return new static(
            "Staffology API HTTP error {$statusCode} for endpoint: {$endpoint}",
            self::ERROR_HTTP_ERROR,
            $statusCode,
            $endpoint,
            null,
            $responseBody
        );
    }

    /**
     * Create exception for authentication failure
     *
     * @param string $endpoint API endpoint
     * @return static
     */
    public static function authenticationFailed(string $endpoint): static
    {
        return new static(
            "Staffology API authentication failed for endpoint: {$endpoint}",
            self::ERROR_AUTHENTICATION,
            401,
            $endpoint
        );
    }

    /**
     * Create exception for network failure
     *
     * @param string $endpoint API endpoint
     * @param string $reason Reason for failure
     * @return static
     */
    public static function networkFailure(string $endpoint, string $reason): static
    {
        return new static(
            "Staffology API network failure for endpoint: {$endpoint}. Reason: {$reason}",
            self::ERROR_NETWORK_FAILURE,
            null,
            $endpoint,
            null,
            null,
            ['reason' => $reason]
        );
    }

    /**
     * Create exception for timeout
     *
     * @param string $endpoint API endpoint
     * @param int $timeoutSeconds Timeout duration in seconds
     * @return static
     */
    public static function timeout(string $endpoint, int $timeoutSeconds): static
    {
        return new static(
            "Staffology API timeout after {$timeoutSeconds} seconds for endpoint: {$endpoint}",
            self::ERROR_TIMEOUT,
            null,
            $endpoint,
            null,
            null,
            ['timeoutSeconds' => $timeoutSeconds]
        );
    }

    /**
     * Create exception for invalid response
     *
     * @param string $endpoint API endpoint
     * @param string $responseBody Response body
     * @param string $reason Reason why response is invalid
     * @return static
     */
    public static function invalidResponse(string $endpoint, string $responseBody, string $reason): static
    {
        return new static(
            "Staffology API returned invalid response for endpoint: {$endpoint}. Reason: {$reason}",
            self::ERROR_INVALID_RESPONSE,
            null,
            $endpoint,
            null,
            $responseBody,
            ['reason' => $reason]
        );
    }

    /**
     * Create exception for rate limit exceeded
     *
     * @param string $endpoint API endpoint
     * @param int|null $retryAfter Seconds until retry is allowed
     * @return static
     */
    public static function rateLimitExceeded(string $endpoint, ?int $retryAfter = null): static
    {
        $context = $retryAfter !== null ? ['retryAfter' => $retryAfter] : [];

        return new static(
            "Staffology API rate limit exceeded for endpoint: {$endpoint}",
            self::ERROR_RATE_LIMIT,
            429,
            $endpoint,
            null,
            null,
            $context
        );
    }

    /**
     * Create exception for resource not found
     *
     * @param string $endpoint API endpoint
     * @param string $resourceType Type of resource (e.g., "payslip", "employee")
     * @param string $resourceId ID of resource that wasn't found
     * @return static
     */
    public static function notFound(string $endpoint, string $resourceType, string $resourceId): static
    {
        return new static(
            "Staffology API resource not found: {$resourceType} with ID {$resourceId}",
            self::ERROR_NOT_FOUND,
            404,
            $endpoint,
            null,
            null,
            ['resourceType' => $resourceType, 'resourceId' => $resourceId]
        );
    }
}
