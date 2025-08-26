<?php
namespace App\Action\Condition;

// Should be in a bootstrap for the tests
require_once(__DIR__.'/../../../config/constants.php');

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;
use App\Interface\ConditionInterface;

abstract class BaseCondition implements ConditionInterface
{
    protected bool $shouldRefresh = false;
    protected array $preConditions = array();

    public function toRemove(): bool {
        return false;
    }

    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition): ConditionResult
    {
        $preConditionResult = $this->checkPreconditions($actor, $target, $condition);
        return $preConditionResult;
    }

    public function applyCosts(ActorInterface $actor, ?ActorInterface $target, ActionCondition $conditionToPay): array
    {
        $result = array();
        foreach ($conditionToPay->getParameters() as $key => $value) {
            $actor->putBonus([$key => -$value]);
            $text = "Vous avez dépensé " . $value . " " . CARACS[$key].".";
            array_push($result, $text);
        }
        return $result;
    }

    public function shouldRefreshUi(): bool  {
        return $this->shouldRefresh;
    }

    public function checkPreconditions(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition): ConditionResult
    {
        array_unshift($this->preConditions, new PlanCondition());

        $success = true;
        $successMessages = array();
        $failureMessages = array();
        foreach ($this->preConditions as $preCondition) {
            $resultCondition = $preCondition->check($actor,$target,$condition);
            if ($resultCondition->isSuccess()) {
                $successMessages = array_merge($successMessages, $resultCondition->getConditionSuccessMessages());
            } else {
                $failureMessages = array_merge($failureMessages, $resultCondition->getConditionFailureMessages());
            }
            $success = $success && $resultCondition->isSuccess();
        }

        return new ConditionResult($success, $successMessages, $failureMessages);
    }
}