<?php

namespace Tests\Player\Mock;

use App\Service\RaceService;
use Classes\Player;
use Classes\View;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * Base class for golden-master tests that exercise the LEGACY player stack
 * (Classes\Player + mysqli Db) against the devcontainer aoo4 database.
 *
 * Phase 0 of the buildings-as-entities plan (docs/design-buildings-entities.md
 * §7.3): these fixtures let tests pin get_caracs()/getRemaining()/putBonus()
 * and a full action resolution on REAL rows, so the GameEntity/Structure
 * refactors that follow have a behavioural safety net.
 *
 * Design constraints, mirroring PlayerCaracsServiceCharacterizationTest and
 * TutorialIntegrationTestCase:
 *
 *   1. **Skip cleanly when aoo4 is unreachable or unseeded.** Legacy Db()
 *      reads DB_CONSTANTS, so unlike the tutorial harness this cannot point
 *      at aoo4_test — and it needs the seeded `races` catalog anyway.
 *
 *   2. **Manual row cleanup, not transactions.** Legacy Db() (mysqli) and
 *      the Doctrine $link are separate connections; a rollback on one would
 *      not cover writes from the other. Every player created through
 *      createRealPlayer() is tracked and its rows are deleted in tearDown.
 *
 *   3. **Fixture players are real `put_player()` products** — the tests pin
 *      the exact machinery buildings will reuse, not a reimplementation.
 */
abstract class LegacyPlayerFixtureTestCase extends TestCase
{
    protected ?Connection $link = null;

    /** @var mixed $GLOBALS['link'] as found in setUp, restored in tearDown */
    private mixed $previousLink = null;

    /** @var int[] ids created via createRealPlayer(), removed in tearDown */
    private array $createdPlayerIds = [];

    /** @var array<int, int|null> coords_id => pre-existing sang endTime (null = absent) */
    private array $bloodSnapshots = [];

    protected function setUp(): void
    {
        $this->link = $this->bootstrapLegacyOrSkip();

        // Fixture rows are deleted through DBAL, invisible to the shared
        // EntityManager: when a later test reuses a freed id, stale
        // identity-map entries (e.g. a PlayerEffect from a previous attack)
        // collide with the new rows. Start every test with a clean map.
        \App\Entity\EntityManagerFactory::getEntityManager()->clear();
    }

    protected function tearDown(): void
    {
        if ($this->link === null) {
            return;
        }

        // Reverse creation order: a building placed after its owner
        // references it via buildings.owner_id and must go first.
        foreach (array_reverse($this->createdPlayerIds) as $id) {
            $this->link->executeStatement('DELETE FROM buildings WHERE player_id = ? OR owner_id = ?', [$id, $id]);
            foreach ([
                'players_bonus',
                'players_effects',
                'players_actions',
                'players_options',
                'players_items',
                'players_items_bank',
            ] as $table) {
                $this->link->executeStatement("DELETE FROM {$table} WHERE player_id = ?", [$id]);
            }
            foreach (['players_logs', 'players_assists'] as $table) {
                $this->link->executeStatement(
                    "DELETE FROM {$table} WHERE player_id = ? OR target_id = ?",
                    [$id, $id]
                );
            }
            $this->link->executeStatement('DELETE FROM players WHERE id = ?', [$id]);

            // Purge every per-entity file cache: .json is the get_data()
            // cache, .svg the board render — a recycled id would otherwise
            // resurrect the previous fixture's identity.
            foreach (['.json', '.svg', '.turn.json', '.caracs.json'] as $suffix) {
                @unlink(__DIR__ . '/../../../datas/private/players/' . $id . $suffix);
            }
        }

        // Restore the map_elements 'sang' rows the exercised putBonus() calls
        // may have written on the fixtures' tiles.
        foreach ($this->bloodSnapshots as $coordsId => $endTimeBefore) {
            if ($endTimeBefore === null) {
                $this->link->executeStatement(
                    'DELETE FROM map_elements WHERE name = "sang" AND coords_id = ?',
                    [$coordsId]
                );
            } else {
                $this->link->executeStatement(
                    'UPDATE map_elements SET endTime = ? WHERE name = "sang" AND coords_id = ?',
                    [$endTimeBefore, $coordsId]
                );
            }
        }

        if ($this->createdPlayerIds !== []) {
            try {
                Player::refresh_list();
            } catch (\Throwable) {
                // The list cache is cosmetic for tests; never fail teardown on it.
            }
        }

        $this->createdPlayerIds = [];
        $this->bloodSnapshots = [];
        $this->link = null;
        $GLOBALS['link'] = $this->previousLink;
        $this->previousLink = null;
    }

