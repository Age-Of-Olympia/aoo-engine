<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Action `creuser` (docs/design-generic-item-actions.md, volet
 * « creuser sort de go.php ») : le creusement de galeries devient une
 * action du catalogue — coût 1 A en condition, galerie/pierre/malus
 * dans l'instruction digtunnel, XP par la règle du type 'search'
 * (1 = XP_PER_MINE). Déclenchée par le déplacement (go.php), visée
 * 'none'. Idempotente.
 */
final class Version20260719250000_CreuserAction extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Action creuser (instruction digtunnel) — le creusement sort de go.php";
    }

    public function up(Schema $schema): void
    {
        $conn = $this->connection;

        if ($conn->fetchOne("SELECT id FROM actions WHERE name = 'creuser'") !== false) {
            return;
        }

        $conn->executeStatement(
            "INSERT INTO actions (name, icon, type, display_name, text, level)
             VALUES ('creuser', 'ra-mining-diamonds', 'search', 'Creuser',
                     'Creuse une galerie sous terre (1 pierre ; malus sans Pioche).', 1)"
        );
        foreach ([
            ['TargetType', ['allowed' => ['none']], 0],
            // DigSite valide la case AVANT tout paiement — un refus
            // bloquant ne coûte ni A ni XP (même règle que BuildSite).
            ['DigSite', [], 1],
            ['RequiresTraitValue', ['a' => 1], 2],
        ] as [$type, $params, $order]) {
            $conn->executeStatement(
                "INSERT INTO action_conditions (conditionType, parameters, action_id, execution_order, blocking)
                 SELECT ?, ?, id, ?, 1 FROM actions WHERE name = 'creuser'",
                [$type, json_encode($params), $order]
            );
        }
        $conn->executeStatement(
            "INSERT INTO action_outcomes (apply_to, name, on_success, action_id)
             SELECT 'self', 'creusement', 1, id FROM actions WHERE name = 'creuser'"
        );
        $conn->executeStatement(
            "INSERT INTO outcome_instructions (type, parameters, orderIndex, outcome_id)
             SELECT 'digtunnel', '{}', 0, o.id
             FROM action_outcomes o JOIN actions a ON a.id = o.action_id
             WHERE a.name = 'creuser' AND o.name = 'creusement'"
        );
    }

    public function down(Schema $schema): void
    {
        $conn = $this->connection;
        $id = $conn->fetchOne("SELECT id FROM actions WHERE name = 'creuser'");
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
