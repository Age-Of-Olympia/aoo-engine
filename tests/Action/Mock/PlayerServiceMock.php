<?php

// Mock pour PlayerService
namespace Tests\Action\Mock;

class PlayerServiceMock
{
    private int $playerId;
    
    public function __construct(int $playerId)
    {
        $this->playerId = $playerId;
    }
    
    public function updateLastActionTime(): void
    {
        // Mock implementation
    }
}