<?php

namespace Tests\Action\Mock;

class PassiveServiceStub
{
    /** @var array<int, object> */
    public array $passives = [];

    public bool $hasPassive = false;

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
}
