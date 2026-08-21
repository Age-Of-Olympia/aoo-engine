<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Building a chest requires a bank on the plan:
 *
 * - items.requires_building names the building a type requires
 *   FINISHED on the plan before it can be built ('banque' for every
 *   lockable container) — the type carries its config, a future altar
 *   is one catalog value;
 * - the generic `construire` action gains three blocking conditions:
 *   RequiresFaction {scope: edifice} (a factionless player keeps
 *   palissades and walls, but cannot build an édifice),
 *   RequiresPlanBuilding (the required-building lookup) and ChestSite
 *   (allowed floor + the personal / faction owner choice, POST
 *   buildFor).
 *
 * Idempotent: the column and each condition are only created when
 * missing; an admin's own tuning is never overwritten.
 */
final class Version20260821234000_TheBankWatchesOverTheChests extends AbstractMigration
{
    private const CONDITIONS = [
        ['RequiresFaction', '{"scope":"edifice"}', 5],
        ['RequiresPlanBuilding', '{}', 6],
        ['ChestSite', '{}', 7],
    ];

    public function getDescription(): string
    {
        return 'items.requires_building (coffres → banque) + conditions RequiresFaction/RequiresPlanBuilding/ChestSite sur construire';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE items ADD COLUMN IF NOT EXISTS requires_building VARCHAR(50) NULL DEFAULT NULL'
        );

        // Only where unset: an admin's own tuning is never overwritten.
        $this->addSql(
            "UPDATE items SET requires_building = 'banque' WHERE lockable = 1 AND requires_building IS NULL"
        );

        foreach (self::CONDITIONS as [$type, $params, $order]) {
            $this->addSql(
                "INSERT INTO action_conditions (conditionType, parameters, action_id, execution_order, blocking)
                 SELECT '{$type}', '{$params}', a.id, {$order}, 1
                   FROM actions a
                  WHERE a.name = 'construire'
                    AND NOT EXISTS (
                        SELECT 1 FROM action_conditions ac
                         WHERE ac.action_id = a.id AND ac.conditionType = '{$type}'
                    )"
            );
        }
    }

    public function down(Schema $schema): void
    {
        foreach (self::CONDITIONS as [$type]) {
            $this->addSql(
                "DELETE ac FROM action_conditions ac
                   JOIN actions a ON a.id = ac.action_id
                  WHERE a.name = 'construire' AND ac.conditionType = '{$type}'"
            );
        }

        $this->addSql('ALTER TABLE items DROP COLUMN IF EXISTS requires_building');
    }
}
