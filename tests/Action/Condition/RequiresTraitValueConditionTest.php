<?php

namespace Tests\Action\Condition;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;
use App\Action\Condition\RequiresDistanceCondition;
use App\Action\Condition\RequiresTraitValueCondition;
use App\Action\Condition\RequiresWeaponTypeCondition;
use App\Action\Condition\ForbidIfHasEffectCondition;
use App\Action\Condition\AntiSpellCondition;
use App\Entity\ActionCondition;
use Tests\Action\Mock\PlayerMock;
use Tests\Action\Mock\ActionMock;

class RequiresTraitValueConditionTest extends TestCase
{
    private RequiresTraitValueCondition $condition;
    private PlayerMock $actor;

    protected function setUp(): void
    {
        $this->condition = new RequiresTraitValueCondition();
        $this->actor = new PlayerMock(1, 'Actor');
    }

    #[Group('conditions')]
    public function testSufficientTraitValue(): void
    {
        // Arrange
        $this->actor->setRemaining('pm', 10);
        $actionCondition = $this->createActionCondition(['pm' => 5]);
        
        // Act
        $result = $this->condition->check($this->actor, null, $actionCondition);
        
        // Assert
        $this->assertTrue($result->isSuccess());
    }

    #[Group('conditions')]
    public function testInsufficientTraitValue(): void
    {
        // Arrange
        $this->actor->setRemaining('pm', 3);
        $actionCondition = $this->createActionCondition(['pm' => 5]);
        
        // Act
        $result = $this->condition->check($this->actor, null, $actionCondition);
        
        // Assert
        $this->assertFalse($result->isSuccess());
        $this->assertContains('Pas assez de PM', $result->getConditionFailureMessages());
    }

    #[Group('conditions')]
    public function testMultipleTraitRequirements(): void
    {
        // Arrange
        $this->actor->setRemaining('pm', 10);
        $this->actor->setRemaining('a', 2);
        $actionCondition = $this->createActionCondition(['pm' => 5, 'a' => 1]);
        
        // Act
        $result = $this->condition->check($this->actor, null, $actionCondition);
        
        // Assert
        $this->assertTrue($result->isSuccess());
    }

    #[Group('conditions')]
    public function testPartiallyInsufficientTraits(): void
    {
        // Arrange
        $this->actor->setRemaining('pm', 10);
        $this->actor->setRemaining('a', 0);
        $actionCondition = $this->createActionCondition(['pm' => 5, 'a' => 1]);
        
        // Act
        $result = $this->condition->check($this->actor, null, $actionCondition);
        
        // Assert
        $this->assertFalse($result->isSuccess());
        $this->assertContains('Pas assez de A', $result->getConditionFailureMessages());
    }

    #[Group('conditions')]
    public function testApplyCosts(): void
    {
        // Arrange
        $this->actor->setRemaining('pm', 10);
        $actionCondition = $this->createActionCondition(['pm' => 5]);
        
        // Act
        $result = $this->condition->applyCosts($this->actor, null, $actionCondition);
        
        // Assert
        $this->assertContains('Vous avez dépensé 5 PM.', $result);
        $this->assertEquals(5, $this->actor->getRemaining('pm')); // 10 - 5
    }

    private function createActionCondition(array $parameters): ActionCondition
    {
        $condition = new ActionCondition();
        $condition->setParameters($parameters);
        $condition->setConditionType('RequiresTraitValue');
        return $condition;
    }
}