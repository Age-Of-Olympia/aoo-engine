<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Seed the `attack` type-level instructions — the data-driven home for what
 * AttackAction::initAutomaticOutcomeInstructions() adds in code: an
 * ApplyStatus(adrenaline, 2 days) then an ObjectEffect (no params). Every
 * action whose class extends AttackAction (melee/distance/technique) inherits
 * them via the resolver, in this order.
 *
 * This flips the executor gate: once these rows exist the executor runs them
 * and stops running the code automatics — same instructions, same params, same
 * order, so behaviour is unchanged. The code path is removed in the next step.
 *
 * Idempotent: clears any existing `attack` rows first.
 */
final class Version20260622130000_SeedAttackTypeInstructions extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Seed the 'attack' type-level instructions (adrenaline + object effect)";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("DELETE FROM action_type_instructions WHERE type_key = 'attack'");
        $this->addSql(
            'INSERT INTO action_type_instructions (type_key, instruction_type, parameters, order_index) '
            . "VALUES ('attack', 'applystatus', :params, 0)",
            ['params' => '{"adrenaline":true,"duration":3}']
        );
        $this->addSql(
            'INSERT INTO action_type_instructions (type_key, instruction_type, parameters, order_index) '
            . "VALUES ('attack', 'objecteffect', NULL, 1)"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM action_type_instructions WHERE type_key = 'attack'");
    }
}
