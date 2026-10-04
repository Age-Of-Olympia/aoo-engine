<?php

namespace Tests\Player;

use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Death purges the wounds (players_bonus) and the effects. One DELETE joined
 * the two tables: a player missing from either kept everything.
 */
#[Group('database')]
class DeathPurgeTest extends LegacyPlayerFixtureTestCase
{
    public function testAWoundWithoutEffectsIsHealedByDeath(): void
    {
        $player = $this->createRealPlayer('GmBlesse');
        $player->putBonus(['pv' => -5]);

        $player->death();

        $this->assertSame(0, (int) $this->link->fetchOne(
            'SELECT COUNT(*) FROM players_bonus WHERE player_id = ?',
            [(int) $player->id]
        ));
    }

    public function testAnEffectWithoutAWoundEndsWithDeath(): void
    {
        $player = $this->createRealPlayer('GmEnvoute');
        $player->add_effect('vol', -1);

        $player->death();

        $this->assertSame(0, $player->have_effect('vol'));
    }
}
