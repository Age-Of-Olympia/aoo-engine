<?php

namespace App\Action\Effect;

class EffectResult
{
    private bool $success;

    private $effectSuccessMessages = array();
    private $effectFailureMessages = array();

    private ?int $totalDamages;

    public function __construct(bool $success, ?array $effectSuccessMessages = null, ?array $effectFailureMessages = null, ?int $totalDamages = null) {
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