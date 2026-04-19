<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 4.5 — collapse the dual real↔tutorial link columns.
 *
 * Before this migration, the tutorial→real-player relationship was
 * stored twice:
 *   - `tutorial_players.real_player_id` (NOT NULL, indexed, FK-cascade
 *     to `players.id`) — the original link, predates the entity layer.
 *   - `players.real_player_id_ref` (nullable) — added in Phase 3.1 so
 *     the Doctrine `TutorialPlayer` entity could map it. Phase 4.4's
 *     factory hotfix started populating it alongside the old column.
 *
 * Every reader now resolves through `players.real_player_id_ref`
 * (Phase 4.5 reader rewrites); the factory stopped writing the old
 * column in the same MR. This migration drops the FK, index, and
 * column. Run AFTER the code-only changes deploy.
 *
 * Pre-migration audit (run on target DB, expect zero rows):
 *   SELECT COUNT(*)
 *   FROM tutorial_players tp
 *   LEFT JOIN players p ON p.id = tp.player_id
 *   WHERE p.player_type = 'tutorial'
 *     AND (p.real_player_id_ref IS NULL
 *          OR p.real_player_id_ref <> tp.real_player_id);
 *
 * If the audit returns rows, run this backfill first:
 *   UPDATE players p
 *   JOIN tutorial_players tp ON tp.player_id = p.id
 *   SET p.real_player_id_ref = tp.real_player_id
 *   WHERE p.player_type = 'tutorial' AND p.real_player_id_ref IS NULL;
 *
 * `down()` restores the column, rebuilds the values from
 * `players.real_player_id_ref`, and re-adds the index/FK. A rollback
 * is destructive only if any code running against the old schema
 * wrote to the old column AFTER this migration ran — which can't
 * happen given the writer is gone.
 */
final class Version20260419200000_DropTutorialPlayersRealPlayerIdColumn extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 4.5 — drop tutorial_players.real_player_id (collapsed into players.real_player_id_ref)';
    }

    public function isTransactional(): bool
    {
        // MariaDB implicitly commits at every ALTER TABLE; wrapping DDL
        // in a transaction isn't meaningful and trips Doctrine's
        // all-or-nothing guard.
        return false;
    }

    public function up(Schema $schema): void
    {
        // FK first (blocks the index drop), then index, then column.
        $this->addSql('ALTER TABLE `tutorial_players` DROP FOREIGN KEY `tutorial_players_ibfk_1`');
        $this->addSql('ALTER TABLE `tutorial_players` DROP INDEX `idx_real_player`');
        $this->addSql('ALTER TABLE `tutorial_players` DROP COLUMN `real_player_id`');
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE `tutorial_players` '
            . 'ADD COLUMN `real_player_id` INT(11) NOT NULL '
            . "COMMENT 'Link to actual player account' AFTER `id`"
        );
        // Rebuild values from the canonical column before re-enforcing NOT NULL + FK.
        $this->addSql('
            UPDATE `tutorial_players` tp
            JOIN `players` p ON p.id = tp.player_id
            SET tp.real_player_id = p.real_player_id_ref
            WHERE p.player_type = "tutorial" AND p.real_player_id_ref IS NOT NULL
        ');
        $this->addSql('ALTER TABLE `tutorial_players` ADD INDEX `idx_real_player` (`real_player_id`)');
        $this->addSql(
            'ALTER TABLE `tutorial_players` '
            . 'ADD CONSTRAINT `tutorial_players_ibfk_1` '
            . 'FOREIGN KEY (`real_player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE'
        );
    }
}
