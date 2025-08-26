<?php

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;
use App\Action\SpellAction;
use App\Action\TechniqueAction;
use Tests\Action\Mock\PlayerMock;

class SpellActionTest extends TestCase
{
    private SpellAction $spellAction;
    private PlayerMock $caster;
    private PlayerMock $target;

    protected function setUp(): void
    {
        $this->spellAction = new SpellAction();
        $this->spellAction->setDisplayName('Boule de Feu');
        
        $this->caster = new PlayerMock(1, 'Mage');
        $this->target = new PlayerMock(2, 'Target');
    }

    #[Group('spell-action')]
    public function testSpellLogMessages(): void
    {
        // Act
        $logs = $this->spellAction->getLogMessages($this->caster, $this->target);
        
        // Assert
        $this->assertContains('Mage a lancé Boule de Feu sur Target', $logs['actor']);
        $this->assertContains('Target a été attaqué par Mage avec Boule de Feu', $logs['target']);
    }

    #[Group('spell-action')]
    public function testSpellInheritsFromTechniqueAction(): void
    {
        // Assert
        $this->assertInstanceOf(TechniqueAction::class, $this->spellAction);
    }
}