<?php

namespace Tests\Service;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;
use App\Service\PlayerService;
use App\Interface\ActorInterface;
use App\Service\ActionService;

// Test de performance et robustesse
class ServicePerformanceTest extends TestCase
{
    #[Group('performance')]
    public function testActionServicePerformance(): void
    {
        // Arrange
        $actionService = new ActionService();
        $startTime = microtime(true);
        
        // Act - Récupérer plusieurs actions
        $actions = ['melee', 'distance', 'pic_de_pierre', 'lame_volante'];
        foreach ($actions as $actionName) {
            $actionService->getActionByName($actionName);
        }
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        
        // Assert - Ne devrait pas prendre plus de 1 seconde
        $this->assertLessThan(1.0, $executionTime);
    }

    #[Group('performance')]
    public function testPlayerServiceCacheEfficiency(): void
    {
        // Arrange
        $playerService = new PlayerService(1);
        
        // Act - Premier accès (mise en cache)
        $startTime = microtime(true);
        $player1 = $playerService->GetPlayer(1);
        $firstCallTime = microtime(true) - $startTime;
        
        // Deuxième accès (depuis le cache)
        $startTime = microtime(true);
        $player2 = $playerService->GetPlayer(1);
        $secondCallTime = microtime(true) - $startTime;
        
        // Assert - Le deuxième appel devrait être plus rapide
        $this->assertLessThanOrEqual($firstCallTime, $secondCallTime);
        $this->assertSame($player1, $player2);
    }

    #[Group('robustness')]
    public function testActionServiceRobustness(): void
    {
        // Arrange
        $actionService = new ActionService();
        
        // Act & Assert - Tests avec des données invalides
        $this->assertNull($actionService->getActionByName(''));
        
        // Test avec des caractères spéciaux
        $this->assertNull($actionService->getActionByName('action<script>'));
        $this->assertNull($actionService->getActionByName('action; DROP TABLE actions;'));
    }

    #[Group('robustness')]
    public function testPlayerServiceRobustness(): void
    {
        // Act & Assert - Test avec des IDs invalides
        $this->expectException(\Exception::class);
        new PlayerService(0); // ID invalide
    }
}