<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * BuffCompute ran the Dodge precondition of attacks: a heal or a buff cast
 * on an ally in a stance (parade, dédoublement, clé de bras) was "dodged",
 * used up the ally's stance, could immobilise the caster, and was still
 * paid. Stances answer attacks only; NoBerserk and AntiSpell stay.
 */
final class Version20261011240000_BuffsAreNotDodged extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'BuffCompute : plus de précondition Dodge, un soin ou un buff ne s\'esquive pas';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "DELETE FROM action_condition_preconditions
              WHERE parent_condition_type = 'BuffCompute' AND precondition_type = 'Dodge'"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "INSERT INTO action_condition_preconditions (parent_condition_type, precondition_type, parameters, order_index, blocking)
             SELECT 'BuffCompute', 'Dodge', NULL, 0, 0
              WHERE NOT EXISTS (
                    SELECT 1 FROM action_condition_preconditions
                     WHERE parent_condition_type = 'BuffCompute' AND precondition_type = 'Dodge')"
        );
    }
}
