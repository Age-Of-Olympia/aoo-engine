<?php

namespace Tests\Action\Condition;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;
use App\Action\Condition\AntiSpellCondition;
use App\Entity\ActionCondition;
use Tests\Action\Mock\PlayerMock;

class AntiSpellConditionTest extends TestCase
{
    private AntiSpellCondition $condition;
    private PlayerMock $actor;

    protected function setUp(): void
    {
        $this->condition = new AntiSpellCondition();
        $this->actor = new PlayerMock(1, 'Actor');
    }

    #[Group('conditions')]
    public function testNoSpellMalusSuccess(): void
    {
        // Arrange
        $this->actor->equipItemWithoutSpellMalus('main1');
        $actionCondition = $this->createActionCondition([]);
        
        // Act
        $result = $this->condition->check($this->actor, null, $actionCondition);
        
        // Assert
        $this->assertTrue($result->isSuccess());
    }

    #[Group('conditions')]
    public function testSpellMalusForbids(): void
    {
        // Arrange
        $this->actor->equipItemWithSpellMalus('main1', 'Armure Lourde');
        $actionCondition = $this->createActionCondition([]);
        
        // Act
        $result = $this->condition->check($this->actor, null, $actionCondition);
        
        // Assert
        $this->assertFalse($result->isSuccess());
        $this->assertTrue($actionCondition->isBlocking());
        $this->assertContains('Armure Lourde empêche la magie.', $result->getConditionFailureMessages());
    }

    #[Group('conditions')]
    public function testMultipleEquipmentWithSpellMalus(): void
    {
        // Arrange
        $this->actor->equipItemWithSpellMalus('main1', 'Épée Enchantée');
        $this->actor->equipItemWithSpellMalus('tronc', 'Armure Lourde');
        $actionCondition = $this->createActionCondition([]);
        
        // Act
        $result = $this->condition->check($this->actor, null, $actionCondition);
        
        // Assert
        $this->assertFalse($result->isSuccess());
        $this->assertCount(2, $result->getConditionFailureMessages());
    }

    private function createActionCondition(array $parameters): ActionCondition
    {
        $condition = new ActionCondition();
        $condition->setParameters($parameters);
        $condition->setConditionType('AntiSpell');
        return $condition;
    }
}