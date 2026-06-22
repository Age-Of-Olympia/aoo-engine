<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create the `action_type_instructions` table.
 *
 * Holds outcome instructions attached to an action *type* (e.g. "attack")
 * rather than a single action, so every action of that type inherits them —
 * the data-driven home for what AttackAction used to add in code. Same shape
 * as outcome_instructions minus the outcome link: a type_key, the STI
 * discriminator, its JSON parameters and an order.
 *
 * Idempotent: CREATE TABLE IF NOT EXISTS.
 */
final class Version20260622120000_AddActionTypeInstructions extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create action_type_instructions (type-level inherited outcome instructions)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE IF NOT EXISTS `action_type_instructions` ('
            . '`id` INT AUTO_INCREMENT NOT NULL, '
            . '`type_key` VARCHAR(100) NOT NULL, '
            . '`instruction_type` VARCHAR(50) NOT NULL, '
            . '`parameters` LONGTEXT DEFAULT NULL, '
            . '`order_index` INT DEFAULT 0 NOT NULL, '
            . 'INDEX `idx_action_type_instructions_type_key` (`type_key`), '
            . 'PRIMARY KEY (`id`)'
            . ') DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS `action_type_instructions`');
    }
}
