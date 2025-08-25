<?php

namespace Tests\Action\OutcomeInstruction;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;
use App\Action\OutcomeInstruction\HealingOutcomeInstruction;
use Tests\Action\Mock\PlayerMock;

class HealingOutcomeInstructionTest extends TestCase
{
    private HealingOutcomeInstruction $instruction;
    private PlayerMock $actor;
    private PlayerMock $target;

    protected function setUp(): void
    {
        $this->instruction = new HealingOutcomeInstruction();
        $this->actor = new PlayerMock(1, 'Healer');
        $this->target = new PlayerMock(2, 'Patient');
        
        // Configuration des stats
        $this->actor->setCarac('agi', 8);
        $this->target->setRemaining('pv', 10); // Blessé
    }

    #[Group('outcomes')]
    public function testBasicHealing(): void
    {
        // Arrange
        $this->instruction->setParameters([
            'actorHealingTrait' => 'agi'
        ]);
        
        // Act
        $result = $this->instruction->execute($this->actor, $this->target);
        
        // Assert
        $this->assertTrue($result->isSuccess());
        $this->assertEquals(8, $result->getTotalDamages()); // Total healing
        $this->assertContains('Vous soignez 8 points de vie à Patient.', $result->getOutcomeSuccessMessages());
        $this->assertEquals(18, $this->target->getRemaining('pv')); // 10 + 8
    }

    #[Group('outcomes')]
    public function testHealingWithBonus(): void
    {
        // Arrange
        $this->instruction->setParameters([
            'actorHealingTrait' => 'agi',
            'bonusHealingTrait' => 3
        ]);
        
        // Act
        $result = $this->instruction->execute($this->actor, $this->target);
        
        // Assert
        $this->assertEquals(11, $result->getTotalDamages()); // 8 + 3
        $this->assertEquals(21, $this->target->getRemaining('pv')); // 10 + 11
    }

    #[Group('outcomes')]
    public function testFixedHealing(): void
    {
        // Arrange
        $this->instruction->setParameters([
            'actorHealingTrait' => 50 // Valeur fixe
        ]);
        
        // Act
        $result = $this->instruction->execute($this->actor, $this->target);
        
        // Assert
        $this->assertEquals(50, $result->getTotalDamages());
        $this->assertContains('Valeur fixe à 50', $result->getOutcomeSuccessMessages());
    }

    #[Group('outcomes')]
    public function testPMHealing(): void
    {
        // Arrange
        $this->target->setRemaining('pm', 5);
        $this->instruction->setParameters([
            'actorPMHealingTrait' => 'agi'
        ]);
        
        // Act
        $result = $this->instruction->execute($this->actor, $this->target);
        
        // Assert
        $this->assertTrue($result->isSuccess());
        $this->assertContains('Vous rendez 8 points de mana à Patient.', $result->getOutcomeSuccessMessages());
        $this->assertEquals(13, $this->target->getRemaining('pm')); // 5 + 8
    }
}