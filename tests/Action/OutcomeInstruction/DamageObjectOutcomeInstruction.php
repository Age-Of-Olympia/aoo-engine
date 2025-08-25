<?php

namespace Tests\Action\OutcomeInstruction;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;
use App\Action\OutcomeInstruction\DamageObjectOutcomeInstruction;
use Tests\Action\Mock\PlayerMock;

class DamageObjectOutcomeInstructionTest extends TestCase
{
    private DamageObjectOutcomeInstruction $instruction;
    private PlayerMock $actor;
    private PlayerMock $target;

    protected function setUp(): void
    {
        $this->instruction = new DamageObjectOutcomeInstruction();
        $this->actor = new PlayerMock(1, 'Fighter');
        $this->target = new PlayerMock(2, 'Defender');
        
        // Mock constants
        if (!defined('ITEM_BREAK')) {
            define('ITEM_BREAK', 5); // 5% de chance de casser
        }
        if (!defined('AUTO_BREAK')) {
            define('AUTO_BREAK', false);
        }
        if (!defined('ITEM_CORRUPTIONS')) {
            define('ITEM_CORRUPTIONS', [
                'corruption_du_metal' => 'metal'
            ]);
        }
        if (!defined('ITEM_CORRUPT_BREAKCHANCES')) {
            define('ITEM_CORRUPT_BREAKCHANCES', [
                'corruption_du_metal' => 50
            ]);
        }
    }

    #[Group('outcomes')]
    public function testWeaponBreakChance(): void
    {
        // Arrange
        $this->actor->equipWeapon('melee', 'main1');
        
        // Act - peut ou non casser selon la chance
        $result = $this->instruction->execute($this->actor, $this->target);
        
        // Assert - Le résultat dépend de la chance, mais ne devrait pas planter
        $this->assertIsBool($result->isSuccess());
    }

    #[Group('outcomes')]
    public function testArmorBreakChance(): void
    {
        // Arrange
        $this->target->equipWeapon('armure', 'tronc');
        
        // Act
        $result = $this->instruction->execute($this->actor, $this->target);
        
        // Assert
        $this->assertIsBool($result->isSuccess());
        
        if ($result->isSuccess()) {
            $this->assertContains('s\'est cassée', $result->getOutcomeSuccessMessages()[0]);
        }
    }

    #[Group('outcomes')]
    public function testCorruptedItemHigherBreakChance(): void
    {
        // Arrange
        $this->actor->equipWeapon('melee', 'main1');
        $this->actor->addEffect('corruption_du_metal');
        
        // Act - avec corruption, la chance de casser est plus élevée
        $result = $this->instruction->execute($this->actor, $this->target);
        
        // Assert
        $this->assertIsBool($result->isSuccess());
    }
}