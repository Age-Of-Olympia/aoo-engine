<?php

namespace App\Tutorial\Exceptions;

/**
 * Base exception for all tutorial-related errors
 *
 * This is the parent exception that all tutorial-specific exceptions extend.
 * Catching this exception will catch all tutorial system errors.
 */
class TutorialException extends \Exception
{
    /**
     * Create exception with context data
     *
     * @param string $message Error message
     * @param array $context Additional context data (logged but not in exception message)
     * @param int $code Error code
     * @param \Throwable|null $previous Previous exception for chaining
     */
    public function __construct(
        string $message,
        array $context = [],
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        // Log context data for debugging
        if (!empty($context)) {
            error_log("[TutorialException] Context: " . json_encode($context));
        }

        parent::__construct($message, $code, $previous);
    }
}
