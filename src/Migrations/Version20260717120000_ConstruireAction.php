<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * First data-driven construction action: 'construire_palissade'
 * (docs/design-items-instances.md §4, gaps G1+G2 now implemented).
 *
 * Shape: self action (type buff, outcome on self) — the button shows on
 * your own tile; 10 bois (RequiresItem, consumed) + 1 A
 * (RequiresTraitValue) place a real 'palissade' Building on a free
 * adjacent tile (PlaceStructure → BuildingService), owner = the
 * builder, faction reprise.
 *
 * This is the exemplar the admins clone in the workbench for other
 * buildables (tour, entrepôt…) — build.php's dumb walls retire
 * progressively. Catalog-only: granting the action (starter packs,
 * école de guerre) is a balance decision.
 *
 * Idempotent: only created if the name is free; skipped entirely when
 * the 'bois' item is absent from this environment's catalog.
 */
final class Version20260717120000_ConstruireAction extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Add the construire_palissade action (10 bois + 1 A → palissade Building)";
    }

    public function up(Schema $schema): void
    {
        $exists = $this->connection->fetchOne("SELECT id FROM actions WHERE name = 'construire_palissade'");
        if ($exists !== false) {
            return;
        }

        $boisId = $this->connection->fetchOne("SELECT id FROM items WHERE name = 'bois'");
        if ($boisId === false) {
            $this->warnIf(true, "item 'bois' absent du catalogue — action construire_palissade non créée");
            return;
        }

        $this->addSql(
            "INSERT INTO actions (name, icon, type, display_name, text, level)
             VALUES ('construire_palissade', 'ra-tower', 'buff', 'Construire une palissade',
                     'Dresse une palissade de pieux sur une case libre adjacente — dix bois et de l''huile de coude.', 1)"
        );

        foreach ([
            ['TargetType', ['allowed' => ['character']], 0],
            ['RequiresItem', ['item' => (int) $boisId, 'n' => 10, 'consume' => true], 1],
            ['RequiresTraitValue', ['a' => 1], 3],
        ] as [$type, $params, $order]) {
            $this->addSql(
                "INSERT INTO action_conditions (conditionType, parameters, action_id, execution_order, blocking)
                 SELECT ?, ?, id, ?, 1 FROM actions WHERE name = 'construire_palissade'",
                [$type, json_encode($params), $order]
            );
        }

        $this->addSql(
            "INSERT INTO action_outcomes (apply_to, name, on_success, action_id)
             SELECT 'self', 'construction', 1, id FROM actions WHERE name = 'construire_palissade'"
        );
        $this->addSql(
            "INSERT INTO outcome_instructions (type, parameters, orderIndex, outcome_id)
             SELECT 'placestructure', ?, 0, o.id
             FROM action_outcomes o JOIN actions a ON a.id = o.action_id
             WHERE a.name = 'construire_palissade' AND o.name = 'construction'",
            [json_encode(['type' => 'palissade'])]
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "DELETE oi FROM outcome_instructions oi
             JOIN action_outcomes o ON o.id = oi.outcome_id
             JOIN actions a ON a.id = o.action_id
             WHERE a.name = 'construire_palissade'"
        );
        $this->addSql("DELETE o FROM action_outcomes o JOIN actions a ON a.id = o.action_id WHERE a.name = 'construire_palissade'");
        $this->addSql("DELETE FROM action_conditions WHERE action_id IN (SELECT id FROM actions WHERE name = 'construire_palissade')");
        $this->addSql("DELETE FROM actions WHERE name = 'construire_palissade'");
    }
}
