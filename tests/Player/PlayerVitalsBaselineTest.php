<?php

namespace Tests\Player;

use App\Factory\PlayerFactory;
use App\Service\RaceService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Phase 0 golden masters for the player vitals pipeline
 * (docs/design-buildings-entities.md §7.3): pins the persisted contract of
 * get_caracs(), getRemaining() and putBonus() on a real fixture player.
 *
 * Buildings will be `players` rows whose damage flows through exactly these
 * three methods (pseudo-race base PV + players_bonus wounds), so any
 * behavioural drift here breaks the whole plan — these tests must stay green,
 * unchanged, through the GameEntity/Character/Structure refactors.
 *
 * What is pinned on purpose:
 *   - caracs = race base + upgrades (fresh player: race base exactly)
 *   - the turn overlay only materializes keys present in players_bonus
 *   - getRemaining() falls back to caracs when no bonus row exists
 *   - putBonus() persists to players_bonus and survives a fresh reload
 *   - healing clamps at max PV and clears the fully-healed bonus row
 *   - damage drops a 'sang' element on the tile (the destruction branch of
 *     the buildings plan hooks right next to this side effect)
 */
#[Group('entities-baseline')]
class PlayerVitalsBaselineTest extends LegacyPlayerFixtureTestCase
{
    public function testFreshPlayerCaracsAreTheRaceBaseWithAnEmptyTurnOverlay(): void
    {
        $player = $this->createRealPlayer('GmVitals');
        $player->get_caracs();

        $race = (new RaceService())->getRaceData('nain');

        foreach (CARACS as $k => $_) {
            $this->assertSame(
                (int) ($race->$k ?? 0),
                (int) $player->caracs->$k,
                "fresh player carac '{$k}' must equal the race base"
            );
            $this->assertSame(
                (int) ($race->$k ?? 0),
                (int) $player->nude->$k,
                "nude carac '{$k}' must equal the race base"
            );
        }

        $this->assertSame(
            [],
            get_object_vars($player->turn),
            'turn overlay must be empty while players_bonus has no rows'
        );
    }

    public function testGetRemainingFallsBackToCaracsWithoutBonusRows(): void
    {
        $player = $this->createRealPlayer('GmVitals');
        $player->get_caracs();

        $this->assertSame((int) $player->caracs->pv, $player->getRemaining('pv'));
        $this->assertSame((int) $player->caracs->a, $player->getRemaining('a'));
        $this->assertSame(
            (int) $player->data->energie,
            $player->getRemaining('energie'),
            "the 'energie' trait must fall back to players.energie, not caracs"
        );
    }

    public function testPutBonusPersistsDamageAndSurvivesAFreshReload(): void
    {
        $player = $this->createRealPlayer('GmVitals');
        $player->get_caracs();
        $maxPv = (int) $player->caracs->pv;
        $this->snapshotBloodAt((int) $player->data->coords_id);

        $player->putBonus(['pv' => -3]);

        $this->assertSame(
            -3,
            (int) $this->link->fetchOne(
                'SELECT n FROM players_bonus WHERE player_id = ? AND name = "pv"',
                [$player->id]
            ),
            'damage must persist as a players_bonus row'
        );
        $this->assertSame($maxPv - 3, $player->getRemaining('pv'), 'same-instance view');

        $fresh = PlayerFactory::legacy($player->id);
        $this->assertSame($maxPv - 3, $fresh->getRemaining('pv'), 'fresh-instance view');
    }

    public function testDamageDropsBloodOnTheTile(): void
    {
        $player = $this->createRealPlayer('GmVitals');
        $player->get_caracs();
        $coordsId = (int) $player->data->coords_id;
        $this->snapshotBloodAt($coordsId);

        $player->putBonus(['pv' => -1]);

        $this->assertNotFalse(
            $this->link->fetchOne(
                'SELECT endTime FROM map_elements WHERE name = "sang" AND coords_id = ?',
                [$coordsId]
            ),
            "negative pv must drop a 'sang' element on the player's tile"
        );
    }

    public function testHealingClampsAtMaxAndClearsTheBonusRow(): void
    {
        $player = $this->createRealPlayer('GmVitals');
        $player->get_caracs();
        $maxPv = (int) $player->caracs->pv;
        $this->snapshotBloodAt((int) $player->data->coords_id);

        $player->putBonus(['pv' => -3]);
        $player->putBonus(['pv' => 5]);

        $fresh = PlayerFactory::legacy($player->id);
        $this->assertSame($maxPv, $fresh->getRemaining('pv'), 'overheal must clamp at max PV');
        $this->assertFalse(
            $this->link->fetchOne(
                'SELECT n FROM players_bonus WHERE player_id = ? AND name = "pv"',
                [$player->id]
            ),
            'a fully-healed pv bonus row must be removed, not kept at >= 0'
        );
    }
}
