<?php

namespace App\Action\Condition;

class ConditionResult
{
    private bool $success;

    private $conditionSuccessMessages = array();
    private $conditionFailureMessages = array();

    /**
     * This failure REFUSES the action instead of failing it at the actor's
     * expense.
     *
     * Carried by the result, not by the condition: the condition states what
     * it IS for its whole existence, the result states what just happened. A
     * blocking precondition (obstacle, anti-Berserk) raises it, and the
     * executor then stops before the outcomes and the costs.
     */
    private bool $blocking;

    public function __construct(bool $success, array $conditionSuccessMessages, array $conditionFailureMessages, bool $blocking = false) {
        $this->success = $success;
        $this->conditionSuccessMessages = $conditionSuccessMessages;
        $this->conditionFailureMessages = $conditionFailureMessages;
        $this->blocking = $blocking;
    }

    public function isSuccess(): bool {
        return $this->success;
    }

    public function isBlocking(): bool {
        return $this->blocking;
    }

    public function getConditionSuccessMessages(): ?array {
        return $this->conditionSuccessMessages;
    }

    public function getConditionFailureMessages(): ?array {
        return $this->conditionFailureMessages;
    }

}