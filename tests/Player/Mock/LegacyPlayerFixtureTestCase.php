<?php

namespace Tests\Player\Mock;

use App\Service\BuildingService;
use App\Service\RaceService;
use Classes\Item;
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
            // Item instances: links first (FK), then the orphaned rows.
            $instanceIds = $this->link->fetchFirstColumn(
                'SELECT instance_id FROM players_items_instances WHERE player_id = ?',
                [$id]
            );
            if ($instanceIds !== []) {
                $in = implode(',', array_map('intval', $instanceIds));
                $this->link->executeStatement("DELETE FROM players_items_instances WHERE instance_id IN ({$in})");
                $this->link->executeStatement("DELETE FROM item_instances WHERE id IN ({$in})");
            }
            $this->link->executeStatement('DELETE FROM buildings WHERE player_id = ? OR owner_id = ?', [$id, $id]);
            $this->link->executeStatement('DELETE FROM unique_objects WHERE player_id = ?', [$id]);
            foreach ([
                'players_bonus',
                'players_effects',
                'players_actions',
                'players_options',
                'players_items',
                'players_items_bank',
                // Depuis la consolidation db/updates, players(id) est visé
                // par des FK : une ligne orpheline (id recyclé par
                // l'auto-incrément, la base de dev en garde des années)
                // sur n'importe laquelle de ces tables bloquerait le
                // DELETE players ci-dessous.
                'players_connections',
                'players_banned',
                'players_upgrades',
                'players_followers',
                'players_quests_steps',
                'players_quests',
                'players_forum_missives',
                'tutorial_progress',
                'items_asks',
                'items_bids',
            ] as $table) {
                $this->link->executeStatement("DELETE FROM {$table} WHERE player_id = ?", [$id]);
            }
            foreach ([
                'players_logs',
                'players_assists',
                'players_kills',
                'players_items_exchanges',
                'items_exchanges',
            ] as $table) {
                $this->link->executeStatement(
                    "DELETE FROM {$table} WHERE player_id = ? OR target_id = ?",
                    [$id, $id]
                );
            }
            $this->link->executeStatement('DELETE FROM players_pnjs WHERE player_id = ? OR pnj_id = ?', [$id, $id]);
            $this->link->executeStatement(
                'DELETE FROM players_forum_rewards WHERE from_player_id = ? OR to_player_id = ?',
                [$id, $id]
            );
            $this->link->executeStatement('DELETE FROM tutorial_enemies WHERE enemy_player_id = ?', [$id]);

            // Provenance de carte (« posé par ») : on détache la référence,
            // on ne détruit pas le contenu qu'un id orphelin squatterait.
            foreach (['map_resources', 'map_tiles', 'map_routes'] as $table) {
                $this->link->executeStatement("UPDATE {$table} SET player_id = NULL WHERE player_id = ?", [$id]);
            }

            $this->link->executeStatement('DELETE FROM players WHERE id = ?', [$id]);

            // Purge every per-entity file cache: .json is the get_data()
            // cache, .svg the board render — a recycled id would otherwise
            // resurrect the previous fixture's identity.
            self::purgeEntityCache($id);
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
    /**
     * Caches fichier par entité : .json est celui de get_data(), .svg le
     * damier rendu, les autres les caracs / le tour / l'inventaire. Un id
     * recyclé qui les retrouve ressuscite l'entité précédente.
     */
    private static function purgeEntityCache(int $id): void
    {
        foreach (['.json', '.svg', '.turn.json', '.caracs.json', '.invent.html'] as $suffix) {
            @unlink(__DIR__ . '/../../../datas/private/players/' . $id . $suffix);
        }

        /* Le fichier ne suffit pas : Json::decode garde un cache MÉMOIRE
         * par chemin, sur un singleton global qui vit tout le process.
         * Supprimer le fichier ne le vide pas — le décodeur ressert
         * l'objet déjà lu. Sur une base neuve, où les ids sont recyclés
         * d'un test à l'autre, le joueur suivant héritait ainsi de
         * l'identité (et donc de la position) du précédent. */
        foreach (['', '.turn', '.caracs'] as $variant) {
            json()->forget('players', $id . $variant);
        }
    }

    protected function createRealPlayer(string $prefix, string $race = 'nain'): Player
    {
        $name = $prefix . '_' . bin2hex(random_bytes(4));
        $id = Player::put_player($name, $race);
        $this->createdPlayerIds[] = $id;

        /* Purger AUSSI à la création, pas seulement au nettoyage. Le
         * nettoyage ne couvre que les joueurs nés ici ; un fichier laissé
         * par n'importe quel autre harnais suffit à ce que get_data()
         * serve l'identité du PRÉCÉDENT occupant de l'id — coordonnées
         * comprises. Invisible sur une base de dev, où l'auto-incrément
         * ne recycle jamais ; systématique sur une base neuve, où les ids
         * repartent à chaque test. */
        self::purgeEntityCache($id);

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

    /** Skip proprement quand les tables structures ne sont pas migrées. */
    protected function requireBuildingsOrSkip(): void
    {
        try {
            $this->link->executeQuery('SELECT 1 FROM buildings LIMIT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('buildings table unavailable (run migrations): ' . $e->getMessage());
        }
    }

    /** Objet du catalogue, données chargées — skip si non seedé. */
    protected function itemOrSkip(string $name): Item
    {
        $item = Item::get_item_by_name($name);
        if ($item === false || $item === null) {
            $this->markTestSkipped("items catalog not seeded (no '{$name}' row).");
        }
        $item->get_data();

        return $item;
    }

    /**
     * Pose une structure jetable via BuildingService (skip si le type
     * n'est pas seedé), trackée pour le teardown du harnais.
     */
    protected function placeStructure(string $type, int $x, int $y, string $plan = 'gaia'): int
    {
        $race = (new RaceService())->getRaceByName($type);
        if ($race === null || !$race->isStructureKind()) {
            $this->markTestSkipped("structure type '{$type}' not seeded (run migrations).");
        }

        /* Un élément seedé sur la case (sang, boue…) la rendrait
         * inconstructible depuis la règle map_elements de place() —
         * ces tests exercent le bâtiment, pas le terrain : on nettoie. */
        $coordsId = View::get_coords_id((object) ['x' => $x, 'y' => $y, 'z' => 0, 'plan' => $plan]);
        (new \Classes\Db())->exe('DELETE FROM map_elements WHERE coords_id = ?', $coordsId);

        $id = (new BuildingService())->place(
            $type,
            (object) ['x' => $x, 'y' => $y, 'z' => 0, 'plan' => $plan]
        );
        $this->trackEntityId($id);

        return $id;
    }

    /**
     * Teleport a fixture player to (x, y) on gaia — direct coords_id update,
     * bypassing movement rules on purpose (tests position, not pathing).
     */
    protected function movePlayerTo(int $playerId, int $x, int $y): void
    {
        $coordsId = View::get_coords_id((object) ['x' => $x, 'y' => $y, 'z' => 0, 'plan' => 'gaia']);
        $this->link->executeStatement('UPDATE players SET coords_id = ? WHERE id = ?', [$coordsId, $playerId]);

        /* Les cases suivent, comme derrière toute écriture de coords_id en
         * production : les laisser en arrière ferait mesurer les distances
         * depuis l'ancienne position. Les cas qui veulent EXPRESSÉMENT une
         * dérive écrivent l'UPDATE eux-mêmes. */
        (new \App\Service\Map\EntityCellService($this->link))->syncCells($playerId);
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
