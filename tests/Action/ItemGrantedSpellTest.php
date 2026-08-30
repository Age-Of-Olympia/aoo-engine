<?php

namespace Tests\Action;

use App\Service\ItemGrantedSpellService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * A worn item lends its spell (`items.spell`).
 *
 * The grant is READ from the equipment, never written to players_actions.
 * That is what keeps it out of the NUMBER_MAX_COMP count — a count of
 * learned rows — and what makes it leave with the item, with nothing to
 * clean up when the item is dropped, banked, broken or looted.
 */
#[Group('items-baseline')]
class ItemGrantedSpellTest extends LegacyPlayerFixtureTestCase
{
    private function spellNameOrSkip(): string
    {
        $names = array_keys((new \App\Service\ActionService())->getCastableSpellNames());
        if ($names === []) {
            $this->markTestSkipped('no castable spell in the actions catalog.');
        }

        return $names[0];
    }

    private function bearerWith(string $itemName, ?string $spell): array
    {
        $player = $this->createRealPlayer('GmPorteur');
        $player->get_data();

        $item = $this->sowCatalogItem($itemName, [
            'type' => 'equipement',
            'emplacement' => 'main2',
            'stats_in_db' => 1,
            'spell' => $spell,
        ]);
        $item->get_data();
        $item->add_item($player, 1);
        $this->link->executeStatement(
            'UPDATE players_items SET equiped = 1 WHERE player_id = ? AND item_id = ?',
            [$player->id, $item->id]
        );

        return [$player, $item];
    }

    public function testAWornItemLendsItsSpell(): void
    {
        $spell = $this->spellNameOrSkip();
        [$player] = $this->bearerWith('zz_baguette_' . bin2hex(random_bytes(3)), $spell);

        $granted = (new ItemGrantedSpellService())->forPlayer($player);

        $this->assertArrayHasKey($spell, $granted, 'the worn item lends its spell');
        $this->assertContains($spell, $player->get_actions(), 'and it shows among usable actions');
        $this->assertSame(1, $player->have_spell($spell), 'have_spell sees a borrowed spell');
    }

    /** The cap counts learned rows; a borrowed spell is not one. */
    public function testABorrowedSpellIsNotALearnedRow(): void
    {
        $spell = $this->spellNameOrSkip();
        [$player] = $this->bearerWith('zz_anneau_' . bin2hex(random_bytes(3)), $spell);

        $this->assertNotContains(
            $spell,
            $player->get_spells(),
            'get_spells reads players_actions: a borrowed spell must not appear there'
        );
        $this->assertSame(
            0,
            (int) $this->link->fetchOne(
                'SELECT COUNT(*) FROM players_actions WHERE player_id = ? AND name = ?',
                [$player->id, $spell]
            ),
            'nothing is written to players_actions'
        );
    }

    /** An item naming a spell the catalogue does not know lends nothing. */
    public function testAnUnknownSpellNameLendsNothing(): void
    {
        [$player] = $this->bearerWith('zz_relique_' . bin2hex(random_bytes(3)), 'sort_qui_nexiste_pas');

        $this->assertSame([], (new ItemGrantedSpellService())->forPlayer($player));
    }

    /** An item with no spell lends nothing. */
    public function testAnItemWithoutASpellLendsNothing(): void
    {
        [$player] = $this->bearerWith('zz_caillou_' . bin2hex(random_bytes(3)), null);

        $this->assertSame([], (new ItemGrantedSpellService())->forPlayer($player));
    }
}
