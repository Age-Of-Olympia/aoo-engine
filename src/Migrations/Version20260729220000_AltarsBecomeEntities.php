<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Altars become entities, and the third address closes.
 *
 * An altar lived in two places that knew nothing of each other: a
 * `map_resources` row for the obstacle, a `map_triggers` row for the god. It
 * becomes one entity carrying both — `players.godId` for the god,
 * `entity_cells` for the cell — so nothing can hold one without the other.
 *
 * Measured on the production copy: 24 cells bear an altar, four of them TWICE
 * (duplicate rows), so cells are deduped and one entity is made per cell. Two
 * carry damage — including a plain `altar`, not just a broken one, which is
 * why the wound is read for all of them and not for the broken alone.
 *
 * `altar_broken` becomes a damaged altar rather than a type of its own: the
 * broken look comes from the sprite, as it does for any structure. Its wound
 * is at least half its life, so it reads as broken.
 *
 * A god is copied from the trigger on the same cell whenever it names a
 * `race='dieu'` player — including a real account, which one of them is. Race
 * decides godhood; that is already the rule the Faith ranking applies.
 *
 * The triggers are left standing: the ranking still reads them until the
 * ranking itself moves. Rows are archived before deletion, so the way back is
 * a re-insert and not a restore.
 */
final class Version20260729220000_AltarsBecomeEntities extends AbstractMigration
{
    private const ID_START = 20000000;
    private const ALTAR_PV = 25;

    public function getDescription(): string
    {
        return 'Altars become building entities carrying their god and their cell';
    }

    public function up(Schema $schema): void
    {
        $conn = $this->connection;

        if ((int) $conn->fetchOne("SELECT COUNT(*) FROM races WHERE name = 'altar'") === 0) {
            $this->write('Type « altar » absent du catalogue : rien à convertir.');

            return;
        }

        $conn->executeStatement(
            'CREATE TABLE IF NOT EXISTS map_walls_archive (
                wall_id INT NOT NULL,
                name VARCHAR(255) NOT NULL,
                player_id INT NULL,
                coords_id INT NOT NULL,
                damages INT NOT NULL,
                entity_id INT NOT NULL,
                converted_at DATETIME NOT NULL,
                PRIMARY KEY (wall_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
        );

        $nextId = $this->nextId();
        $nextDisplayId = (int) $conn->fetchOne(
            "SELECT COALESCE(MAX(display_id), 0) + 1 FROM players WHERE player_type = 'building'"
        );

        $made = 0;

        foreach ($this->cellsToConvert() as $cell) {
            $this->makeAltar($nextId, $nextDisplayId, $cell);
            $nextId++;
            $nextDisplayId++;
            $made++;
        }

        $this->write(sprintf('%d autel(s) converti(s) en entités.', $made));
    }

    /**
     * One row per CELL bearing an altar, plus the cells a trigger names
     * without any resource under it — an altar that was only ever a god.
     *
     * @return list<array<string, mixed>>
     */
    private function cellsToConvert(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT c.id AS coords_id, c.x, c.y, c.z, c.plan,
                    MAX(r.name LIKE 'altar\\_broken') AS broken,
                    MAX(COALESCE(r.damages, 0)) AS damages,
                    MAX(r.player_id) AS owner_id
               FROM coords c
               JOIN map_resources r ON r.coords_id = c.id AND r.name LIKE 'altar%'
              GROUP BY c.id, c.x, c.y, c.z, c.plan

              UNION

             SELECT c.id AS coords_id, c.x, c.y, c.z, c.plan,
                    0 AS broken, 0 AS damages, NULL AS owner_id
               FROM coords c
               JOIN map_triggers t ON t.coords_id = c.id AND t.name = 'altar'
              WHERE NOT EXISTS (
                    SELECT 1 FROM map_resources r
                     WHERE r.coords_id = c.id AND r.name LIKE 'altar%'
              )
                AND EXISTS (
                    SELECT 1 FROM players p
                     WHERE p.race = 'dieu' AND p.id = CAST(t.params AS SIGNED)
                )
              GROUP BY c.id, c.x, c.y, c.z, c.plan"
        );

        return $rows;
    }

