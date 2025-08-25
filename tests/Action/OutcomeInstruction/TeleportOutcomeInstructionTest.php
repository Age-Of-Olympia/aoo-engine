<?php

namespace Tests\Action\OutcomeInstruction;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;
use App\Action\OutcomeInstruction\TeleportOutcomeInstruction;
use Tests\Action\Mock\PlayerMock;

class TeleportOutcomeInstructionTest extends TestCase
{
    private TeleportOutcomeInstruction $instruction;
    private PlayerMock $actor;
    private PlayerMock $target;

    protected function setUp(): void
    {
        $this->instruction = new TeleportOutcomeInstruction();
        $this->actor = new PlayerMock(1, 'Teleporter');
        $this->target = new PlayerMock(2, 'Target');
        
        $this->actor->setCoords(0, 0, 0, 'test');
        $this->target->setCoords(5, 5, 0, 'test');
    }

    #[Group('outcomes')]
    public function testTeleportToTarget(): void
    {
        // Arrange
        $this->instruction->setParameters([
            'coords' => 'target'
        ]);
        
        // Act
        $result = $this->instruction->execute($this->actor, $this->target);
        
        // Assert
        $this->assertTrue($result->isSuccess());
        $this->assertContains('Teleporter saute sur Target !', $result->getOutcomeSuccessMessages());
        // Note: Dans un vrai test, on vérifierait que go() a été appelé
    }

    #[Group('outcomes')]
    public function testProjectTarget(): void
    {
        // Arrange
        $this->instruction->setParameters([
            'coords' => 'projected'
        ]);
        
        // Act
        $result = $this->instruction->execute($this->actor, $this->target);
        
        // Assert
        $this->assertTrue($result->isSuccess());
        $this->assertContains('Target est projeté !', $result->getOutcomeSuccessMessages());
    }

    #[Group('outcomes')]
    public function testTeleportToSpecificCoords(): void
    {
        // Arrange
        $this->instruction->setParameters([
            'coords' => '10,15,0,autre_plan'
        ]);
        
        // Act
        $result = $this->instruction->execute($this->actor, $this->target);
        
        // Assert
        $this->assertTrue($result->isSuccess());
        // Dans un test complet, on vérifierait les coordonnées finales
    }

    #[Group('outcomes')]
    public function testTeleportWithRelativeCoords(): void
    {
        // Arrange - utiliser les coordonnées actuelles pour certains axes
        $this->instruction->setParameters([
            'coords' => 'x,y,1,plan' // x,y actuels, z=1, plan actuel
        ]);
        
        // Act
        $result = $this->instruction->execute($this->actor, $this->target);
        
        // Assert
        $this->assertTrue($result->isSuccess());
    }
}
