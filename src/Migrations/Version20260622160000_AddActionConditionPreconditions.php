<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create the `action_condition_preconditions` table.
 *
 * Holds preconditions keyed on a CONDITION type (e.g. "MeleeCompute") — the
 * data-driven home for what the *Compute conditions array_push in code
 * (Dodge/NoBerserk/Obstacle/AntiSpell). Keyed on the condition, not the action
 * type, because the behaviour follows the condition (a spell action can carry a
 * MeleeCompute).
 *
 * Idempotent: CREATE TABLE IF NOT EXISTS.
 */
final class Version20260622160000_AddActionConditionPreconditions extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create action_condition_preconditions (condition-keyed inherited preconditions)';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE IF NOT EXISTS `action_condition_preconditions` ('
            . '`id` INT AUTO_INCREMENT NOT NULL, '
            . '`parent_condition_type` VARCHAR(100) NOT NULL, '
            . '`precondition_type` VARCHAR(100) NOT NULL, '
            . '`parameters` LONGTEXT DEFAULT NULL, '
            . '`order_index` INT DEFAULT 0 NOT NULL, '
            . 'INDEX `idx_action_condition_preconditions_parent` (`parent_condition_type`), '
            . 'PRIMARY KEY (`id`)'
            . ') DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS `action_condition_preconditions`');
    }
}
