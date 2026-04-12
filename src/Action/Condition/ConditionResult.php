<?php

namespace App\Action\Condition;

class ConditionResult
{
    private bool $success;

    private $conditionSuccessMessages = array();
    private $conditionFailureMessages = array();

    public function __construct(bool $success, array $conditionSuccessMessages, array $conditionFailureMessages) {
        $this->success = $success;
        $this->conditionSuccessMessages = $conditionSuccessMessages;
        $this->conditionFailureMessages = $conditionFailureMessages;
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

}