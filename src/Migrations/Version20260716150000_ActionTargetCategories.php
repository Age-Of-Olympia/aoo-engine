<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Wire the TargetType condition onto the existing action catalog and ship
 * the first structure-targeted action (docs/design-buildings-entities.md
 * §4.4 + retours 2026-07-16 : les actions sur personnages et sur
 * structures doivent être distinctes en jeu).
 *
 * Rule applied to every action that does not already carry a TargetType
 * condition:
 *
 *   - base attacks (types 'melee', 'distance') → ['character','structure']
 *     — a sword or an arrow can raze a palisade (siege);
 *   - everything else (spells, buffs, techniques, heals, steal, self
 *     actions) → ['character'].
 *
 * This is the conservative content default: opening a specific technique
 * or spell to structures (corruption_du_bois on a palisade…) is a per-
 * action toggle in the action workbench, not a code change. Actions
 * created later in the workbench carry no TargetType row and therefore
 * keep today's unrestricted behavior until an admin adds the condition —
 * the workbench proposes it like any other condition type.
 *
 * New action: 'reparer' (type heal) — soigner une structure. Same healing
 * machinery as soins/barbier, gated TargetType ['structure']: mechanically
 * a heal, distinct action in game terms. Shipped in the catalog only, not
 * granted to anyone: animators attach it via race starter packs, war
 * school or players_actions. A future material cost (bois…) is content
 * tuning on its conditions.
 *
 * Idempotent: TargetType rows are only inserted where absent; 'reparer'
 * is only created if the name is free.
 */
final class Version20260716150000_ActionTargetCategories extends AbstractMigration
{
    private const STRUCTURE_ATTACK_TYPES = ['melee', 'distance'];

    public function getDescription(): string
    {
        return "Attach TargetType conditions to the action catalog and add the 'reparer' action";
    }

    public function up(Schema $schema): void
    {
        $actions = $this->connection->fetchAllAssociative(
            "SELECT a.id, a.type
             FROM actions a
             WHERE NOT EXISTS (
                 SELECT 1 FROM action_conditions c
                 WHERE c.action_id = a.id AND c.conditionType = 'TargetType'
             )"
        );

        foreach ($actions as $action) {
            $allowed = in_array($action['type'], self::STRUCTURE_ATTACK_TYPES, true)
                ? ['character', 'structure']
                : ['character'];

            $this->addSql(
                'INSERT INTO action_conditions (conditionType, parameters, action_id, execution_order, blocking)
                 VALUES (?, ?, ?, 0, 1)',
                ['TargetType', json_encode(['allowed' => $allowed]), (int) $action['id']]
            );
        }

        $this->createReparer();
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM action_conditions WHERE conditionType = 'TargetType'");
        $this->addSql(
            "DELETE oi FROM outcome_instructions oi
             JOIN action_outcomes o ON o.id = oi.outcome_id
             JOIN actions a ON a.id = o.action_id
             WHERE a.name = 'reparer'"
        );
        $this->addSql("DELETE o FROM action_outcomes o JOIN actions a ON a.id = o.action_id WHERE a.name = 'reparer'");
        $this->addSql("DELETE FROM actions WHERE name = 'reparer'");
    }

    private function createReparer(): void
    {
        $exists = $this->connection->fetchOne("SELECT id FROM actions WHERE name = 'reparer'");
        if ($exists !== false) {
            return;
        }

        $this->addSql(
            "INSERT INTO actions (name, icon, type, display_name, text, level)
             VALUES ('reparer', 'ra-hammer', 'heal', 'Réparer',
                     'Remet en état une structure endommagée — planches, pierres et huile de coude.', 1)"
        );

        // Conditions : adjacent, cible structure uniquement, coûte 1 A.
        foreach ([
            ['RequiresDistance', ['max' => 1], 0],
            ['TargetType', ['allowed' => ['structure']], 0],
            ['RequiresTraitValue', ['a' => 1], 3],
        ] as [$type, $params, $order]) {
            $this->addSql(
                "INSERT INTO action_conditions (conditionType, parameters, action_id, execution_order, blocking)
                 SELECT ?, ?, id, ?, 1 FROM actions WHERE name = 'reparer'",
                [$type, json_encode($params), $order]
            );
        }

        // Outcome : même instruction healing que soins/barbier — la force
        // fait le travail là où le barbier opère à l'agilité.
        $this->addSql(
            "INSERT INTO action_outcomes (apply_to, name, on_success, action_id)
             SELECT 'target', 'structure_repair', 1, id FROM actions WHERE name = 'reparer'"
        );
        $this->addSql(
            "INSERT INTO outcome_instructions (type, parameters, orderIndex, outcome_id)
             SELECT 'healing', ?, 0, o.id
             FROM action_outcomes o JOIN actions a ON a.id = o.action_id
             WHERE a.name = 'reparer' AND o.name = 'structure_repair'",
            [json_encode(['actorHealingTrait' => 'f'])]
        );
    }
}
