<?php

namespace App\Action\Condition;

class ConditionResult
{
    private bool $success;

    private $conditionSuccessMessage = array();
    private $conditionFailureMessage = array();

    private $actorRoll = array();
    private $targetRoll = array();

    private ?int $actorTotal;
    private ?int $targetTotal;

    public function __construct(bool $success, $conditionSuccessMessage = null, $conditionFailureMessage = null, $actorRoll = null, $targetRoll = null, $actorTotal = null, $targetTotal = null) {
        $this->success = $success;
        $this->conditionSuccessMessage = $conditionSuccessMessage;
        $this->conditionFailureMessage = $conditionFailureMessage;
        $this->actorRoll = $actorRoll;
        $this->targetRoll = $targetRoll;
        $this->actorTotal = $actorTotal;
        $this->targetTotal = $targetTotal;
    }

    public function isSuccess(): bool {
        return $this->success;
    }

    public function getConditionSuccessMessage(): ?array {
        return $this->conditionSuccessMessage;
    }

    public function getConditionFailureMessage(): ?array {
        return $this->conditionFailureMessage;
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