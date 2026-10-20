<?php

namespace Tests\Various;

use Classes\Player;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * The first player, reference of the catch-up XP bonus, is taken among the
 * races offered at registration: no other character, however high its XP.
 */
class FirstPlayerReferenceTest extends LegacyPlayerFixtureTestCase
{
    private const ID = 89990001;

    public function testACharacterOfAnUnregisterableRaceIsNeverTheFirstPlayer(): void
    {
        $this->link->executeStatement('DELETE FROM progression WHERE player_id = ?', [self::ID]);
        $this->link->executeStatement('DELETE FROM players WHERE id = ?', [self::ID]);
        $this->link->executeStatement(
            "INSERT INTO players (id, name, race, player_type) VALUES (?, 'Gm premier', 'gm_race_non_proposee', 'real')",
            [self::ID]
        );
        $this->link->executeStatement('INSERT INTO progression (player_id, xp) VALUES (?, 99999999)', [self::ID]);

        try {
            $list = Player::get_player_list();

            $this->assertContains(self::ID, array_map(static fn (object $p): int => (int) $p->id, $list->list));
            $this->assertNotSame(self::ID, (int) ($list->first->id ?? 0));
        } finally {
            $this->link->executeStatement('DELETE FROM progression WHERE player_id = ?', [self::ID]);
            $this->link->executeStatement('DELETE FROM players WHERE id = ?', [self::ID]);
        }
    }
}
