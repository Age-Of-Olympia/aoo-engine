<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 3.1 schema alignment — add the `bonus_points` column to the
 * `players` table.
 *
 * The PlayerEntity has declared `bonus_points` since the entity's
 * creation (reserved for future season-carry-over XP: the "over-the-
 * limit" XP a player earns past the cap at season change). The column
 * was never actually added to the table. Doctrine hydration of any
 * PlayerEntity would therefore fail with a "column not found" SQL
 * error — an issue that has lain latent because no production code
 * path currently hydrates the entity (PlayerFactory::entity() has zero
 * callers as of !382's audit).
 *
 * This migration brings the table forward to match the entity before
 * Phase 3 starts migrating read-path callers onto the entity layer.
 *
 * Idempotent: MariaDB 10.2+ supports ADD COLUMN IF NOT EXISTS. Fresh
 * devcontainer/CI setups that already have the column (via the
 * updated db/init_noupdates.sql) run this as a no-op.
 */
final class Version20260419130000_AddBonusPointsToPlayers extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 3.1 — add players.bonus_points column (reserved for season-carry-over XP)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE `players` '
            . 'ADD COLUMN IF NOT EXISTS `bonus_points` INT(11) NOT NULL DEFAULT 0'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `players` DROP COLUMN IF EXISTS `bonus_points`');
    }
}
