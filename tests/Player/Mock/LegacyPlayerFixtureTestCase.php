<?php

namespace Tests\Player\Mock;

use App\Service\BuildingService;
use App\Service\RaceService;
use Classes\Item;
use Classes\Player;
use Classes\View;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Tests\Support\PlanFixtureTrait;

/**
 * Base class for baseline tests that exercise the LEGACY player stack
 * (Classes\Player + mysqli Db) against the devcontainer aoo4 database.
 *
 * Phase 0 of the buildings-as-entities plan: these fixtures let tests pin
 * get_caracs()/getRemaining()/putBonus()
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
    use PlanFixtureTrait;

    protected ?Connection $link = null;

    /** @var mixed $GLOBALS['link'] as found in setUp, restored in tearDown */
    private mixed $previousLink = null;

    /** @var int[] ids created via createRealPlayer(), removed in tearDown */
    private array $createdPlayerIds = [];

    /** @var array<int, int|null> coords_id => pre-existing sang endTime (null = absent) */
    private array $bloodSnapshots = [];

    /** @var string[] structure type names sown via sowStructureType(), removed in tearDown */
    private array $sownTypeNames = [];

    /** @var string[] catalogue item names sown via sowCatalogItem(), removed in tearDown */
    private array $sownItemNames = [];

    /** @var int[] action ids sown via sowCatalogAction(), removed in tearDown */
    private array $sownActionIds = [];

    /** @var string[] faction codes sown via sowFaction(), removed in tearDown */
    private array $sownFactionCodes = [];

    protected function setUp(): void
    {
        $this->link = $this->bootstrapLegacyOrSkip();

        // Fixture rows are deleted through DBAL, invisible to the shared
        // EntityManager: when a later test reuses a freed id, stale
        // identity-map entries (e.g. a PlayerEffect from a previous attack)
        // collide with the new rows. Start every test with a clean map.
        \App\Factory\EntityManagerFactory::getEntityManager()->clear();
    }

    protected function tearDown(): void
    {
        if ($this->link === null) {
            return;
        }

        // Reverse creation order: a building placed after its owner
        // references it via buildings.owner_id and must go first.
        foreach (array_reverse($this->createdPlayerIds) as $id) {
            $this->removeEntity($id);
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
                Player::refresh_classements();
            } catch (\Throwable) {
                // Classements pages are cosmetic for tests; never fail teardown on them.
            }
        }

        // Sown catalogue rows go last: the entities standing on them are
        // already gone by now.
        foreach ($this->sownTypeNames as $name) {
            $this->link->executeStatement('DELETE FROM races WHERE name = ?', [$name]);
        }
        if ($this->sownTypeNames !== []) {
            RaceService::clearCache();
        }
        foreach ($this->sownItemNames as $name) {
            $this->link->executeStatement('DELETE FROM items WHERE name = ?', [$name]);
        }
        foreach ($this->sownActionIds as $id) {
            $this->link->executeStatement('DELETE FROM action_conditions WHERE action_id = ?', [$id]);
            $this->link->executeStatement('DELETE FROM actions WHERE id = ?', [$id]);
        }
        foreach ($this->sownFactionCodes as $code) {
            $this->link->executeStatement('DELETE FROM factions WHERE code = ?', [$code]);
        }

        $this->createdPlayerIds = [];
        $this->bloodSnapshots = [];
        $this->sownTypeNames = [];
        $this->sownItemNames = [];
        $this->sownActionIds = [];
        $this->sownFactionCodes = [];
        $this->link = null;
        $GLOBALS['link'] = $this->previousLink;
        $this->previousLink = null;
    }

    /**
     * Every RESTRICT key onto players(id), read from the schema once per run,
     * dependants before the tables they point at: the hand-kept list this
     * replaces fell behind every time a key was added.
     *
     * @var list<array{0: string, 1: string}>|null (table, column)
     */
    private static ?array $restrictingReferences = null;

    /** @return list<array{0: string, 1: string}> */
    private function restrictingReferences(): array
    {
        return self::$restrictingReferences ??= $this->link->fetchAllNumeric(
            "SELECT k.TABLE_NAME, k.COLUMN_NAME
               FROM information_schema.KEY_COLUMN_USAGE k
               JOIN information_schema.REFERENTIAL_CONSTRAINTS r
                 ON r.CONSTRAINT_SCHEMA = k.TABLE_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
              WHERE k.TABLE_SCHEMA = DATABASE()
                AND k.REFERENCED_TABLE_NAME = 'players'
                AND k.TABLE_NAME <> 'players'
                AND r.DELETE_RULE IN ('RESTRICT', 'NO ACTION')
              ORDER BY (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE d
                         WHERE d.TABLE_SCHEMA = k.TABLE_SCHEMA AND d.REFERENCED_TABLE_NAME = k.TABLE_NAME),
                       k.TABLE_NAME, k.COLUMN_NAME"
        );
    }

    /**
     * Delete a fixture entity and whatever holds it down: its own rows first,
     * then what it holds (an exemplar is an entity of its own), then the row.
     * CASCADE and SET NULL keys look after themselves.
     */
    private function removeEntity(int $id): void
    {
        // One key deeper: a bag row on an exemplar of this entity holds its
        // instance, which holds the entity.
        $this->link->executeStatement(
            'DELETE FROM players_items_instances
              WHERE instance_id IN (SELECT id FROM item_instances WHERE entity_id = ?)',
            [$id]
        );

        foreach ($this->restrictingReferences() as [$table, $column]) {
            // Map provenance ("laid by") is detached, not destroyed: the
            // content outlives its author.
            if (str_starts_with($table, 'map_')) {
                $this->link->executeStatement("UPDATE {$table} SET {$column} = NULL WHERE {$column} = ?", [$id]);
            } else {
                $this->link->executeStatement("DELETE FROM {$table} WHERE {$column} = ?", [$id]);
            }
        }
        // No key on this one; a recycled id would inherit the rows.
        $this->link->executeStatement('DELETE FROM players_items_bank WHERE player_id = ?', [$id]);

        foreach ($this->link->fetchFirstColumn('SELECT id FROM players WHERE holder_id = ?', [$id]) as $held) {
            $this->removeEntity((int) $held);
        }

        $this->link->executeStatement('DELETE FROM players WHERE id = ?', [$id]);

        // Purge the per-entity file cache: a recycled id would otherwise
        // resurrect the previous fixture's render.
        self::purgeEntityCache($id);
    }

    /**
     * The rendered board (.svg) is the only file cache left: a recycled
     * id that finds one would serve the previous entity's render. Player
     * data reads the database, nothing else to purge.
     */
    protected static function purgeEntityCache(int $id): void
    {
        @unlink(__DIR__ . '/../../../datas/private/players/' . $id . '.svg');
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
     * Set what an exemplar has left of its life.
     *
     * Wear is a deficit against the maximum its TYPE gives, like every other
     * wound, so a fixture states the remaining life and the deficit follows.
     * A per-instance maximum cannot be arranged any more — that was the frozen
     * snapshot, and it is gone.
     */
    protected function setRemainingLife(int $instanceId, int $remaining): void
    {
        $row = $this->link->fetchAssociative(
            'SELECT i.entity_id, it.durability_max
               FROM item_instances i JOIN items it ON it.id = i.item_id
              WHERE i.id = ?',
            [$instanceId]
        );

        $this->link->executeStatement(
            "INSERT INTO players_bonus (player_id, name, n) VALUES (?, 'pv', ?)
             ON DUPLICATE KEY UPDATE n = VALUES(n)",
            [(int) $row['entity_id'], $remaining - (int) $row['durability_max']]
        );
    }

    /** What an exemplar has left, rebuilt the way every reader now rebuilds it. */
    protected function remainingLifeOf(int $instanceId): int
    {
        return (int) $this->link->fetchOne(
            'SELECT ' . \App\Service\ItemInstanceService::WEAR_CURRENT . '
               FROM item_instances i
               JOIN items it ON it.id = i.item_id
               ' . \App\Service\ItemInstanceService::WEAR_JOIN . '
              WHERE i.id = ?',
            [$instanceId]
        );
    }

    /** The maximum an exemplar's type gives it. */
    protected function maxLifeOf(int $instanceId): int
    {
        return (int) $this->link->fetchOne(
            'SELECT it.durability_max FROM item_instances i
               JOIN items it ON it.id = i.item_id WHERE i.id = ?',
            [$instanceId]
        );
    }

    /**
     * Register an entity id created OUTSIDE createRealPlayer() (e.g. a
     * building placed via BuildingService) for the same row cleanup.
     */
    protected function trackEntityId(int $id): void
    {
        $this->createdPlayerIds[] = $id;
    }

    /** Skip cleanly when a table — or one of its columns — is not migrated yet. */
    protected function requireTableOrSkip(string $table, string $column = '1'): void
    {
        try {
            $this->link->executeQuery("SELECT {$column} FROM {$table} LIMIT 1");
        } catch (\Throwable $e) {
            $this->markTestSkipped("{$table}.{$column} unavailable (run migrations): " . $e->getMessage());
        }
    }

    protected function requireBuildingsOrSkip(): void
    {
        $this->requireTableOrSkip('buildings');
    }

    /** A catalogue action, or skip when the world lacks it. */
    protected function actionOrSkip(string $name): \App\Interface\ActionInterface
    {
        $action = \App\Factory\ActionFactory::getAction($name);
        if ($action === null) {
            $this->markTestSkipped("actions catalog not seeded (no '{$name}' row — run migrations).");
        }

        return $action;
    }

    /**
     * Give an entity one more cell, with the role wanted — EntityCellService
     * only lays anchors.
     *
     * @return int the coords id of that cell
     */
    protected function giveCell(int $entityId, int $x, int $y, string $role, string $plan = 'gaia'): int
    {
        $coordsId = $this->coordsIdOn($plan, $x, $y);

        $this->link->executeStatement(
            'INSERT INTO entity_cells (player_id, coords_id, plan, z, x, y, piece, role)
             VALUES (?, ?, ?, 0, ?, ?, 0, ?)
             ON DUPLICATE KEY UPDATE role = VALUES(role)',
            [$entityId, $coordsId, $plan, $x, $y, $role]
        );

        return $coordsId;
    }

    /** The exemplar a player holds of a catalogue item, 0 when none. */
    protected function instanceHeldBy(int $playerId, int $itemId, bool $equippedOnly = false): int
    {
        $slotFilter = $equippedOnly ? "AND e.slot != ''" : '';

        return (int) $this->link->fetchOne(
            "SELECT i.id FROM players e
               JOIN item_instances i ON i.entity_id = e.id
              WHERE e.holder_id = ? AND i.item_id = ? {$slotFilter}
              ORDER BY i.id LIMIT 1",
            [$playerId, $itemId]
        );
    }

    /** The slot an exemplar sits in: '' carried, an emplacement, bank, escrow. */
    protected function slotOfInstance(int $instanceId): string
    {
        return (string) $this->link->fetchOne(
            'SELECT e.slot FROM players e
               JOIN item_instances i ON i.entity_id = e.id
              WHERE i.id = ?',
            [$instanceId]
        );
    }

    /** Who holds an exemplar, 0 when nobody does. */
    protected function holderOfInstance(int $instanceId): int
    {
        return (int) $this->link->fetchOne(
            'SELECT e.holder_id FROM players e
               JOIN item_instances i ON i.entity_id = e.id
              WHERE i.id = ?',
            [$instanceId]
        );
    }

    /**
     * Sow a structure type the catalogue lacks, shaped like the
     * migrations that create some (TradeHallsEnterTheWorld): a lockable
     * single-cell edifice, 150 PV. `$overrides` replaces columns
     * (capacity, structure_nature…). An already-seeded type is left
     * alone — it belongs to the world, not to the test.
     */
    protected function sowStructureType(string $name, array $overrides = []): void
    {
        if ((new RaceService())->getRaceByName($name) !== null) {
            return;
        }

        $this->link->insert('races', array_merge([
            'code' => strtoupper($name),
            'name' => $name,
            'label' => ucfirst($name),
            'description' => 'Type semé par le harnais de test.',
            'playable' => 0,
            'hidden' => 1,
            'kind' => 'structure',
            'type_kind' => 'building',
            'structure_nature' => 'edifice',
            'bleeds' => '',
            'wound_color' => '#cd7f32',
            'blocks_passage' => 1,
            'blocks_projectiles' => 1,
            'lockable' => 1,
            'opens_the_way' => 0,
            'readable_from_afar' => 1,
            'bgColor' => '#8b6d43',
            'color' => 'black',
            'faction' => '',
            'plan' => '',
            'pv' => 150,
        ], $overrides));
        $this->sownTypeNames[] = $name;

        // The guard lookup above cached the absence; the identity map may
        // hold stale catalogue reads.
        RaceService::clearCache();
        \App\Factory\EntityManagerFactory::getEntityManager()->clear();
    }

    /**
     * Sow a catalogue action the world lacks, with its conditions
     * ([conditionType => parameters]), removed in tearDown. The type is
     * a discriminator of Action's STI map (heal, spell, gesture…).
     */
    protected function sowCatalogAction(string $name, string $type, array $conditions = []): void
    {
        if (\App\Factory\ActionFactory::getAction($name) !== null) {
            return;
        }

        // icon is NOT NULL without default on the CI schema.
        $this->link->insert('actions', ['name' => $name, 'icon' => '', 'type' => $type]);
        $id = (int) $this->link->lastInsertId();
        $this->sownActionIds[] = $id;

        foreach ($conditions as $conditionType => $parameters) {
            $this->link->insert('action_conditions', [
                'action_id' => $id,
                'conditionType' => $conditionType,
                'parameters' => json_encode($parameters),
            ]);
        }

        \App\Factory\EntityManagerFactory::getEntityManager()->clear();
    }

    /**
     * Sow a catalogue item the world lacks and return it loaded. Real
     * worlds import their items by bundle; the harness only creates the
     * row the test needs, removed in tearDown.
     */
    protected function sowCatalogItem(string $name, array $overrides = []): Item
    {
        $existing = Item::get_item_by_name($name);
        if (!empty($existing)) {
            $existing->get_data();

            return $existing;
        }

        $this->link->insert('items', array_merge(['name' => $name], $overrides));
        $this->sownItemNames[] = $name;
        \App\Factory\EntityManagerFactory::getEntityManager()->clear();

        return $this->itemOrSkip($name);
    }

    /**
     * Sow a faction catalogue row for the scene, removed in tearDown —
     * BuildingService::place() validates faction codes against it.
     */
    protected function sowFaction(string $code): void
    {
        if ((bool) $this->link->fetchOne('SELECT 1 FROM factions WHERE code = ?', [$code])) {
            return;
        }

        $this->link->insert('factions', ['code' => $code, 'name' => ucfirst($code), 'raFont' => '']);
        $this->sownFactionCodes[] = $code;
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

    /** Tile allocator cursor — never reset within the run. */
    private static int $farTileCursor = 0;

    /**
     * Every scene its own corner of the board: far gaia coordinates,
     * never served twice in a run. Tests sharing a hardcoded tile
     * inherit each other's leftovers — the manual cleanup only covers
     * tracked rows. The 8-step between scenes leaves room for reach
     * and footprints without touching the next one.
     *
     * @return array{int, int} [$x, $y]
     */
    protected function farTile(): array
    {
        $i = self::$farTileCursor++;

        return [400 + ($i % 40) * 8, 400 + intdiv($i, 40) * 8];
    }

    /**
     * Install an item exemplar on a cell, the way a container now stands.
     *
     * A holder is created when none is given: `create()` files the exemplar in
     * a bag before it is taken out again.
     */
    protected function installExemplar(
        string $itemName,
        int $x,
        int $y,
        ?int $holderId = null,
        string $plan = 'gaia'
    ): int {
        $item = $this->itemOrSkip($itemName);
        $holderId ??= (int) $this->createRealPlayer('GmPorteur' . $x . 'x' . $y)->id;

        $instanceId = (new \App\Service\ItemInstanceService())
            ->create($holderId, (int) $item->id, $holderId, '');

        $entityId = (int) $this->link->fetchOne(
            'SELECT entity_id FROM item_instances WHERE id = ?',
            [$instanceId]
        );

        $coordsId = (int) View::get_coords_id(
            $this->tile($x, $y, $plan)
        );
        (new \App\Service\Map\EntityLocationService($this->link))->installOnCell($entityId, $coordsId);
        $this->trackEntityId($entityId);

        return $entityId;
    }

    /**
     * Pose une structure jetable via BuildingService (skip si le type
     * n'est pas seedé), trackée pour le teardown du harnais.
     */
    protected function placeStructure(string $type, int $x, int $y, string $plan = 'gaia', bool $asConstructionSite = false): int
    {
        $race = (new RaceService())->getRaceByName($type);
        if ($race === null || !$race->isStructureKind()) {
            $this->markTestSkipped("structure type '{$type}' not seeded (run migrations).");
        }

        /* Un élément seedé sur la case (sang, boue…) la rendrait
         * inconstructible depuis la règle map_elements de place() —
         * ces tests exercent le bâtiment, pas le terrain : on nettoie. */
        $coordsId = $this->coordsIdOn($plan, $x, $y);
        (new \Classes\Db())->exe('DELETE FROM map_elements WHERE coords_id = ?', $coordsId);

        $id = (new BuildingService())->place(
            $type,
            $this->tile($x, $y, $plan),
            asConstructionSite: $asConstructionSite
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
        $coordsId = $this->coordsIdOn('gaia', $x, $y);
        $this->link->executeStatement('UPDATE players SET coords_id = ? WHERE id = ?', [$coordsId, $playerId]);

        /* Cells follow, as behind every production write to coords_id:
         * leaving them behind would measure distances from the old spot.
         * Cases that WANT drift write their own UPDATE. */
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
            $link = \App\Factory\EntityManagerFactory::getEntityManager()->getConnection();
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