    /**
     * Create a throwaway real player through the production factory path
     * (players row + starter actions + default options) and register it for
     * teardown. Returns a fresh legacy Player.
     */
    protected function createRealPlayer(string $prefix, string $race = 'nain'): Player
    {
        $name = $prefix . '_' . bin2hex(random_bytes(4));
        $id = Player::put_player($name, $race);
        $this->createdPlayerIds[] = $id;

        return new Player($id);
    }

    /**
     * Register an entity id created OUTSIDE createRealPlayer() (e.g. a
     * building placed via BuildingService) for the same row cleanup.
     */
    protected function trackEntityId(int $id): void
    {
        $this->createdPlayerIds[] = $id;
    }

    /**
     * Teleport a fixture player to (x, y) on gaia — direct coords_id update,
     * bypassing movement rules on purpose (tests position, not pathing).
     */
    protected function movePlayerTo(int $playerId, int $x, int $y): void
    {
        $coordsId = View::get_coords_id((object) ['x' => $x, 'y' => $y, 'z' => 0, 'plan' => 'gaia']);
        $this->link->executeStatement('UPDATE players SET coords_id = ? WHERE id = ?', [$coordsId, $playerId]);
    }

    /**
     * Record the pre-test state of the 'sang' element on a tile so tearDown
     * can restore it exactly. Call before any putBonus(['pv' => -x]).
     */
    protected function snapshotBloodAt(int $coordsId): void
    {
        if (array_key_exists($coordsId, $this->bloodSnapshots)) {
            return;
        }

        $endTime = $this->link->fetchOne(
            'SELECT endTime FROM map_elements WHERE name = "sang" AND coords_id = ?',
            [$coordsId]
        );
        $this->bloodSnapshots[$coordsId] = $endTime === false ? null : (int) $endTime;
    }

    /**
     * Boot the legacy stack (bootstrap + functions + constants) and validate
     * the aoo4 DB is reachable and carries the seeded race catalog; skip the
     * test cleanly otherwise.
     */
    private function bootstrapLegacyOrSkip(): Connection
    {
        try {
            require_once __DIR__ . '/../../../config/bootstrap.php';
            require_once __DIR__ . '/../../../config/functions.php';
            require_once __DIR__ . '/../../../config/constants.php';
        } catch (\Throwable $e) {
            $this->markTestSkipped('Legacy bootstrap failed: ' . $e->getMessage());
        }

        // Re-anchor db() on the canonical aoo4 connection: earlier suites
        // legitimately point $GLOBALS['link'] at aoo4_test or SQLite doubles,
        // and the legacy stack exercised here needs the fully seeded DB. The
        // previous value is restored in tearDown.
        $this->previousLink = $GLOBALS['link'] ?? null;
        try {
            $link = \App\Entity\EntityManagerFactory::getEntityManager()->getConnection();
            $link->executeQuery('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('Legacy DB unreachable: ' . $e->getMessage());
        }
        $GLOBALS['link'] = $link;

        $race = (new RaceService())->getRaceData('nain');
        if (!is_object($race) || !isset($race->pv)) {
            $this->markTestSkipped('races catalog not seeded (no nain row) — run the devcontainer DB init.');
        }

        return $link;
    }
}
