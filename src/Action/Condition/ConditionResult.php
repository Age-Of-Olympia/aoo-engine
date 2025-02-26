<?php

namespace App\Action\Condition;

class ConditionResult
{
    private bool $success;

    private $conditionSuccessMessages = array();
    private $conditionFailureMessages = array();

    private $actorRoll = array();
    private $targetRoll = array();

    private ?int $actorTotal;
    private ?int $targetTotal;

    public function __construct(bool $success, ?array $conditionSuccessMessages = null, ?array $conditionFailureMessages = null, ?array $actorRoll = null, ?array $targetRoll = null, ?int $actorTotal = null, ?int $targetTotal = null) {
        $this->success = $success;
        $this->conditionSuccessMessages = $conditionSuccessMessages;
        $this->conditionFailureMessages = $conditionFailureMessages;
        $this->actorRoll = $actorRoll;
        $this->targetRoll = $targetRoll;
        $this->actorTotal = $actorTotal;
        $this->targetTotal = $targetTotal;
    }

    public function isSuccess(): bool {
        return $this->success;
    }

    public function getConditionSuccessMessages(): ?array {
        return $this->conditionSuccessMessages;
    }

    public function getConditionFailureMessages(): ?array {
        return $this->conditionFailureMessages;
    }

    public function getActorRoll(): ?array {
        return $this->actorRoll;
    }

    public function getTargetRoll(): ?array {
        return $this->targetRoll;
    }

    public function getActorTotal(): ?int {
        return $this->actorTotal;
    }

    public function getTargetTotal(): ?int {
        return $this->targetTotal;
    }

}