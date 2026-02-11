<?php

namespace Models;

/**
 * Standardized API response structure
 *
 * Allows returning data along with informational messages, warnings, or errors
 * without stopping processing
 */
class ApiResponse
{
    public bool $success;
    public mixed $data;
    public array $messages;

    /**
     * Create a new API response
     *
     * @param bool $success Whether the operation succeeded
     * @param mixed $data The response data
     * @param array $messages Array of message objects
     */
    public function __construct(bool $success = true, mixed $data = null, array $messages = [])
    {
        $this->success = $success;
        $this->data = $data;
        $this->messages = $messages;
    }

    /**
     * Add an info message
     *
     * @param string $message The message text
     * @param array $context Additional context data
     * @return self
     */
    public function addInfo(string $message, array $context = []): self
    {
        $this->messages[] = [
            'type' => 'info',
            'message' => $message,
            'context' => $context,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        return $this;
    }

    /**
     * Add a warning message
     *
     * @param string $message The message text
     * @param array $context Additional context data
     * @return self
     */
    public function addWarning(string $message, array $context = []): self
    {
        $this->messages[] = [
            'type' => 'warning',
            'message' => $message,
            'context' => $context,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        return $this;
    }

    /**
     * Add a success message
     *
     * @param string $message The message text
     * @param array $context Additional context data
     * @return self
     */
    public function addSuccess(string $message, array $context = []): self
    {
        $this->messages[] = [
            'type' => 'success',
            'message' => $message,
            'context' => $context,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        return $this;
    }

    /**
     * Add an error message
     *
     * @param string $message The message text
     * @param array $context Additional context data
     * @return self
     */
    public function addError(string $message, array $context = []): self
    {
        $this->messages[] = [
            'type' => 'error',
            'message' => $message,
            'context' => $context,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        return $this;
    }

    /**
     * Check if there are any messages of a specific type
     *
     * @param string $type The message type to check
     * @return bool
     */
    public function hasMessagesOfType(string $type): bool
    {
        foreach ($this->messages as $message) {
            if ($message['type'] === $type) {
                return true;
            }
        }
        return false;
    }

    /**
     * Convert to array for JSON serialization
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'data' => $this->data,
            'messages' => $this->messages
        ];
    }

    /**
     * Convert to JSON
     *
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }
}
