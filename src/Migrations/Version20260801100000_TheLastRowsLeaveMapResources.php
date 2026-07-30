<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The last rows leave `map_resources`, and the table is empty for good.
 *
 * Sixty-two objects survived every conversion of this chantier, each for its
 * own reason, and together they were enough to keep the whole legacy table —
 * with its readers, its admin screens and its destruction path — alive:
 *
 *  - 58 cocotier2/cocotier3 fell between two sieves. The board called them
 *    harvestable (damages -1), so the walls conversion left them; the catalogue
 *    called them obstacles (pv 1), so the resources conversion left them too.
 *    Arbitrated: they are harvestable, like cocotier1 — which is already a
 *    resource, and which the animator plainly meant them to match.
 *  - 3 unique_* were excluded by name, meant for a conversion that never
 *    claimed them: no catalogue row, so nothing to become.
 *  - 1 piedestal_pierre got its catalogue row after the walls conversion had
 *    already run on the server, and no later pass looked back.
 *
 * A loop, not the set-based shape of the 26 656: at this volume readability is
 * worth more than speed, and each row takes a different road depending on what
 * its type says it is.
 *
 * Reversible on its own: the archive now records WHICH conversion took a row,
 * so `down()` here puts back these rows and no others.
 */
final class Version20260801100000_TheLastRowsLeaveMapResources extends AbstractMigration
{
    private const TAG = 'last-rows';

    private const COCONUT_PALMS = ['cocotier1', 'cocotier2', 'cocotier3'];

    /** Ranges as declared in ENTITY_ID_RANGES. */
    private const RANGES = [
        'building' => 20000000,
        'scenery' => 40000000,
        'resource' => 50000000,
    ];

    private const OBSTACLE_ERA_PV = 1;
    private const FALLBACK_HARVEST_PV = 100;

    public function getDescription(): string
    {
        return 'The 62 rows left in map_resources become entities; the table is emptied';
    }

    public function up(Schema $schema): void
    {
        $conn = $this->connection;

        $this->markArchiveWithItsConversion();
        $this->coconutPalmsBecomeHarvestable();
        $this->giveOrphansACatalogueRow();

        $rows = $conn->fetchAllAssociative(
            "SELECT m.id, m.name, m.player_id, m.coords_id, m.damages,
                    c.plan, c.z, c.x, c.y,
                    r.structure_nature, r.pv, r.label
               FROM map_resources m
               JOIN coords c ON c.id = m.coords_id
               JOIN races r
                 ON r.name COLLATE utf8mb4_general_ci = m.name COLLATE utf8mb4_general_ci"
        );

        if ($rows === []) {
            $this->write('map_resources est déjà vide.');

            return;
        }

        $counts = ['resource' => 0, 'scenery' => 0, 'building' => 0];

        foreach ($rows as $row) {
            $type = $this->entityTypeFor((string) $row['structure_nature']);
            $id = $this->nextId($type);

            $this->createEntity($type, $id, $row);
            $this->takeTheCell($id, $row);

            $conn->executeStatement(
                'INSERT IGNORE INTO map_walls_archive
                    (wall_id, name, player_id, coords_id, damages, entity_id, converted_at, converted_by)
                 VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)',
                [
                    (int) $row['id'],
                    (string) $row['name'],
                    $row['player_id'] === null ? null : (int) $row['player_id'],
                    (int) $row['coords_id'],
                    (int) $row['damages'],
                    $id,
                    self::TAG,
                ]
            );

            $conn->executeStatement('DELETE FROM map_resources WHERE id = ?', [(int) $row['id']]);
            $counts[$type]++;
        }

        $left = (int) $conn->fetchOne('SELECT COUNT(*) FROM map_resources');

