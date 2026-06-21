<?php

namespace Tests\Action\Mock;

class PassiveServiceStub
{
    /** @var array<int, object> */
    public array $passives = [];

    public bool $hasPassive = false;

    /** Value returned for a passive resolved by its (integer) id. */
    public int $computedValue = 0;

    /**
     * @return array<int, object>
     */
    public function getPassivesByPlayerId(int $playerId): array
    {
        return $this->passives;
    }

    public function hasPassiveByPlayerIdByName(int $playerId, string $name): bool
    {
        return $this->hasPassive;
    }

    public function checkPassiveConditionsByPlayerById(object $player, object $passive, object $conditionObject): bool
    {
        return true;
    }

    /**
     * Mirrors the real service: it resolves the passive by id (findOneBy id), so a
     * name argument matches nothing and yields 0 — this is what makes the
     * id-vs-name callsite bug observable.
     */
    public function getComputedValueByPlayerIdById(int $playerId, mixed $id): int
    {
        return is_int($id) ? $this->computedValue : 0;
    }
}
