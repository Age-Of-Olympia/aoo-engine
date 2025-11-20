<?php

namespace App\Tutorial\Exceptions;

/**
 * Exception for tutorial validation errors
 *
 * Thrown when step validation fails.
 * This is a "user error" exception - the user didn't complete the step correctly.
 * Should display helpful hint to guide the player.
 *
 * Used for:
 * - Movement validation failures
 * - Action validation failures
 * - UI interaction validation failures
 * - Combat validation failures
 * - Any prerequisite not met
 */
class TutorialValidationException extends TutorialException
{
    private ?string $hint;

    /**
     * Create validation exception with helpful hint
     *
     * @param string $message Error message (for logging)
     * @param string|null $hint Helpful hint to show user (e.g., "Move to position (1, 2)")
     * @param array $context Additional context data
     * @param int $code Error code
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message,
        ?string $hint = null,
        array $context = [],
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        $this->hint = $hint;
        parent::__construct($message, $context, $code, $previous);
    }

    /**
     * Get helpful hint to show user
     *
     * @return string|null Hint message or null if no hint available
     */
    public function getHint(): ?string
    {
        return $this->hint;
    }
}
