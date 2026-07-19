<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Action générique `equiper` (docs/design-generic-item-actions.md,
 * volet 2) : équiper/déséquiper entre dans le moteur d'actions — objet
 * fourni au geste (ItemPick kind equipement, instance précise
 * comprise), visée 'self', bascule et coût 1 Ae (sens-dépendant) dans
 * l'instruction equipitem. Type 'equip' : sans ligne action_type_xp,
 * aucune XP. Idempotente.
 */
final class Version20260719240000_EquipAction extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Action générique equiper (ItemPick equipement, instruction equipitem)";
    }

    public function up(Schema $schema): void
    {
        $conn = $this->connection;

        if ($conn->fetchOne("SELECT id FROM actions WHERE name = 'equiper'") !== false) {
            return;
        }

        $conn->executeStatement(
            "INSERT INTO actions (name, icon, type, display_name, text, level)
             VALUES ('equiper', 'ra-vest', 'equip', 'Équiper / déséquiper',
                     'Équipe l\'objet choisi (1 Ae) ou le déséquipe (gratuit).', 1)"
        );
        foreach ([
            ['TargetType', ['allowed' => ['self']], 0],
            ['ItemPick', ['kind' => 'equipement'], 1],
        ] as [$type, $params, $order]) {
            $conn->executeStatement(
                "INSERT INTO action_conditions (conditionType, parameters, action_id, execution_order, blocking)
                 SELECT ?, ?, id, ?, 1 FROM actions WHERE name = 'equiper'",
                [$type, json_encode($params), $order]
            );
        }
        $conn->executeStatement(
            "INSERT INTO action_outcomes (apply_to, name, on_success, action_id)
             SELECT 'self', 'equipement', 1, id FROM actions WHERE name = 'equiper'"
        );
        $conn->executeStatement(
            "INSERT INTO outcome_instructions (type, parameters, orderIndex, outcome_id)
             SELECT 'equipitem', '{}', 0, o.id
             FROM action_outcomes o JOIN actions a ON a.id = o.action_id
             WHERE a.name = 'equiper' AND o.name = 'equipement'"
        );
    }

    public function down(Schema $schema): void
    {
        $conn = $this->connection;
        $id = $conn->fetchOne("SELECT id FROM actions WHERE name = 'equiper'");
        if ($id === false) {
            return;
        }
        $conn->executeStatement(
            'DELETE oi FROM outcome_instructions oi JOIN action_outcomes o ON o.id = oi.outcome_id WHERE o.action_id = ?',
            [$id]
        );
        $conn->executeStatement('DELETE FROM action_outcomes WHERE action_id = ?', [$id]);
        $conn->executeStatement('DELETE FROM action_conditions WHERE action_id = ?', [$id]);
        $conn->executeStatement('DELETE FROM actions WHERE id = ?', [$id]);
    }
}
