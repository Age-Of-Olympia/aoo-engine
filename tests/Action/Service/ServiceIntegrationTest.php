<?php

namespace Tests\Service;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;
use App\Service\ActionService;
use App\Service\PlayerService;
use App\Interface\ActionInterface;
use App\Interface\ActorInterface;

// Tests d'intégration des services
class ServiceIntegrationTest extends TestCase
{
    #[Group('service-integration')]
    public function testActionServicePlayerServiceIntegration(): void
    {
        // Arrange
        $actionService = new ActionService();
        $playerService = new PlayerService(1);
        
        // Act - Récupérer une action et un joueur
        $action = $actionService->getActionByName('melee');
        $player = $playerService->GetPlayer(1);
        
        // Assert
        if ($action !== null) {
            $this->assertInstanceOf(ActionInterface::class, $action);
            $costs = $actionService->getCostsArray('melee', $action);
            $this->assertIsArray($costs);
        }
        
        $this->assertInstanceOf(ActorInterface::class, $player);
    }

    #[Group('service-integration')]
    public function testPlayerServiceSpellManagement(): void
    {
        // Arrange
        $playerService = new PlayerService(1);
        
        // Act
        $spellsAvailable = $playerService->getNumberOfSpellAvailable();
        
        // Assert
        $this->assertIsInt($spellsAvailable);
        $this->assertGreaterThanOrEqual(0, $spellsAvailable);
    }

    #[Group('service-integration')]
    public function testActionCostsCalculation(): void
    {
        // Arrange
        $actionService = new ActionService();
        $playerService = new PlayerService(1);
        
        // Act
        $costs = $actionService->getCostsArray('pic_de_pierre', null);
        
        // Assert
        $this->assertIsArray($costs);
        // Si l'action existe et a des coûts, vérifier la structure
        if (!empty($costs)) {
            foreach ($costs as $cost) {
                $this->assertIsString($cost);
                $this->assertMatchesRegularExpression('/\d+[A-Z]+/', $cost); // Format "5PM", "1A", etc.
            }
        }
    }
}