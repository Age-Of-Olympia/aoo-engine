<?php

namespace Tests\Various;

use App\Service\ProgressionService;
use App\Service\RaceService;
use App\Service\TurnProcessingService;
use App\Service\TurnService;
use Classes\Player;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * A building that plays, end to end, with no screen and no session.
 *
 * The switch is one flag: `races.playable` on a building type. It gets the two
 * satellites, its turn falls due like anyone's, and refreshing it restarts its
 * action pool and moves its clock on — nothing else, because a structure has no
 * body to recover (docs/design-playable-buildings.md §3.2).
 *
 * The type is made playable here rather than in the catalogue: no shipped
 * building type carries the flag yet, and this is the case that says what
 * happens the day one does.
 */
#[Group('entities-baseline')]
class APlayableBuildingTakesTurnsTest extends LegacyPlayerFixtureTestCase
{
    private const TYPE = 'mur_pierre';

    private ?bool $playableBefore = null;

    protected function tearDown(): void
    {
        if ($this->playableBefore !== null && $this->link !== null) {
            $this->link->executeStatement(
                'UPDATE races SET playable = ? WHERE name = ?',
                [$this->playableBefore ? 1 : 0, self::TYPE]
            );
            RaceService::clearCache();
        }
        $this->playableBefore = null;

        parent::tearDown();
    }

    public function testAnUnplayableBuildingGetsNoSatellites(): void
    {
        [$x, $y] = $this->farTile();
        $id = $this->placeStructure(self::TYPE, $x, $y);

        (new TurnService($this->link))->ensureRow($id);
        (new ProgressionService($this->link))->ensureRow($id);

        $this->assertFalse($this->hasRowIn('turns', $id));
        $this->assertFalse($this->hasRowIn('progression', $id));
    }

    public function testDeclaringTheTypePlayableIsWhatGivesItTheSatellites(): void
    {
        [$x, $y] = $this->farTile();
        $id = $this->placeStructure(self::TYPE, $x, $y);
        $this->makeTypePlayable();

        (new TurnService($this->link))->ensureRow($id);
        (new ProgressionService($this->link))->ensureRow($id);

        $this->assertTrue($this->hasRowIn('turns', $id), 'it owns a turn of its own');
        $this->assertTrue($this->hasRowIn('progression', $id), 'and a progression of its own');
    }

    /** A building earns its own experience — the same gateway, no account. */
    public function testItEarnsItsOwnExperience(): void
    {
        [$x, $y] = $this->farTile();
        $id = $this->placeStructure(self::TYPE, $x, $y);
        $this->makeTypePlayable();

        (new ProgressionService($this->link))->gain($id, 40, 40, 2);

        $entity = new Player($id);
        $entity->get_row();

        $this->assertSame(40, (int) $entity->row->xp);
        $this->assertSame(2, (int) $entity->row->rank);
    }

    /**
     * Its turn is its pool and its clock: the spent points come back and the
     * next turn is scheduled. No session is asked anywhere along the way.
     */
    public function testItsTurnRestartsThePoolAndMovesTheClock(): void
    {
        [$x, $y] = $this->farTile();
        $id = $this->placeStructure(self::TYPE, $x, $y);
        $this->makeTypePlayable();

        $now = 1_800_000_000;
        (new TurnService($this->link))->setNextTurnTime($id, $now - 10);

        // Points spent by whoever drove it before this turn.
        $this->link->executeStatement(
            "INSERT INTO players_bonus (player_id, name, n) VALUES (?, 'a', -3)
             ON DUPLICATE KEY UPDATE n = VALUES(n)",
            [$id]
        );

        $entity = new Player($id);
        $recap = (new TurnProcessingService())->processDue($entity, $now);

        $this->assertNotNull($recap, 'its hour had passed, so its turn ran');
        $this->assertGreaterThan($now, $recap->nextTurnTime, 'the clock moved on');

        $this->assertFalse(
            $this->link->fetchOne(
                "SELECT n FROM players_bonus WHERE player_id = ? AND name = 'a'",
                [$id]
            ),
            'the pool starts again'
        );

        $this->assertSame(
            $recap->nextTurnTime,
            (int) $this->link->fetchOne('SELECT next_turn_time FROM turns WHERE player_id = ?', [$id]),
            'and the satellite carries it'
        );
    }

    /** A structure recovers no body: no experience for merely standing there. */
    public function testItsTurnGrantsNoExperienceForStandingStill(): void
    {
        [$x, $y] = $this->farTile();
        $id = $this->placeStructure(self::TYPE, $x, $y);
        $this->makeTypePlayable();

        $now = 1_800_000_000;
        (new TurnService($this->link))->setNextTurnTime($id, $now - 10);
        (new ProgressionService($this->link))->gain($id, 40, 40, 2);

        (new TurnProcessingService())->processDue(new Player($id), $now);

        $this->assertSame(
            40,
            (int) $this->link->fetchOne('SELECT xp FROM progression WHERE player_id = ?', [$id]),
            'it earns by acting, not by existing'
        );
    }

    private function makeTypePlayable(): void
    {
        $this->playableBefore = (bool) $this->link->fetchOne(
            'SELECT playable FROM races WHERE name = ?',
            [self::TYPE]
        );

        $this->link->executeStatement(
            'UPDATE races SET playable = 1 WHERE name = ?',
            [self::TYPE]
        );
        RaceService::clearCache();
    }

    private function hasRowIn(string $table, int $playerId): bool
    {
        return $this->link->fetchOne(
            "SELECT player_id FROM {$table} WHERE player_id = ?",
            [$playerId]
        ) !== false;
    }
}
