<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * `resource_types` goes: one catalogue is enough.
 *
 * Two tables described the same types. `resource_types` held a name and a
 * number whose SIGN carried the meaning — -1 harvestable, positive a life
 * total, absent indestructible — while `races` held those same types with a
 * `structure_nature` saying it in words and a `pv` meaning only life.
 *
 * Two sources for one truth is one too many, and they had already drifted: a
 * type made harvestable in the admin's type editor stayed an obstacle to the
 * map editor, which read the other table. That drift is exactly what stranded
 * 58 coconut palms between two conversions.
 *
 * Nothing is lost in the move: every name here has its `races` row, and the
 * sign it encoded is read from the nature instead.
 */
final class Version20260801120000_OneCatalogueForTheTypes extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drops resource_types: races is the only catalogue of structure types';
    }

    public function up(Schema $schema): void
    {
        $conn = $this->connection;

        if ($conn->fetchFirstColumn("SHOW TABLES LIKE 'resource_types'") === []) {
            $this->write('resource_types est déjà absente.');

            return;
        }

        /* A name here with no `races` row would lose what it declared. Said
         * out loud rather than dropped silently — the row is created with the
         * nature its sign meant, so the editors keep answering the same. */
        $orphans = $conn->fetchAllAssociative(
            "SELECT t.name, t.pv
               FROM resource_types t
              WHERE NOT EXISTS (
                    SELECT 1 FROM races r
                     WHERE r.name COLLATE utf8mb4_general_ci = t.name COLLATE utf8mb4_general_ci
                )"
        );

        foreach ($orphans as $orphan) {
            $name = (string) $orphan['name'];
            $pv = (int) $orphan['pv'];

            $conn->executeStatement(
                "INSERT IGNORE INTO races
                    (code, name, label, description, playable, hidden, kind, structure_nature,
                     bleeds, wound_color, blocks_passage, blocks_projectiles, bgColor, color, faction, plan, pv)
                 VALUES (?, ?, ?, '', 0, 1, 'structure', ?, '', '#cd7f32', 1, 1, '#7f7f7f', 'black', '', '', ?)",
                [
                    strtoupper($name),
                    $name,
                    ucfirst(str_replace('_', ' ', $name)),
                    $pv < 0 ? 'ressource' : 'obstacle',
                    $pv > 0 ? $pv : 100,
                ]
            );
        }

        if ($orphans !== []) {
            $this->write(sprintf(
                '%d type(s) n\'existaient qu\'ici et rejoignent le catalogue : %s',
                count($orphans),
                implode(', ', array_column($orphans, 'name'))
            ));
        }

        $conn->executeStatement('DROP TABLE resource_types');
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE IF NOT EXISTS resource_types (
                name VARCHAR(255) NOT NULL,
                pv INT NOT NULL,
                PRIMARY KEY (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
        );

        /* Rebuilt from the surviving catalogue, sign and all. */
        $this->addSql(
            "INSERT IGNORE INTO resource_types (name, pv)
             SELECT name, CASE WHEN structure_nature = 'ressource' THEN -1 ELSE pv END
               FROM races WHERE kind = 'structure'"
        );
    }
}
