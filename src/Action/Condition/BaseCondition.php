<?php
namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;
use App\Interface\ConditionInterface;
use App\Action\Condition\ConditionObject;
use App\Service\PoolSpendService;

abstract class BaseCondition implements ConditionInterface
{
    protected bool $shouldRefresh = false;
    protected array $preConditions = array();

    public function toRemove(): bool {
        return false;
    }

    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition, ConditionObject $conditionObject): ConditionResult
    {
        $preConditionResult = $this->checkPreconditions($actor, $target, $condition, $conditionObject);
        return $preConditionResult;
    }

    public function applyCosts(ActorInterface $actor, ?ActorInterface $target, ActionCondition $conditionToPay): array
    {
        $result = array();
        foreach ($conditionToPay->getParameters() as $key => $value) {
            $spent = $this->pay($actor, (string) $key, (int) $value);
            $text = "Vous avez dépensé " . $spent . " " . CARACS[$key].".";
            array_push($result, $text);
        }
        return $result;
    }

    /**
     * Pays one cost and answers what was actually taken. A turn-pool
     * trait goes through the guarded spend — the pool may be shared, so
     * the debit and its floor are one statement — everything else keeps
     * the plain delta.
     */
    protected function pay(ActorInterface $actor, string $trait, int $cost): int
    {
        if (in_array($trait, PoolSpendService::POOL_TRAITS, true)) {
            return (new PoolSpendService())->spend($actor, $trait, $cost);
        }

        $actor->putBonus([$trait => -$cost]);

        return $cost;
    }

    public function shouldRefreshUi(): bool  {
        return $this->shouldRefresh;
    }

    public function checkPreconditions(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition, ConditionObject $conditionObject): ConditionResult
    {
        // PlanCondition (the enfers block) used to be array_unshift-ed here on
        // every call; it is now a data-driven global precondition run once by
        // ActionExecutorService. See ActionTypePreconditionResolver.

        $success = true;
        $successMessages = array();
        $failureMessages = array();
        foreach ($this->preConditions as $preCondition) {
            $resultCondition = $preCondition->check($actor,$target,$condition,$conditionObject);
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