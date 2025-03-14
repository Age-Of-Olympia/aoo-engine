<?php

namespace App\Action\EffectInstruction;

class EffectResult
{
    private bool $success;

    private $effectSuccessMessages;
    private $effectFailureMessages;

    private ?int $totalDamages;

    public function __construct(bool $success, ?array $effectSuccessMessages = array(), ?array $effectFailureMessages = array(), ?int $totalDamages = 0) {
        $this->success = $success;
        $this->effectSuccessMessages = $effectSuccessMessages;
        $this->effectFailureMessages = $effectFailureMessages;
        $this->totalDamages = $totalDamages;
    }

    public function isSuccess(): bool {
        return $this->success;
    }

    public function getEffectSuccessMessages(): array {
        return $this->effectSuccessMessages;
    }

    public function getEffectFailureMessages(): array {
        return $this->effectFailureMessages;
    }

    public function getTotalDamages(): ?int {
        return $this->totalDamages;
    }

}