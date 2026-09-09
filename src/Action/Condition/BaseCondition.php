<?php
namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;
use App\Interface\ConditionInterface;
use App\Service\PoolSpendService;

abstract class BaseCondition implements ConditionInterface
{
    public function toRemove(): bool {
        return false;
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
}