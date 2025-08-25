<?php

namespace Tests\Service;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;
use App\Service\PlayerService;
use App\Interface\ActorInterface;
class PlayerServiceTest extends TestCase
{
    private PlayerService $playerService;

    protected function setUp(): void
    {
        $this->playerService = new PlayerService(1);
    }

    #[Group('player-service')]
    public function testIsInactiveWithRecentLogin(): void
    {
        // Arrange - Connexion récente (1 heure)
        $recentLoginTime = time() - 3600;
        
        // Act
        $isInactive = $this->playerService->isInactive($recentLoginTime);
        
        // Assert
        $this->assertFalse($isInactive);
    }

    #[Group('player-service')]
    public function testIsInactiveWithOldLogin(): void
    {
        // Arrange - Connexion ancienne (plus de INACTIVE_TIME)
        if (!defined('INACTIVE_TIME')) {
            define('INACTIVE_TIME', 7 * 24 * 3600); // 7 jours
        }
        $oldLoginTime = time() - (INACTIVE_TIME + 3600);
        
        // Act
        $isInactive = $this->playerService->isInactive($oldLoginTime);
        
        // Assert
        $this->assertTrue($isInactive);
    }

    #[Group('player-service')]
    public function testSearchNonAnonymePlayer(): void
    {
        // Note: Ce test nécessiterait un mock de base de données
        // Pour l'instant, on teste la structure
        
        // Act
        $results = $this->playerService->searchNonAnonymePlayer('test');
        
        // Assert
        $this->assertIsArray($results);
    }

    #[Group('player-service')]
    public function testGetPlayer(): void
    {
        // Act
        $player = $this->playerService->GetPlayer(1);
        
        // Assert
        $this->assertInstanceOf(ActorInterface::class, $player);
        $this->assertEquals(1, $player->getId());
    }

    #[Group('player-service')]
    public function testGetPlayerWithCache(): void
    {
        // Act - Premier appel
        $player1 = $this->playerService->GetPlayer(1, false, true);
        // Deuxième appel avec cache
        $player2 = $this->playerService->GetPlayer(1, true, false);
        
        // Assert
        $this->assertSame($player1, $player2); // Même instance grâce au cache
    }

    #[Group('player-service')]
    public function testUpdateLastActionTime(): void
    {
        // Act - Ne devrait pas lever d'exception
        $this->playerService->updateLastActionTime();
        
        // Assert
        $this->assertTrue(true); // Test passe si pas d'exception
    }
}