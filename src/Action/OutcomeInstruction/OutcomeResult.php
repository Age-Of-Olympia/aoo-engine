<?php

namespace App\Action\OutcomeInstruction;

class OutcomeResult
{
    private bool $success;

    private $outcomeSuccessMessages;
    private $outcomeFailureMessages;

    private ?int $totalDamages;

    public function __construct(bool $success, ?array $outcomeSuccessMessages = array(), ?array $outcomeFailureMessages = array(), ?int $totalDamages = 0) {
        $this->success = $success;
        $this->outcomeSuccessMessages = $outcomeSuccessMessages;
        $this->outcomeFailureMessages = $outcomeFailureMessages;
        $this->totalDamages = $totalDamages;
    }

    public function isSuccess(): bool {
        return $this->success;
    }

    public function getOutcomeSuccessMessages(): array {
        return $this->outcomeSuccessMessages;
    }

    public function getOutcomeFailureMessages(): array {
        return $this->outcomeFailureMessages;
    }

    public function getTotalDamages(): ?int {
        return $this->totalDamages;
    }

}