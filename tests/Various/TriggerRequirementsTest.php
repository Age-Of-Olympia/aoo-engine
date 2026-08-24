<?php

namespace Tests\Various;

use App\Service\Map\TriggerRequirements;
use PHPUnit\Framework\TestCase;

/**
 * The access condition shared by the `need` trigger and by `tp`.
 *
 * A `need` and a `tp` on the same cell make a guarded door. Since the trigger
 * layer became a tile layer, a cell shows one trigger only, so `tp` takes the
 * condition as its fifth parameter instead. Both must refuse identically.
 */
class TriggerRequirementsTest extends TestCase
{
    /** No condition: nothing to satisfy. */
    public function testAnEmptyConditionAsksForNothing(): void
    {
        $player = $this->player([], []);

        $this->assertTrue(TriggerRequirements::met($player, ''));
    }

    /** A missing spell closes the passage, a known one opens it. */
    public function testASpellIsRequired(): void
    {
        $this->assertFalse(TriggerRequirements::met($this->player([], []), 'spell:feu'));
        $this->assertTrue(TriggerRequirements::met($this->player([], ['feu']), 'spell:feu'));
    }

    /** Terms are cumulative. */
    public function testEveryTermMustHold(): void
    {
        $player = $this->player([], ['feu']);

        $this->assertFalse(
            TriggerRequirements::met($player, 'spell:feu,spell:glace'),
            'one spell out of two is not enough'
        );
        $this->assertTrue(TriggerRequirements::met($player, 'spell:feu,spell:feu'));
    }

    /** An unknown term is ignored: a typo must not wall a cell. */
    public function testAnUnknownTermDoesNotWallTheCell(): void
    {
        $this->assertTrue(TriggerRequirements::met($this->player([], []), 'sortilege:feu'));
    }

    /**
     * tp.php reads "x,y,z,plan[,condition]" with explode(..., 5): everything
     * past the fourth separator is the condition, commas included.
     */
    public function testTheConditionOfATpKeepsItsCommas(): void
    {
        $parts = explode(',', 'x,y,-1,nidhogg,item:clef:1,spell:feu', 5);

        $this->assertSame('nidhogg', $parts[3], 'the plan stays fourth');
        $this->assertSame('item:clef:1,spell:feu', $parts[4], 'the condition stays whole');

        $legacy = explode(',', 'x,y,-1,nidhogg', 5);
        $this->assertArrayNotHasKey(4, $legacy, 'an older tp requires nothing');
    }

    /**
     * Fixture player: its spells, nothing else. Item::get_n() hits the
     * database; the item term is covered by the inventory tests.
     *
     * @param list<string> $items  unused here, kept for readability
     * @param list<string> $spells
     */
    private function player(array $items, array $spells): \Classes\Player
    {
        return new class ($spells) extends \Classes\Player {
            /** @param list<string> $spells */
            public function __construct(private array $spells)
            {
            }

            public function have_spell($name): bool
            {
                return in_array($name, $this->spells, true);
            }
        };
    }
}
