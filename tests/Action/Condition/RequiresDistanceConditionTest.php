<?php

namespace Tests\Action\Condition;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;
use App\Action\Condition\RequiresDistanceCondition;
use App\Entity\ActionCondition;
use Tests\Action\Mock\PlayerMock;

class RequiresDistanceConditionTest extends TestCase
{
    private RequiresDistanceCondition $condition;
    private PlayerMock $actor;
    private PlayerMock $target;

    protected function setUp(): void
    {
        $this->condition = new RequiresDistanceCondition();
        $this->actor = new PlayerMock(1, 'Actor');
        $this->target = new PlayerMock(2, 'Target');
        
        // Position par défaut : acteur en (0,0,0) et cible en (2,2,0) = distance 2
        $this->actor->setCoords(0, 0, 0, 'test_plan');
        $this->target->setCoords(2, 2, 0, 'test_plan');
    }

    #[Group('conditions')]
    public function testMaxDistanceSuccess(): void
    {
        // Arrange - distance max 3, distance réelle 2
        $actionCondition = $this->createActionCondition(['max' => 3]);
        
        // Act
        $result = $this->condition->check($this->actor, $this->target, $actionCondition);
        
        // Assert
        $this->assertTrue($result->isSuccess());
    }

    #[Group('conditions')]
    public function testMaxDistanceFailure(): void
    {
        // Arrange - distance max 1, distance réelle 2
        $actionCondition = $this->createActionCondition(['max' => 1]);
        
        // Act
        $result = $this->condition->check($this->actor, $this->target, $actionCondition);
        
        // Assert
        $this->assertFalse($result->isSuccess());
        $this->assertContains('La cible est trop loin !', $result->getConditionFailureMessages());
    }

    #[Group('conditions')]
    public function testMinDistanceSuccess(): void
    {
        // Arrange - distance min 1, distance réelle 2
        $actionCondition = $this->createActionCondition(['min' => 1]);
        
        // Act
        $result = $this->condition->check($this->actor, $this->target, $actionCondition);
        
        // Assert
        $this->assertTrue($result->isSuccess());
    }

    #[Group('conditions')]
    public function testMinDistanceFailure(): void
    {
        // Arrange - distance min 5, distance réelle 2
        $actionCondition = $this->createActionCondition(['min' => 5]);
        
        // Act
        $result = $this->condition->check($this->actor, $this->target, $actionCondition);
        
        // Assert
        $this->assertFalse($result->isSuccess());
        $this->assertContains('La cible est trop proche !', $result->getConditionFailureMessages());
    }

    #[Group('conditions')]
    public function testRangeDistanceSuccess(): void
    {
        // Arrange - distance entre 1 et 5, distance réelle 2
        $actionCondition = $this->createActionCondition(['min' => 1, 'max' => 5]);
        
        // Act
        $result = $this->condition->check($this->actor, $this->target, $actionCondition);
        
        // Assert
        $this->assertTrue($result->isSuccess());
    }

    #[Group('conditions')]
    public function testNoTargetFailure(): void
    {
        // Arrange
        $actionCondition = $this->createActionCondition(['max' => 1]);
        
        // Act
        $result = $this->condition->check($this->actor, null, $actionCondition);
        
        // Assert
        $this->assertFalse($result->isSuccess());
        $this->assertContains("Aucune cible n'a été spécifiée.", $result->getConditionFailureMessages());
    }

    private function createActionCondition(array $parameters): ActionCondition
    {
        $condition = new ActionCondition();
        $condition->setParameters($parameters);
        $condition->setConditionType('RequiresDistance');
        return $condition;
    }
}