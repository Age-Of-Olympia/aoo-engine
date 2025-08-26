<?php
// Mock pour PlayerService
namespace Tests\Action\Mock;

use App\Interface\PlayerServiceInterface;
use Classes\Player;

class PlayerServiceMock implements PlayerServiceInterface
{
    private int $playerId;
   
    public function __construct(int $playerId)
    {
        $this->playerId = $playerId;
    }
   
    public function getPlainEmail(int $playerId): ?string
    {
        // Mock implementation
        return null;
    }

    public function getEmailBonus(int $playerId): bool
    {
        // Mock implementation
        return false;
    }

    public function getPlayerFields(array $fields): array
    {
        // Mock implementation
        return [];
    }

    public function isInactive(int $lastLoginTime): bool
    {
        // Mock implementation
        return false;
    }

    public function searchNonAnonymePlayer(string $searchKey): array
    {
        // Mock implementation
        return [];
    }

    public function GetPlayer($id, bool $readCache = true, bool $writeCache = true): Player
    {
        // Mock implementation - retourne un objet Player basique
        return new Player($id);
    }

    public function getAllPlayers(): array
    {
        // Mock implementation
        return [];
    }

    public function updateLastActionTime(): void
    {
        // Mock implementation
    }

    public function getNumberOfSpellAvailable(): int
    {
        // Mock implementation
        return 0;
    }
}