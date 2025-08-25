<?php

namespace Tests\Action\OutcomeInstruction;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;
use App\Action\OutcomeInstruction\ApplyStatusOutcomeInstruction;
use Tests\Action\Mock\PlayerMock;

class ApplyStatusOutcomeInstructionTest extends TestCase
{
    private ApplyStatusOutcomeInstruction $instruction;
    private PlayerMock $actor;
    private PlayerMock $target;

    protected function setUp(): void
    {
        $this->instruction = new ApplyStatusOutcomeInstruction();
        $this->actor = new PlayerMock(1, 'Caster');
        $this->target = new PlayerMock(2, 'Target');
        
        // Mock constants si pas définies
        if (!defined('EFFECTS_RA_FONT')) {
            define('EFFECTS_RA_FONT', [
                'adrenaline' => 'ra-heart',
                'paralysie' => 'ra-frozen-orb'
            ]);
        }
    }

    #[Group('outcomes')]
    public function testApplyEffectToActor(): void
    {
        // Arrange
        $this->instruction->setParameters([
            'adrenaline' => true,
            'duration' => 3600,
            'player' => 'actor'
        ]);
        
        // Act
        $result = $this->instruction->execute($this->actor, $this->target);
        
        // Assert
        $this->assertTrue($result->isSuccess());
        $this->assertEquals(1, $this->actor->haveEffect('adrenaline'));
        $this->assertContains('L\'effet adrenaline', $result->getOutcomeSuccessMessages()[0]);
        $this->assertContains('à Caster', $result->getOutcomeSuccessMessages()[0]);
    }

    #[Group('outcomes')]
    public function testApplyEffectToTarget(): void
    {
        // Arrange
        $this->instruction->setParameters([
            'paralysie' => true,
            'duration' => 1800,
            'player' => 'target'
        ]);
        
        // Act
        $result = $this->instruction->execute($this->actor, $this->target);
        
        // Assert
        $this->assertTrue($result->isSuccess());
        $this->assertEquals(1, $this->target->haveEffect('paralysie'));
        $this->assertContains('à Target', $result->getOutcomeSuccessMessages()[0]);
    }

    #[Group('outcomes')]
    public function testApplyEffectToBoth(): void
    {
        // Arrange
        $this->instruction->setParameters([
            'adrenaline' => true,
            'duration' => 3600
            // Pas de 'player' spécifié = both par défaut
        ]);
        
        // Act
        $result = $this->instruction->execute($this->actor, $this->target);
        
        // Assert
        $this->assertTrue($result->isSuccess());
        $this->assertEquals(1, $this->actor->haveEffect('adrenaline'));
        $this->assertEquals(1, $this->target->haveEffect('adrenaline'));
        $this->assertCount(2, $result->getOutcomeSuccessMessages());
    }

    #[Group('outcomes')]
    public function testPurgeEffects(): void
    {
        // Arrange
        $this->actor->addEffect('poison');
        $this->actor->addEffect('paralysie');
        
        $this->instruction->setParameters([
            'finished' => true,
            'player' => 'actor'
        ]);
        
        // Act
        $result = $this->instruction->execute($this->actor, $this->target);
        
        // Assert
        $this->assertTrue($result->isSuccess());
        $this->assertContains('effet(s) terminé(s)', $result->getOutcomeSuccessMessages()[0]);
    }
}