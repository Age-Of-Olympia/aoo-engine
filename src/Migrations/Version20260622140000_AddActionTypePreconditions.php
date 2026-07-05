<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create the `action_type_preconditions` table.
 *
 * Holds conditions attached to an action *type* (e.g. "attack"), or globally to
 * every action (an empty type_key), that run as preconditions before the
 * action's own conditions — the data-driven home for what BaseCondition and the
 * *Compute conditions used to inject in code (PlanCondition/enfers, Obstacle,
 * Dodge, NoBerserk, AntiSpell). Same shape as action_type_instructions plus a
 * blocking flag.
 *
 * Idempotent: CREATE TABLE IF NOT EXISTS.
 */
final class Version20260622140000_AddActionTypePreconditions extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create action_type_preconditions (type-level / global inherited preconditions)';
    }

    public function isTransactional(): bool
    {
        // DDL auto-commits on MySQL, so wrapping it in a transaction leaves
        // nothing to commit at the end ("no active transaction").
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE IF NOT EXISTS `action_type_preconditions` ('
            . '`id` INT AUTO_INCREMENT NOT NULL, '
            . '`type_key` VARCHAR(100) NOT NULL, '
            . '`condition_type` VARCHAR(100) NOT NULL, '
            . '`parameters` LONGTEXT DEFAULT NULL, '
            . '`order_index` INT DEFAULT 0 NOT NULL, '
            . '`blocking` TINYINT(1) DEFAULT 1 NOT NULL, '
            . 'INDEX `idx_action_type_preconditions_type_key` (`type_key`), '
            . 'PRIMARY KEY (`id`)'
            . ') DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS `action_type_preconditions`');
    }
}
