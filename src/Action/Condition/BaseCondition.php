<?php
namespace App\Action\Condition;

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
        return new ConditionResult(true, array(), array());
    }
}