<?php
namespace App\Action;

/**
 * A simple DTO class used to communicate the result of executing an action.
 */
class ActionResult
{
    public function __construct(
        private bool $success,
        private ?string $message = null
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function setSuccess(bool $success): self
    {
        $this->success = $success;
        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message;
        return $this;
    }
}