    /**
     * @param array<string, mixed> $cell
     */
    private function makeAltar(int $id, int $displayId, array $cell): void
    {
        $conn = $this->connection;
        $coordsId = (int) $cell['coords_id'];
        $broken = (bool) $cell['broken'];

        $god = $conn->fetchAssociative(
            "SELECT p.id, p.name
               FROM map_triggers t
               JOIN players p ON p.id = CAST(t.params AS SIGNED) AND p.race = 'dieu'
              WHERE t.coords_id = ? AND t.name = 'altar'
              ORDER BY t.id
              LIMIT 1",
            [$coordsId]
        );

        $godId = $god === false ? 0 : (int) $god['id'];
        $name = $god === false ? 'Autel' : 'Autel de ' . $god['name'];
        $sprite = $broken ? 'img/walls/altar_broken.png' : 'img/walls/altar.png';

        $conn->executeStatement(
            "INSERT INTO players
                (id, player_type, display_id, name, race, avatar, portrait,
                 coords_id, godId, nextTurnTime, registerTime, text)
             VALUES (?, 'building', ?, ?, 'altar', ?, ?, ?, ?, 0, ?, '')",
            [$id, $displayId, $name, $sprite, $sprite, $coordsId, $godId, time()]
        );

        $conn->executeStatement(
            "INSERT INTO buildings (player_id, owner_id, faction, build_state)
             VALUES (?, ?, '', 'built')",
            [$id, $cell['owner_id'] === null ? null : (int) $cell['owner_id']]
        );

        $conn->executeStatement(
            "INSERT INTO entity_cells (player_id, coords_id, plan, z, x, y, piece, role)
             VALUES (?, ?, ?, ?, ?, ?, 0, 'block')",
            [$id, $coordsId, (string) $cell['plan'], (int) $cell['z'], (int) $cell['x'], (int) $cell['y']]
        );

        /* A broken altar is wounded at least to half, so its state reads off
         * the sprite; a wound already recorded wins if it is deeper. */
        $wound = max((int) $cell['damages'], $broken ? (int) ceil(self::ALTAR_PV / 2) : 0);

        if ($wound > 0) {
            $conn->executeStatement(
                "INSERT INTO players_bonus (player_id, name, n) VALUES (?, 'pv', ?)",
                [$id, -$wound]
            );
        }

        $conn->executeStatement(
            "INSERT IGNORE INTO map_walls_archive
                (wall_id, name, player_id, coords_id, damages, entity_id, converted_at)
             SELECT r.id, r.name, r.player_id, r.coords_id, r.damages, ?, NOW()
               FROM map_resources r
              WHERE r.coords_id = ? AND r.name LIKE 'altar%'",
            [$id, $coordsId]
        );

        $conn->executeStatement(
            "DELETE FROM map_resources WHERE coords_id = ? AND name LIKE 'altar%'",
            [$coordsId]
        );
    }

    private function nextId(): int
    {
        $max = (int) $this->connection->fetchOne(
            'SELECT COALESCE(MAX(id), 0) FROM players WHERE id BETWEEN ? AND ?',
            [self::ID_START, self::ID_START + 9999999]
        );

        return $max === 0 ? self::ID_START : $max + 1;
    }

    public function down(Schema $schema): void
    {
        /* The resources come back from the archive, then the entities go —
         * cells and satellites first, both keys being unforgiving. */
        $this->addSql(
            "INSERT IGNORE INTO map_resources (id, name, player_id, coords_id, damages)
             SELECT a.wall_id, a.name, a.player_id, a.coords_id, a.damages
               FROM map_walls_archive a
               JOIN players p ON p.id = a.entity_id AND p.race = 'altar'"
        );
        $this->addSql(
            "DELETE ec FROM entity_cells ec JOIN players p ON p.id = ec.player_id WHERE p.race = 'altar'"
        );
        $this->addSql(
            "DELETE pb FROM players_bonus pb JOIN players p ON p.id = pb.player_id WHERE p.race = 'altar'"
        );
        $this->addSql(
            "DELETE b FROM buildings b JOIN players p ON p.id = b.player_id WHERE p.race = 'altar'"
        );
        $this->addSql(
            "DELETE a FROM map_walls_archive a JOIN players p ON p.id = a.entity_id AND p.race = 'altar'"
        );
        $this->addSql("DELETE FROM players WHERE race = 'altar'");
    }
}
