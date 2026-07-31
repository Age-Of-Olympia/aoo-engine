<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The resources become entities: one per cell, cells and state included.
 *
 * The heart of the chantier. 26 656 rows that knew nothing of each other become
 * entities that occupy a cell, carry a life, take a hit and hold their own
 * state — the same shape walls, decor and altars took before them.
 *
 * Set-based on purpose: one INSERT … SELECT per table rather than a loop over
 * twenty-six thousand rows. The ids are handed out by arithmetic on a window
 * function, so a row's entity id is derived and not fetched back.
 *
 * The game is DOWN while this runs (maintenance flag). That is the deliberate
 * trade: no tolerant reader, no transitional branch to remember and delete —
 * the code deployed after this migration knows entities only.
 *
 * Reversible without a host backup: every converted row is archived first, and
 * `down()` re-inserts it before dismantling the entities.
 */
final class Version20260731100000_ResourcesBecomeEntities extends AbstractMigration
{
    private const ID_START = 50000000;

    public function getDescription(): string
    {
        return 'map_resources rows become resource entities with their cells and state';
    }

    public function up(Schema $schema): void
    {
        $conn = $this->connection;

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

        /* Only what the catalogue knows how to be: a harvestable type with a
         * `races` row. Anything else stays a map row and is reported, never
         * guessed at. */
        $conn->executeStatement(
            "CREATE TEMPORARY TABLE tmp_resource_conversion (
                wall_id INT NOT NULL,
                entity_id INT NOT NULL,
                coords_id INT NOT NULL,
                name VARCHAR(255) NOT NULL,
                damages INT NOT NULL,
                player_id INT NULL,
                plan VARCHAR(255) NOT NULL,
                z SMALLINT NOT NULL,
                x INT NOT NULL,
                y INT NOT NULL,
                PRIMARY KEY (wall_id),
                KEY k_entity (entity_id)
            /* Le jeu de caractères est DÉCLARÉ : sans lui, une table aux
             * colonnes écrites à la main prend le défaut de la BASE, latin1
             * sur un hébergement ancien. La table naît puis disparaît avec la
             * migration — le défaut ne laisse donc aucune trace à inspecter,
             * et c'est ici qu'un déploiement est mort. */
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );

        $offset = $this->nextId();

        $conn->executeStatement(
            "INSERT INTO tmp_resource_conversion
                (wall_id, entity_id, coords_id, name, damages, player_id, plan, z, x, y)
             SELECT m.id,
                    ? + ROW_NUMBER() OVER (ORDER BY m.id) - 1,
                    m.coords_id, m.name, m.damages, m.player_id,
                    c.plan, c.z, c.x, c.y
               FROM map_resources m
               JOIN coords c ON c.id = m.coords_id
               JOIN races r
                 ON CONVERT(r.name USING utf8mb4) = CONVERT(m.name USING utf8mb4)
                AND r.structure_nature = 'ressource'",
            [$offset]
        );

        $count = (int) $conn->fetchOne('SELECT COUNT(*) FROM tmp_resource_conversion');

        if ($count === 0) {
            $this->write('Aucune ressource à convertir.');
            $conn->executeStatement('DROP TEMPORARY TABLE tmp_resource_conversion');

            return;
        }

        $nextDisplayId = (int) $conn->fetchOne(
            "SELECT COALESCE(MAX(display_id), 0) + 1 FROM players WHERE player_type = 'resource'"
        );

        $conn->executeStatement(
            "INSERT INTO players
                (id, player_type, display_id, name, race, avatar, portrait,
                 coords_id, nextTurnTime, registerTime, text)
             SELECT t.entity_id,
                    'resource',
                    ? + ROW_NUMBER() OVER (ORDER BY t.entity_id) - 1,
                    COALESCE(NULLIF(r.label, ''), t.name),
                    t.name,
                    CONCAT('img/walls/', t.name, '.png'),
                    CONCAT('img/walls/', t.name, '.png'),
                    t.coords_id, 0, ?, ''
               FROM tmp_resource_conversion t
               JOIN races r
                 ON CONVERT(r.name USING utf8mb4) = CONVERT(t.name USING utf8mb4)",
            [$nextDisplayId, time()]
        );

        /* One cell each: a resource has never spanned more than its own. */
        $conn->executeStatement(
            "INSERT INTO entity_cells (player_id, coords_id, plan, z, x, y, piece, role)
             SELECT t.entity_id, t.coords_id, t.plan, t.z, t.x, t.y, 0, 'block'
               FROM tmp_resource_conversion t"
        );

        /* Exhausted stays exhausted: `damages = -2` becomes a dated state. Only
         * those rows get a satellite — an absent row means standing. */
        $exhausted = $conn->executeStatement(
            "INSERT INTO resources (player_id, exhausted_at)
             SELECT t.entity_id, NOW() FROM tmp_resource_conversion t WHERE t.damages = -2"
        );

        $conn->executeStatement(
            "INSERT IGNORE INTO map_walls_archive
                (wall_id, name, player_id, coords_id, damages, entity_id, converted_at)
             SELECT t.wall_id, t.name, t.player_id, t.coords_id, t.damages, t.entity_id, NOW()
               FROM tmp_resource_conversion t"
        );

        $conn->executeStatement(
            'DELETE m FROM map_resources m JOIN tmp_resource_conversion t ON t.wall_id = m.id'
        );

        $left = (int) $conn->fetchOne('SELECT COUNT(*) FROM map_resources');

        $conn->executeStatement('DROP TEMPORARY TABLE tmp_resource_conversion');

        $this->write(sprintf(
            '%d ressource(s) converties, dont %d épuisée(s). %d ligne(s) map_resources restantes '
            . '(types hors catalogue des récoltables : autels, unique_*, murs).',
            $count,
            $exhausted,
            $left
        ));
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
        /* The rows come back from the archive, then the entities go — cells and
         * satellites first, both keys being unforgiving. */
        $this->addSql(
            "INSERT IGNORE INTO map_resources (id, name, player_id, coords_id, damages)
             SELECT a.wall_id, a.name, a.player_id, a.coords_id, a.damages
               FROM map_walls_archive a
               JOIN players p ON p.id = a.entity_id AND p.player_type = 'resource'"
        );
        $this->addSql(
            "DELETE r FROM resources r JOIN players p ON p.id = r.player_id WHERE p.player_type = 'resource'"
        );
        $this->addSql(
            "DELETE ec FROM entity_cells ec JOIN players p ON p.id = ec.player_id WHERE p.player_type = 'resource'"
        );
        $this->addSql(
            "DELETE a FROM map_walls_archive a JOIN players p ON p.id = a.entity_id AND p.player_type = 'resource'"
        );
        $this->addSql("DELETE FROM players WHERE player_type = 'resource'");
    }
}
