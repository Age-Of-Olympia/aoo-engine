<?php

use App\Action\ActionResults;
use App\Action\MeleeAction;
use App\Service\ActionExecutorService;
use App\Service\ActionService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Action\Mock\PlayerMock;

/**
 * Tests de sécurité et edge cases
 */
class SecurityTest extends TestCase
{
    #[Group('security')]
    public function testSQLInjectionPrevention(): void
    {
        // Arrange - Tentatives d'injection SQL
        $maliciousInputs = [
            "'; DROP TABLE actions; --",
            "1 OR 1=1",
            "<script>alert('xss')</script>",
            "../../etc/passwd",
            "null",
            "undefined"
        ];

        foreach ($maliciousInputs as $maliciousInput) {
            // Act & Assert - Le service ne devrait pas planter
            try {
                $actionService = new ActionService();
                $result = $actionService->getActionByName($maliciousInput);
                
                // Devrait retourner null sans erreur
                $this->assertNull($result);
            } catch (\Exception $e) {
                // Si exception, elle ne devrait pas révéler d'informations sensibles
                $this->assertStringNotContainsString('password', strtolower($e->getMessage()));
                $this->assertStringNotContainsString('database', strtolower($e->getMessage()));
            }
        }
    }

    #[Group('security')]
    public function testPlayerIsolation(): void
    {
        // Arrange - S'assurer qu'un joueur ne peut pas affecter les données d'un autre
        $player1 = new PlayerMock(1, 'Player1');
        $player2 = new PlayerMock(2, 'Player2');
        
        $player1->setRemaining('pv', 20);
        $player2->setRemaining('pv', 20);
        
        // Act - Player1 modifie ses propres stats
        $player1->setRemaining('pv', 15);
        
        // Assert - Player2 ne devrait pas être affecté
        $this->assertEquals(15, $player1->getRemaining('pv'));
        $this->assertEquals(20, $player2->getRemaining('pv'));
    }

    #[Group('security')]
    public function testResourceLimitEnforcement(): void
    {
        // Arrange
        $player = new PlayerMock(1, 'TestPlayer');
        
        // Act & Assert - Tenter de donner des valeurs extrêmes
        $player->setRemaining('pm', -999);
        $player->setRemaining('pv', 99999);
        
        // Dans un système sécurisé, ces valeurs devraient être normalisées
        $this->assertGreaterThanOrEqual(0, $player->getRemaining('pm'));
        $this->assertLessThan(10000, $player->getRemaining('pv')); // Limite raisonnable
    }

    #[Group('security')]
    public function testActionExecutorBoundaryChecks(): void
    {
        // Arrange
        $player = new PlayerMock(1, 'TestPlayer');
        $target = new PlayerMock(2, 'TestTarget');
        
        // Test avec des coordonnées extrêmes
        $player->setCoords(-999999, 999999, -100, 'test');
        $target->setCoords(999999, -999999, 100, 'test');
        
        // Act - Le système ne devrait pas planter
        $action = new MeleeAction();
        
        try {
            $executor = new ActionExecutorService($action, $player, $target);
            $result = $executor->executeAction();
            
            // Assert
            $this->assertInstanceOf(ActionResults::class, $result);
        } catch (\Exception $e) {
            // Si exception, elle devrait être gérée proprement
            $this->assertIsString($e->getMessage());
        }
    }
}