<?php

namespace Tests\Action\OutcomeInstruction;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;
use App\Action\OutcomeInstruction\LifeLossOutcomeInstruction;
use Tests\Action\Mock\PlayerMock;

class LifeLossOutcomeInstructionTest extends TestCase
{
    private LifeLossOutcomeInstruction $instruction;
    private PlayerMock $actor;
    private PlayerMock $target;

    protected function setUp(): void
    {
        $this->instruction = new LifeLossOutcomeInstruction();
        $this->actor = new PlayerMock(1, 'Attacker');
        $this->target = new PlayerMock(2, 'Defender');
        
        // Configuration des stats de base
        $this->actor->setCarac('f', 10);
        $this->target->setCarac('e', 5);
        $this->target->setRemaining('pv', 20);
    }

    #[Group('outcomes')]
    public function testBasicDamageCalculation(): void
    {
        // Arrange
        $this->instruction->setParameters([
            'actorDamagesTrait' => 'f',
            'targetDamagesTrait' => 'e'
        ]);
        
        // Act
        $result = $this->instruction->execute($this->actor, $this->target);
        
        // Assert
        $this->assertTrue($result->isSuccess());
        $this->assertEquals(5, $result->getTotalDamages()); // 10 - 5 = 5
        $this->assertContains('Vous infligez 5 dégâts à Defender.', $result->getOutcomeSuccessMessages());
        $this->assertEquals(15, $this->target->getRemaining('pv')); // 20 - 5 = 15
    }

    #[Group('outcomes')]
    public function testDamageWithBonus(): void
    {
        // Arrange
        $this->actor->setCarac('m', 8);
        $this->target->setCarac('m', 3);
        
        $this->instruction->setParameters([
            'actorDamagesTrait' => 'f',
            'targetDamagesTrait' => 'e',
            'bonusDamagesTrait' => 'm',
            'bonusDefenseTrait' => 'm'
        ]);
        
        // Act
        $result = $this->instruction->execute($this->actor, $this->target);
        
        // Assert
        $this->assertTrue($result->isSuccess());
        // (10 - 5) + (8 - 3) = 5 + 5 = 10
        $this->assertEquals(10, $result->getTotalDamages());
        $this->assertEquals(10, $this->target->getRemaining('pv')); // 20 - 10 = 10
    }

    #[Group('outcomes')]
    public function testDistanceDamageReduction(): void
    {
        // Arrange
        $this->actor->setCoords(0, 0, 0, 'test');
        $this->target->setCoords(3, 0, 0, 'test'); // Distance = 3
        
        $this->instruction->setParameters([
            'actorDamagesTrait' => 'f',
            'targetDamagesTrait' => 'e',
            'distance' => true
        ]);
        
        // Act
        $result = $this->instruction->execute($this->actor, $this->target);
        
        // Assert
        // 10 - 5 - 2 (distance - 1) = 3
        $this->assertEquals(3, $result->getTotalDamages());
    }

    #[Group('outcomes')]
    public function testMinimumDamage(): void
    {
        // Arrange - Défenseur avec très haute défense
        $this->target->setCarac('e', 15);
        
        $this->instruction->setParameters([
            'actorDamagesTrait' => 'f',
            'targetDamagesTrait' => 'e'
        ]);
        
        // Act
        $result = $this->instruction->execute($this->actor, $this->target);
        
        // Assert - Toujours 1 dégât minimum
        $this->assertEquals(1, $result->getTotalDamages());
        $this->assertEquals(19, $this->target->getRemaining('pv'));
    }

    #[Group('outcomes')]
    public function testIgnoreArmor(): void
    {
        // Arrange - Équiper une armure au défenseur
        $this->target->equipWeapon('armure', 'tronc');
        
        $this->instruction->setParameters([
            'actorDamagesTrait' => 'f',
            'targetDamagesTrait' => 'e',
            'targetIgnore' => ['tronc']
        ]);
        
        // Act
        $result = $this->instruction->execute($this->actor, $this->target);
        
        // Assert
        $this->assertTrue($result->isSuccess());
        $this->assertEquals(5, $result->getTotalDamages());
    }

    #[Group('outcomes')]
    public function testAutoCrit(): void
    {
        // Arrange
        $this->instruction->setParameters([
            'actorDamagesTrait' => 'f',
            'targetDamagesTrait' => 'e',
            'autoCrit' => true
        ]);
        
        // Act
        $result = $this->instruction->execute($this->actor, $this->target);
        
        // Assert - Base damage + 3 critique
        $this->assertEquals(8, $result->getTotalDamages()); // 5 + 3
        $this->assertContains('Critique ! Dégâts augmentés ! +3 !', $result->getOutcomeSuccessMessages());
    }
}