<?php

namespace Tests\Action\Condition;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;
use App\Action\Condition\ForbidIfHasEffectCondition;
use App\Entity\ActionCondition;
use Tests\Action\Mock\PlayerMock;

class ForbidIfHasEffectConditionTest extends TestCase
{
    private ForbidIfHasEffectCondition $condition;
    private PlayerMock $actor;
    private PlayerMock $target;

    protected function setUp(): void
    {
        $this->condition = new ForbidIfHasEffectCondition();
        $this->actor = new PlayerMock(1, 'Actor');
        $this->target = new PlayerMock(2, 'Target');
    }

    #[Group('conditions')]
    public function testNoEffectSuccess(): void
    {
        // Arrange
        $actionCondition = $this->createActionCondition(['actorEffect' => 'adrenaline']);
        
        // Act
        $result = $this->condition->check($this->actor, $this->target, $actionCondition);
        
        // Assert
        $this->assertTrue($result->isSuccess());
    }

    #[Group('conditions')]
    public function testActorEffectForbids(): void
    {
        // Arrange
        $this->actor->addEffect('adrenaline');
        $actionCondition = $this->createActionCondition(['actorEffect' => 'adrenaline']);
        
        // Act
        $result = $this->condition->check($this->actor, $this->target, $actionCondition);
        
        // Assert
        $this->assertFalse($result->isSuccess());
        $this->assertContains('Un effet empêche l\'action : adrenaline', $result->getConditionFailureMessages());
    }

    #[Group('conditions')]
    public function testTargetEffectForbids(): void
    {
        // Arrange
        $this->target->addEffect('paralysie');
        $actionCondition = $this->createActionCondition(['targetEffect' => 'paralysie']);
        
        // Act
        $result = $this->condition->check($this->actor, $this->target, $actionCondition);
        
        // Assert
        $this->assertFalse($result->isSuccess());
        $this->assertContains('Un effet empêche l\'action : paralysie', $result->getConditionFailureMessages());
    }

    #[Group('conditions')]
    public function testMultipleEffectsArray(): void
    {
        // Arrange
        $this->target->addEffect('corruption_du_metal');
        $actionCondition = $this->createActionCondition([
            'targetEffects' => ['corruption_du_metal', 'corruption_du_bronze', 'corruption_du_cuir']
        ]);
        
        // Act
        $result = $this->condition->check($this->actor, $this->target, $actionCondition);
        
        // Assert
        $this->assertFalse($result->isSuccess());
        $this->assertContains('Un effet empêche l\'action : corruption_du_metal', $result->getConditionFailureMessages());
    }

    private function createActionCondition(array $parameters): ActionCondition
    {
        $condition = new ActionCondition();
        $condition->setParameters($parameters);
        $condition->setConditionType('ForbidIfHasEffect');
        return $condition;
    }
}