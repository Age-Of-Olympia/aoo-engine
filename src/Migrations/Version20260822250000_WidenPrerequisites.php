<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * `prerequisites` holds a JSON list of forbidden passives; two values written
 * by Version20260822300000 are longer than the column's 50 characters.
 *
 * Versioned BEFORE that migration so a fresh database widens the column
 * first. A database where it already ran holds either the values cut to 50
 * characters (non-strict mode: invalid JSON) or the rows inserted before the
 * failure, once per attempt (the ALTER commits the transaction). The UPDATE
 * puts the values back whole; the DELETE keeps one row per name, the oldest,
 * and moves what players learned onto it first.
 */
final class Version20260822250000_WidenPrerequisites extends AbstractMigration
{
    private const FULL_VALUES = [
        'maitre_archer' => '{"forbidden": ["fulgurance", "pouvoir_titanique"]}',
        'fulgurance'    => '{"forbidden": ["pouvoir_titanique", "maitre_archer"]}',
    ];

    public function getDescription(): string
    {
        return 'prerequisites en VARCHAR(255) sur actions et action_passives';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE action_passives MODIFY prerequisites VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE actions MODIFY prerequisites VARCHAR(255) DEFAULT NULL');

        foreach (self::FULL_VALUES as $name => $value) {
            $this->addSql(
                'UPDATE action_passives SET prerequisites = ? WHERE name = ? AND prerequisites = LEFT(?, 50)',
                [$value, $name, $value]
            );
        }

        // IGNORE: a player holding both the original and a duplicate keeps the
        // original, the duplicate's row goes with the cascade below
        $this->addSql(
            'UPDATE IGNORE players_passives pp
               JOIN action_passives a ON a.id = pp.passive_id
               JOIN (SELECT name, MIN(id) AS id FROM action_passives GROUP BY name) k ON k.name = a.name
                SET pp.passive_id = k.id
              WHERE pp.passive_id <> k.id'
        );
        $this->addSql(
            'DELETE a FROM action_passives a
               JOIN action_passives b ON b.name = a.name AND b.id < a.id'
        );
    }

    /** Column widening is backward-compatible: nothing to undo. */
    public function down(Schema $schema): void
    {
    }
}
