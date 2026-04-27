<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 4.6 — restore an FK guardrail on the collapsed real↔tutorial
 * link.
 *
 * Phase 4.5 dropped `tutorial_players.real_player_id` along with its
 * `ON DELETE CASCADE` FK into `players.id`. The link now lives
 * exclusively on `players.real_player_id_ref`, which (until this
 * migration) carries no referential constraint. That leaves a gap:
 * deleting a real player leaves the tutorial player's
 * `real_player_id_ref` pointing at a row that no longer exists.
 *
 * This migration adds a self-referential FK with `ON DELETE SET NULL`:
 *
 *   players.real_player_id_ref  →  players.id  ON DELETE SET NULL
 *
 * `SET NULL` (not `CASCADE`) is deliberate — cascading would hard-
 * delete the tutorial player's own `players` row, but orphan cleanup
 * wants to handle that via `TutorialPlayerCleanup` (which also soft-
 * deletes the matching `tutorial_players` row and fans out to ~25
 * FK tables). Setting the ref to NULL leaves the tutorial row
 * visibly orphaned; the orphan scan picks it up from there.
 *
 * Pre-migration data hygiene: any existing row whose
 * `real_player_id_ref` points at a non-existent `players.id` will
 * trip the FK validation. The `up()` method NULLs those first.
 *
 * Idempotent: checks information_schema for the constraint and skips
 * if it already exists.
 */
final class Version20260419210000_AddRealPlayerIdRefForeignKey extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 4.6 — add FK players.real_player_id_ref → players.id ON DELETE SET NULL';
    }

    public function isTransactional(): bool
    {
        // MariaDB auto-commits the ALTER; keeping consistent with other
        // DDL migrations in this tree.
        return false;
    }

    public function up(Schema $schema): void
    {
        // Scrub dangling refs (rows whose real_player_id_ref points at
        // a players.id that no longer exists). The FK validation would
        // otherwise refuse to apply.
        $this->addSql('
            UPDATE `players` p
            LEFT JOIN `players` target ON target.id = p.real_player_id_ref
            SET p.real_player_id_ref = NULL
            WHERE p.real_player_id_ref IS NOT NULL
              AND target.id IS NULL
        ');

        // Idempotent: skip if the constraint already exists.
        if (!$this->fkExists('players', 'fk_players_real_player_id_ref')) {
            $this->addSql(
                'ALTER TABLE `players` ADD CONSTRAINT `fk_players_real_player_id_ref` '
                . 'FOREIGN KEY (`real_player_id_ref`) REFERENCES `players` (`id`) ON DELETE SET NULL'
            );
        }
    }

    public function down(Schema $schema): void
    {
        if ($this->fkExists('players', 'fk_players_real_player_id_ref')) {
            $this->addSql('ALTER TABLE `players` DROP FOREIGN KEY `fk_players_real_player_id_ref`');
        }
    }

    private function fkExists(string $table, string $constraintName): bool
    {
        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND CONSTRAINT_NAME = ?',
            [$table, $constraintName]
        );

        return (int) $count > 0;
    }
}
