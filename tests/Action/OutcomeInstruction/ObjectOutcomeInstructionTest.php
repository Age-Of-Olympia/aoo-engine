<?php

namespace Tests\Action\OutcomeInstruction;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;
use App\Action\OutcomeInstruction\ObjectOutcomeInstruction;
use Tests\Action\Mock\PlayerMock;

class ObjectOutcomeInstructionTest extends TestCase
{
    private ObjectOutcomeInstruction $instruction;
    private PlayerMock $actor;
    private PlayerMock $target;

    protected function setUp(): void
    {
        $this->instruction = new ObjectOutcomeInstruction();
        $this->actor = new PlayerMock(1, 'Thief');
        $this->target = new PlayerMock(2, 'Victim');
        
        // Mock constants
        if (!defined('MIN_GOLD_STOLEN')) {
            define('MIN_GOLD_STOLEN', 5);
        }
    }

    #[Group('outcomes')]
    public function testStealGold(): void
    {
        // Arrange
        $this->target->setRemaining('gold', 100); // Target a 100 or
        $this->instruction->setParameters([
            'action' => 'steal',
            'object' => 1 // ID de l'or
        ]);
        
        // Act
        $result = $this->instruction->execute($this->actor, $this->target);
        
        // Assert
        $this->assertTrue($result->isSuccess());
        $this->assertContains('Vous obtenez', $result->getOutcomeSuccessMessages()[0]);
        $this->assertContains('Po grâce à votre larcin sur Victim', $result->getOutcomeSuccessMessages()[0]);
        
        // Le montant volé doit être d'au moins MIN_GOLD_STOLEN
        $this->assertGreaterThanOrEqual(MIN_GOLD_STOLEN, $result->getTotalDamages());
    }

    #[Group('outcomes')]
    public function testStealFromPoorTarget(): void
    {
        // Arrange - Target avec très peu d'or
        $this->target->setRemaining('gold', 2);
        $this->instruction->setParameters([
            'action' => 'steal',
            'object' => 1
        ]);
        
        // Act
        $result = $this->instruction->execute($this->actor, $this->target);
        
        // Assert
        $this->assertTrue($result->isSuccess());
        // Devrait quand même recevoir MIN_GOLD_STOLEN
        $this->assertEquals(MIN_GOLD_STOLEN, $result->getTotalDamages());
    }
}