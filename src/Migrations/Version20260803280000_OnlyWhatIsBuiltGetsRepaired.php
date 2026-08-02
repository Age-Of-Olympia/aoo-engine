<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Repairability moves to the type: `races.repairable` (nullable = ask the
 * family) plus the `RequiresRepairableTarget` guard on `reparer`, whose
 * targeting goes back to the wide `structure` envelope so a type can be made
 * repairable in any family.
 */
final class Version20260803280000_OnlyWhatIsBuiltGetsRepaired extends AbstractMigration
{
    private const ENVELOPE = '{"allowed":["structure"]}';

    public function getDescription(): string
    {
        return 'races.repairable + RequiresRepairableTarget guard on reparer';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE races ADD COLUMN IF NOT EXISTS repairable TINYINT(1) NULL');

        /* By NAME: the action's id depends on the order catalogues were
         * seeded and already differs between databases. */
        $this->addSql(
            "INSERT INTO action_conditions (conditionType, parameters, action_id, execution_order, blocking, display_context)
             SELECT 'RequiresRepairableTarget', '{}', a.id, 1, 1, 1
               FROM actions a
              WHERE a.name = 'reparer'
                AND NOT EXISTS (
                    SELECT 1 FROM (SELECT action_id, conditionType FROM action_conditions) AS existing
                     WHERE existing.action_id = a.id
                       AND existing.conditionType = 'RequiresRepairableTarget'
                )"
        );

        /* Wide envelope: the type decides, and a family list here would stop
         * it from being overridden. */
        $this->addSql(
            "UPDATE action_conditions c
               JOIN actions a ON a.id = c.action_id
                SET c.parameters = ?
              WHERE a.name = 'reparer' AND c.conditionType = 'TargetType'",
            [self::ENVELOPE]
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "DELETE c FROM action_conditions c
               JOIN actions a ON a.id = c.action_id
              WHERE a.name = 'reparer' AND c.conditionType = 'RequiresRepairableTarget'"
        );

        /* Back to the family gate as the only one in place. */
        $this->addSql(
            "UPDATE action_conditions c
               JOIN actions a ON a.id = c.action_id
                SET c.parameters = '{\"allowed\":[\"building\",\"scenery\",\"item\"]}'
              WHERE a.name = 'reparer' AND c.conditionType = 'TargetType'"
        );

        $this->addSql('ALTER TABLE races DROP COLUMN IF EXISTS repairable');
    }
}
