<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Action `fabriquer` (cadrage du 2026-07-19, volet artisanat) : le
 * craft entre dans le moteur d'actions — recette au geste (POST
 * recipeId), règles et consommation par RecipeService (source unique),
 * visée 'none'. Sans coût ni règle d'XP propre (parité avec le craft
 * historique, gratuit). L'artisanat restant en sommeil, l'action câble
 * le moteur sans UI ; le bâtiment d'artisanat s'y greffera en
 * conditions. Idempotente.
 */
final class Version20260719260000_FabriquerAction extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Action fabriquer (instruction craftrecipe) — le craft entre dans le moteur";
    }

    public function up(Schema $schema): void
    {
        $conn = $this->connection;

        if ($conn->fetchOne("SELECT id FROM actions WHERE name = 'fabriquer'") !== false) {
            return;
        }

        $conn->executeStatement(
            "INSERT INTO actions (name, icon, type, display_name, text, level)
             VALUES ('fabriquer', 'ra-anvil', 'craft', 'Fabriquer',
                     'Fabrique la recette choisie (ingrédients consommés par la recette).', 1)"
        );
        $conn->executeStatement(
            "INSERT INTO action_conditions (conditionType, parameters, action_id, execution_order, blocking)
             SELECT 'TargetType', ?, id, 0, 1 FROM actions WHERE name = 'fabriquer'",
            [json_encode(['allowed' => ['none']])]
        );
        $conn->executeStatement(
            "INSERT INTO action_outcomes (apply_to, name, on_success, action_id)
             SELECT 'self', 'fabrication', 1, id FROM actions WHERE name = 'fabriquer'"
        );
        $conn->executeStatement(
            "INSERT INTO outcome_instructions (type, parameters, orderIndex, outcome_id)
             SELECT 'craftrecipe', '{}', 0, o.id
             FROM action_outcomes o JOIN actions a ON a.id = o.action_id
             WHERE a.name = 'fabriquer' AND o.name = 'fabrication'"
        );
    }

    public function down(Schema $schema): void
    {
        $conn = $this->connection;
        $id = $conn->fetchOne("SELECT id FROM actions WHERE name = 'fabriquer'");
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
