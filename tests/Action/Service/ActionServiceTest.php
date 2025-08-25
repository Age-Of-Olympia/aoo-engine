<?php

namespace Tests\Service;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;
use App\Service\ActionService;
use App\Action\MeleeAction;
use App\Action\SpellAction;
use App\Interface\ActionInterface;
use Doctrine\DBAL\DriverManager;
use Tests\Action\Mock\TestDatabaseActions;

class ActionServiceTest extends TestCase
{
    private ActionService $actionService;
    private TestDatabaseActions $testDatabase;



    protected function setUp(): void
    {
        $this->testDatabase = new TestDatabaseActions();
        
        // Utiliser directement la connexion Doctrine DBAL de TestDatabaseActions
        $conn = $this->testDatabase->connection;
        
        $this->actionService = new ActionService($conn);
    }

    #[Group('action-service')]
    public function testGetActionByNameMelee(): void
    {
        // Arrange
        $actionName = 'melee';

        // Act
        $action = $this->actionService->getActionByName($actionName);
        
        // Assert
        $this->assertInstanceOf(ActionInterface::class, $action);
        $this->assertInstanceOf(MeleeAction::class, $action);
        $this->assertEquals('melee', $action->getOrmType());
    }

    #[Group('action-service')]
    public function testGetActionByNameSpell(): void
    {
        // Arrange
        $actionName = 'dmg1/pic_de_pierre';
        //$this->entityManager->setMockAction('spell', new SpellAction());
        
        // Act
        $action = $this->actionService->getActionByName($actionName);
        
        // Assert
        $this->assertInstanceOf(SpellAction::class, $action);
        $this->assertEquals('spell', $action->getOrmType());
    }

    #[Group('action-service')]
    public function testGetActionByNameNotFound(): void
    {
        // Arrange
        $actionName = 'nonexistent_action';
        
        // Act
        $action = $this->actionService->getActionByName($actionName);
        
        // Assert
        $this->assertNull($action);
    }

    #[Group('action-service')]
    public function testGetCostsArray(): void
    {
        // Arrange
        $action = new SpellAction();
        $action->setName('test_spell');
        
        // Ajouter une condition de coût
        $condition = new \App\Entity\ActionCondition();
        $condition->setConditionType('RequiresTraitValue');
        $condition->setParameters(['pm' => 5, 'a' => 1]);
        $action->addCondition($condition);
        
        // Act
        $costs = $this->actionService->getCostsArray(null, $action);
        
        // Assert
        $this->assertContains('5PM', $costs);
        $this->assertContains('1A', $costs);
    }

    #[Group('action-service')]
    public function testGetCostsArrayEmptyWhenNoRequirements(): void
    {
        // Arrange
        $action = new MeleeAction();
        
        // Act
        $costs = $this->actionService->getCostsArray(null, $action);
        
        // Assert
        $this->assertEmpty($costs);
    }

    #[Group('action-service')]
    public function testGetCostsArrayIgnoresEnergie(): void
    {
        // Arrange
        $action = new SpellAction();
        $condition = new \App\Entity\ActionCondition();
        $condition->setConditionType('RequiresTraitValue');
        $condition->setParameters(['energie' => 'both', 'pm' => 3]);
        $action->addCondition($condition);
        
        // Act
        $costs = $this->actionService->getCostsArray(null, $action);
        
        // Assert
        $this->assertContains('3PM', $costs);
        $this->assertNotContains('energie', $costs);
    }
}


namespace Tests\Service\Mock;

// Constants pour les tests
if (!defined('CARACS')) {
    define('CARACS', [
        'pm' => 'PM',
        'a' => 'A',
        'pv' => 'PV',
        'cc' => 'CC',
        'ct' => 'CT',
        'f' => 'F',
        'e' => 'E',
        'agi' => 'AGI',
        'fm' => 'FM',
        'm' => 'M',
        'r' => 'R',
        'mvt' => 'MVT',
        'p' => 'P'
    ]);
}