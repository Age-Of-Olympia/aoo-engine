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

class RequiresWeaponTypeConditionTest extends TestCase
{
    private RequiresWeaponTypeCondition $condition;
    private PlayerMock $actor;

    protected function setUp(): void
    {
        $this->condition = new RequiresWeaponTypeCondition();
        $this->actor = new PlayerMock(1, 'Actor');
    }

    #[Group('conditions')]
    public function testCorrectWeaponType(): void
    {
        // Arrange
        $this->actor->equipWeapon('melee', 'main1');
        $actionCondition = $this->createActionCondition(['type' => ['melee']]);
        
        // Act
        $result = $this->condition->check($this->actor, null, $actionCondition);
        
        // Assert
        $this->assertTrue($result->isSuccess());
    }

    #[Group('conditions')]
    public function testIncorrectWeaponType(): void
    {
        // Arrange
        $this->actor->equipWeapon('tir', 'main1');
        $actionCondition = $this->createActionCondition(['type' => ['melee']]);
        
        // Act
        $result = $this->condition->check($this->actor, null, $actionCondition);
        
        // Assert
        $this->assertFalse($result->isSuccess());
        $this->assertContains("Vous n'êtes pas équipé d'une arme de type melee.", $result->getConditionFailureMessages());
    }

    #[Group('conditions')]
    public function testMultipleAcceptableWeaponTypes(): void
    {
        // Arrange
        $this->actor->equipWeapon('jet', 'main1');
        $actionCondition = $this->createActionCondition(['type' => ['tir', 'jet']]);
        
        // Act
        $result = $this->condition->check($this->actor, null, $actionCondition);
        
        // Assert
        $this->assertTrue($result->isSuccess());
    }

    #[Group('conditions')]
    public function testAlternativeLocation(): void
    {
        // Arrange
        $this->actor->equipWeapon('bouclier', 'main2');
        $actionCondition = $this->createActionCondition([
            'type' => ['bouclier'], 
            'location' => ['main2']
        ]);
        
        // Act
        $result = $this->condition->check($this->actor, null, $actionCondition);
        
        // Assert
        $this->assertTrue($result->isSuccess());
    }

    private function createActionCondition(array $parameters): ActionCondition
    {
        $condition = new ActionCondition();
        $condition->setParameters($parameters);
        $condition->setConditionType('RequiresWeaponType');
        return $condition;
    }
}