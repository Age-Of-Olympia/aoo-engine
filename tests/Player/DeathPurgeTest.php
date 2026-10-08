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

        $player->death(1);

        $this->assertSame(0, (int) $this->link->fetchOne(
            'SELECT COUNT(*) FROM players_bonus WHERE player_id = ?',
            [(int) $player->id]
        ));
    }

    public function testAnEffectWithoutAWoundEndsWithDeath(): void
    {
        $player = $this->createRealPlayer('GmEnvoute');
        $player->add_effect('vol', -1);

        $player->death(1);

        $this->assertSame(0, $player->have_effect('vol'));
    }

    public function testTheAdminDeathEffectLandsAfterThePurgeScaledByTheRankBeforeDeath(): void
    {
        $player = $this->createRealPlayer('GmMaudit');

        (new \App\Service\AdminSettingsService())->set(\App\Service\EffectService::SETTING_DEATH_EFFECT, 'vol');
        try {
            $player->death(3);
        } finally {
            $this->link->executeStatement('DELETE FROM admin_settings WHERE name = ?', [\App\Service\EffectService::SETTING_DEATH_EFFECT]);
        }

        $this->assertSame(
            ['value' => 3, 'endTime' => 3],
            array_map('intval', (array) $this->link->fetchAssociative(
                'SELECT value, endTime FROM players_effects WHERE player_id = ? AND name = ?',
                [(int) $player->id, 'vol']
            )),
            'intensity and duration = the rank passed in'
        );
    }

    public function testAnUnknownDeathEffectIsSkipped(): void
    {
        $player = $this->createRealPlayer('GmOublie');

        (new \App\Service\AdminSettingsService())->set(\App\Service\EffectService::SETTING_DEATH_EFFECT, 'effet_supprime_test');
        try {
            $player->death(2);
        } finally {
            $this->link->executeStatement('DELETE FROM admin_settings WHERE name = ?', [\App\Service\EffectService::SETTING_DEATH_EFFECT]);
        }

        $this->assertSame(0, (int) $this->link->fetchOne(
            'SELECT COUNT(*) FROM players_effects WHERE player_id = ?',
            [(int) $player->id]
        ));
    }
}
