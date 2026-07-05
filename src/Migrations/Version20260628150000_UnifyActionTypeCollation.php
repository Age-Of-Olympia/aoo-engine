<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Unify the collation of the action_type_* family on utf8mb4_unicode_ci.
 *
 * action_type_logs and action_type_xp were created utf8mb4_unicode_ci, but
 * action_type_instructions, action_type_preconditions and
 * action_condition_preconditions were created utf8mb4_general_ci (and
 * action_type_instructions is utf8mb4_uca1400_ai_ci in init_noupdates.sql) — so
 * the same table carried different collations depending on install path. That is
 * a latent "illegal mix of collations" the day any resolver JOINs these
 * type_key / condition_type columns. Converge the three odd ones here.
 *
 * Idempotent: CONVERT TO on a table already at utf8mb4_unicode_ci is a no-op.
 */
final class Version20260628150000_UnifyActionTypeCollation extends AbstractMigration
{
    private const TABLES = [
        'action_type_instructions',
        'action_type_preconditions',
        'action_condition_preconditions',
    ];

    public function getDescription(): string
    {
        return 'Unify action_type_* tables on utf8mb4_unicode_ci';
    }

    public function isTransactional(): bool
    {
        // DDL auto-commits on MySQL/MariaDB, so wrapping it in a transaction
        // leaves nothing to commit at the end ("no active transaction").
        return false;
    }

    public function up(Schema $schema): void
    {
        foreach (self::TABLES as $table) {
            $this->addSql(
                "ALTER TABLE `{$table}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
            );
        }
    }

    public function down(Schema $schema): void
    {
        foreach (self::TABLES as $table) {
            $this->addSql(
                "ALTER TABLE `{$table}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci"
            );
        }
    }
}
