<?php

namespace Tests\Action\Mock;

class PassiveServiceStub
{
    /** @var array<int, object> */
    public array $passives = [];

    /**
     * @return array<int, object>
     */
    public function getPassivesByPlayerId(int $playerId): array
    {
        return $this->passives;
    }
}
