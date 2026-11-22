<?php
namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;

class SpellComputeCondition extends TechniqueComputeCondition
{
    private int $spellLevel = 0;

    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition, ConditionObject $conditionObject): ConditionResult
    {
        $this->spellLevel = $condition->getAction()->getLevel();

        return parent::check($actor, $target, $condition, $conditionObject);
    }

    public static function thresholdFor(int $spellLevel, int $distance): int
    {
        $steps = max(0, $distance - 1);

        return 3 * max(0, $spellLevel) + self::triangularNumber($steps);
    }

    protected function getDistanceTreshold(ActorInterface $actor): int
    {
        return self::thresholdFor($this->spellLevel, $this->distance);
    }

    protected function requiresDistanceThreshold(): bool
    {
        return true;
    }
}
