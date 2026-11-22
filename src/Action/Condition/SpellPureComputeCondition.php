<?php
namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;

class SpellPureComputeCondition extends TechniquePureComputeCondition
{
    protected string $throwName = "Le sort";

    private int $spellLevel = 0;

    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition, ConditionObject $conditionObject): ConditionResult
    {
        $this->spellLevel = $condition->getAction()->getLevel();

        return parent::check($actor, $target, $condition, $conditionObject);
    }

    protected function getDistanceTreshold(ActorInterface $actor): int
    {
        return SpellComputeCondition::thresholdFor($this->spellLevel, $this->distance);
    }

    protected function requiresDistanceThreshold(): bool
    {
        return true;
    }
}
