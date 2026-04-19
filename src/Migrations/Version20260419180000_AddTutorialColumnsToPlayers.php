<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 4.3 hotfix — idempotently adds `tutorial_session_id` and
 * `real_player_id_ref` columns to `players` on any DB that predates
 * them.
 *
 * Both columns are declared on `TutorialPlayer` (Phase 3.1
 * !383 fixed their `name:` attributes to match snake_case), and they
 * ARE in `db/init_noupdates.sql` — fresh CI setups don't need this
 * migration. But dev DBs that pre-date the tutorial-system work
 * never had them applied, and Phase 4.3 (!393) introduced the first
 * hot-path caller (`TutorialResourceManager::createTutorialPlayerAsEntity`
 * via `$em->find(TutorialPlayer::class, ...)`) that triggers
 * STI-wide hydration and fails on any DB missing them:
 *
 *   Unknown column 't0.tutorial_session_id' in 'SELECT'
 *
 * These columns are logically part of
 * `Version20251127000000_CreateCompleteTutorialSystem`, but that
 * migration is monolithic and not intended to be replayed; this
 * hotfix is a focused add-only safety net, idempotent via
 * `ADD COLUMN IF NOT EXISTS`. Running it is a no-op on any DB that
 * already has the columns (fresh CI, reset test DBs, etc.).
 *
 * Once applied, reset_test_database.sh clones the column into
 * `aoo4_test` automatically (init_test_from_dump.sh clones structure
 * from `aoo4`).
 */
final class Version20260419180000_AddTutorialColumnsToPlayers extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 4.3 hotfix — idempotently add tutorial_session_id + real_player_id_ref to players (STI-required for TutorialPlayer hydration)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE `players` '
            . 'ADD COLUMN IF NOT EXISTS `tutorial_session_id` VARCHAR(36) DEFAULT NULL '
            . "COMMENT 'Tutorial session UUID (for tutorial players)'"
        );
        $this->addSql(
            'ALTER TABLE `players` '
            . 'ADD COLUMN IF NOT EXISTS `real_player_id_ref` INT(11) DEFAULT NULL '
            . "COMMENT 'Real player ID reference (for tutorial players)'"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `players` DROP COLUMN IF EXISTS `real_player_id_ref`');
        $this->addSql('ALTER TABLE `players` DROP COLUMN IF EXISTS `tutorial_session_id`');
    }
}