        $this->write(sprintf(
            '%d ressource(s), %d décor(s), %d bâtiment(s) convertis. %d ligne(s) restantes dans map_resources%s',
            $counts['resource'],
            $counts['scenery'],
            $counts['building'],
            $left,
            $left === 0 ? '.' : ' — un type sans ligne de catalogue les retient.'
        ));
    }

    /**
     * The archive says which conversion took each row, so each can undo its own.
     * Rows already there belong to the conversions that ran before this one.
     */
    private function markArchiveWithItsConversion(): void
    {
        /* Created by the conversions that ran before, but this one does not
         * depend on their order: a fresh database has no archive at all. */
        $this->connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS map_walls_archive (
                wall_id INT NOT NULL,
                name VARCHAR(255) NOT NULL,
                player_id INT NULL,
                coords_id INT NOT NULL,
                damages INT NOT NULL,
                entity_id INT NOT NULL,
                converted_at DATETIME NOT NULL,
                converted_by VARCHAR(64) NULL,
                PRIMARY KEY (wall_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
        );

        $columns = $this->connection->fetchFirstColumn("SHOW COLUMNS FROM map_walls_archive LIKE 'converted_by'");

        if ($columns === []) {
            $this->connection->executeStatement(
                'ALTER TABLE map_walls_archive ADD COLUMN converted_by VARCHAR(64) NULL'
            );
        }
    }

    /**
     * The coconut palms agree with each other at last: all three harvestable,
     * all three with a harvestable's life. Their pv of 1 is the obstacle era's
     * figure — a tree that falls to a single blow — and only that value is
     * raised, so a figure anyone has since chosen by hand is left alone.
     */
    private function coconutPalmsBecomeHarvestable(): void
    {
        $names = implode(',', array_fill(0, count(self::COCONUT_PALMS), '?'));

        $this->connection->executeStatement(
            "UPDATE resource_types SET pv = -1 WHERE name IN ({$names})",
            self::COCONUT_PALMS
        );

        $this->connection->executeStatement(
            "UPDATE races SET structure_nature = 'ressource'
              WHERE kind = 'structure' AND name IN ({$names})",
            self::COCONUT_PALMS
        );

        $this->connection->executeStatement(
            "UPDATE races SET pv = ? WHERE kind = 'structure' AND name IN ({$names}) AND pv = ?",
            array_merge([$this->harvestDefaultPv()], self::COCONUT_PALMS, [self::OBSTACLE_ERA_PV])
        );
    }

    /** The admin's dial, or the figure the chantier settled on. */
    private function harvestDefaultPv(): int
    {
        $stored = (int) $this->connection->fetchOne(
            "SELECT value FROM admin_settings WHERE name = 'harvest_default_pv'"
        );

        return $stored > 0 ? $stored : self::FALLBACK_HARVEST_PV;
    }

    /**
     * A name still standing on the board with nothing in the catalogue cannot
     * become anything. It gets the row it never had — decor when it is a
     * `unique_`, an obstacle otherwise — with the life its old type declared.
     */
    private function giveOrphansACatalogueRow(): void
    {
        $orphans = $this->connection->fetchAllAssociative(
            "SELECT DISTINCT m.name, COALESCE(t.pv, 0) AS pv
               FROM map_resources m
               LEFT JOIN resource_types t
                 ON t.name COLLATE utf8mb4_general_ci = m.name COLLATE utf8mb4_general_ci
              WHERE NOT EXISTS (
                    SELECT 1 FROM races r
                     WHERE r.name COLLATE utf8mb4_general_ci = m.name COLLATE utf8mb4_general_ci
                )"
        );

        foreach ($orphans as $orphan) {
            $name = (string) $orphan['name'];
            $nature = str_starts_with($name, 'unique_') ? 'decor' : 'obstacle';
            $pv = (int) $orphan['pv'];

            $this->connection->executeStatement(
                "INSERT IGNORE INTO races
                    (code, name, label, description, playable, hidden, kind, structure_nature,
                     bleeds, wound_color, blocks_passage, blocks_projectiles, bgColor, color, faction, plan, pv)
                 VALUES (?, ?, ?, '', 0, 1, 'structure', ?, '', '#cd7f32', 1, 1, '#7f7f7f', 'black', '', '', ?)",
                [
                    strtoupper($name),
                    $name,
                    ucfirst(str_replace('_', ' ', $name)),
                    $nature,
                    $pv > 0 ? $pv : self::FALLBACK_HARVEST_PV,
                ]
            );
        }
    }

    private function entityTypeFor(string $nature): string
    {
        return match ($nature) {
            'ressource' => 'resource',
            'decor' => 'scenery',
            default => 'building',
        };
    }

    /** @param array<string, mixed> $row */
    private function createEntity(string $type, int $id, array $row): void
    {
        $name = (string) $row['name'];
        $label = (string) ($row['label'] ?? '');
        $image = 'img/walls/' . $name . '.png';

        $this->connection->executeStatement(
            "INSERT INTO players
                (id, player_type, display_id, name, race, avatar, portrait,
                 coords_id, nextTurnTime, registerTime, text)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, '')",
            [
                $id,
                $type,
                $this->nextDisplayId($type),
                $label !== '' ? $label : ucfirst(str_replace('_', ' ', $name)),
                $name,
                $image,
                $image,
                (int) $row['coords_id'],
                time(),
            ]
        );

        if ($type === 'building') {
            $this->connection->executeStatement(
                "INSERT INTO buildings (player_id, owner_id, faction, build_state) VALUES (?, ?, '', 'built')",
                [$id, $row['player_id'] === null ? null : (int) $row['player_id']]
            );
        }

        /* Exhausted stays exhausted, the way the resources conversion read it. */
        if ($type === 'resource' && (int) $row['damages'] === -2) {
            $this->connection->executeStatement(
                'INSERT IGNORE INTO resources (player_id, exhausted_at) VALUES (?, NOW())',
                [$id]
            );
        }
    }

    /** One cell, and it refuses the step — every one of these was a wall. */
    private function takeTheCell(int $id, array $row): void
    {
        $this->connection->executeStatement(
            "INSERT INTO entity_cells (player_id, coords_id, plan, z, x, y, piece, role)
             VALUES (?, ?, ?, ?, ?, ?, 0, 'block')",
            [
                $id,
                (int) $row['coords_id'],
                (string) $row['plan'],
                (int) $row['z'],
                (int) $row['x'],
                (int) $row['y'],
            ]
        );
    }

    private function nextId(string $type): int
    {
        $start = self::RANGES[$type];

        $max = (int) $this->connection->fetchOne(
            'SELECT COALESCE(MAX(id), 0) FROM players WHERE id BETWEEN ? AND ?',
            [$start, $start + 9999999]
        );

        return $max === 0 ? $start : $max + 1;
    }

    private function nextDisplayId(string $type): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COALESCE(MAX(display_id), 0) + 1 FROM players WHERE player_type = ?',
            [$type]
        );
    }

    public function down(Schema $schema): void
    {
        /* Only this conversion's rows: the archive names the one that took them. */
        $this->addSql(
            "INSERT IGNORE INTO map_resources (id, name, player_id, coords_id, damages)
             SELECT a.wall_id, a.name, a.player_id, a.coords_id, a.damages
               FROM map_walls_archive a WHERE a.converted_by = '" . self::TAG . "'"
        );
        $this->addSql(
            "DELETE r FROM resources r
               JOIN map_walls_archive a ON a.entity_id = r.player_id AND a.converted_by = '" . self::TAG . "'"
        );
        $this->addSql(
            "DELETE b FROM buildings b
               JOIN map_walls_archive a ON a.entity_id = b.player_id AND a.converted_by = '" . self::TAG . "'"
        );
        $this->addSql(
            "DELETE ec FROM entity_cells ec
               JOIN map_walls_archive a ON a.entity_id = ec.player_id AND a.converted_by = '" . self::TAG . "'"
        );
        $this->addSql(
            "DELETE p FROM players p
               JOIN map_walls_archive a ON a.entity_id = p.id AND a.converted_by = '" . self::TAG . "'"
        );
        $this->addSql("DELETE FROM map_walls_archive WHERE converted_by = '" . self::TAG . "'");

        $this->addSql(
            "UPDATE resource_types SET pv = 1 WHERE name IN ('cocotier2', 'cocotier3')"
        );
        $this->addSql(
            "UPDATE races SET structure_nature = 'obstacle' WHERE kind = 'structure' AND name IN ('cocotier2', 'cocotier3')"
        );
    }
}
