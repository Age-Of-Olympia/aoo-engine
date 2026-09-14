<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Footsteps and the flag leave the elements: they are MARKS, a layer of
 * their own above the elements and under the characters.
 *
 * An element is one thing on the ground with an effect — water, fire,
 * mud, blood — and a cell holds one at most. A mark is drawn over it and
 * has no effect: several can share a cell, and one can sit on water.
 * Footsteps only lived in the effect catalogue because Element::put
 * demanded a row there; the `is_map_marker` flag existed to hide them
 * again everywhere else. Both go with them.
 *
 * `is_map_marker` itself stays in the table for the deployment window
 * (the running code still selects it); a later migration drops it.
 *
 * `mark_turns` is what an element adds to the life of the footsteps of
 * whoever carries its effect — mud made a footstep last two turns
 * instead of one, as an `if` on the name. Now a number on the row.
 *
 * Cells holding two elements today are authoring, left as they are:
 * the rule is enforced when an element is laid, and the maps are the
 * animators' to clean (300 of them are fire drawn over lava).
 */
final class Version20260914120000_MarksAboveElements extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'crée map_marks (traces de pas, drapeau) au-dessus des éléments, et effects.mark_turns à la place du drapeau marqueur';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE IF NOT EXISTS map_marks (
                id INT AUTO_INCREMENT NOT NULL,
                name VARCHAR(255) NOT NULL,
                coords_id INT NOT NULL,
                endTime INT NOT NULL DEFAULT 0,
                PRIMARY KEY (name, coords_id),
                UNIQUE KEY id (id),
                KEY coords_id (coords_id),
                CONSTRAINT map_marks_ibfk_1 FOREIGN KEY (coords_id) REFERENCES coords (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
        );

        $this->addSql(
            "INSERT IGNORE INTO map_marks (name, coords_id, endTime)
             SELECT name, coords_id, endTime FROM map_elements
              WHERE name LIKE 'trace\\_pas%' OR name = 'flag_red'"
        );
        $this->addSql("DELETE FROM map_elements WHERE name LIKE 'trace\\_pas%' OR name = 'flag_red'");

        /* A mark is no effect: nothing should carry one. */
        $this->addSql("DELETE FROM players_effects WHERE name LIKE 'trace\\_pas%' OR name = 'flag_red'");
        $this->addSql("DELETE FROM effects WHERE name LIKE 'trace\\_pas%'");

        if (!$this->columnExists('effects', 'mark_turns')) {
            $this->addSql('ALTER TABLE effects ADD mark_turns INT NOT NULL DEFAULT 0');
        }
        $this->addSql("UPDATE effects SET mark_turns = 1 WHERE name = 'boue'");
    }

    /**
     * The rows go back where they came from; the footstep effects do not:
     * they carried nothing but a name, and the code no longer reads them.
     */
    public function down(Schema $schema): void
    {
        $this->addSql(
            'INSERT IGNORE INTO map_elements (name, coords_id, endTime)
             SELECT name, coords_id, endTime FROM map_marks'
        );
        $this->addSql('DROP TABLE IF EXISTS map_marks');
        $this->addSql('ALTER TABLE effects DROP COLUMN IF EXISTS mark_turns');
    }

    private function columnExists(string $table, string $column): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );
    }
}
