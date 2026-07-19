<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Actions génériques paramétrées par l'objet
 * (docs/design-generic-item-actions.md) :
 *
 * - crée UNE action `construire` et UNE action `consommer` — l'objet
 *   arrive à l'exécution (condition ItemPick, POST itemId), plus une
 *   ligne par type ;
 * - supprime les `construire_<type>` (squelettes jumeaux, jamais
 *   accordées aux joueurs ni référencées hors de l'inventaire) et leurs
 *   satellites conditions/outcomes/instructions.
 *
 * Idempotente : rejouable sans effet si les actions génériques existent
 * déjà et que les construire_* ont disparu.
 */
final class Version20260719220000_GenericItemActions extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Actions génériques construire/consommer (ItemPick), suppression des construire_<type>';
    }

    public function up(Schema $schema): void
    {
        $conn = $this->connection;

        // ── 1. construire ────────────────────────────────────────────
        if ($conn->fetchOne("SELECT id FROM actions WHERE name = 'construire'") === false) {
            $conn->executeStatement(
                "INSERT INTO actions (name, icon, type, display_name, text, level)
                 VALUES ('construire', 'ra-tower', 'buff', 'Construire',
                         'Bâtit l\'objet constructible choisi sur une case libre adjacente.', 1)"
            );
            $conditions = [
                ['TargetType', ['allowed' => ['character']], 0],
                // L'objet du geste : possession + admissibilité validées,
                // déposé pour RequiresItem et PlaceStructure.
                ['ItemPick', ['kind' => 'constructible'], 1],
                ['RequiresItem', ['n' => 1, 'consume' => true], 2],
                // BuildSite valide la case choisie AVANT tout paiement — sans
                // elle, une case volée consommerait l'objet pour rien.
                ['BuildSite', [], 3],
                ['RequiresTraitValue', ['a' => 1], 4],
            ];
            foreach ($conditions as [$type, $params, $order]) {
                $conn->executeStatement(
                    "INSERT INTO action_conditions (conditionType, parameters, action_id, execution_order, blocking)
                     SELECT ?, ?, id, ?, 1 FROM actions WHERE name = 'construire'",
                    [$type, json_encode($params), $order]
                );
            }
            $conn->executeStatement(
                "INSERT INTO action_outcomes (apply_to, name, on_success, action_id)
                 SELECT 'self', 'construction', 1, id FROM actions WHERE name = 'construire'"
            );
            $conn->executeStatement(
                "INSERT INTO outcome_instructions (type, parameters, orderIndex, outcome_id)
                 SELECT 'placestructure', '{}', 0, o.id
                 FROM action_outcomes o JOIN actions a ON a.id = o.action_id
                 WHERE a.name = 'construire' AND o.name = 'construction'"
            );
        }

        // ── 2. consommer ─────────────────────────────────────────────
        if ($conn->fetchOne("SELECT id FROM actions WHERE name = 'consommer'") === false) {
            $conn->executeStatement(
                "INSERT INTO actions (name, icon, type, display_name, text, level)
                 VALUES ('consommer', 'ra-potion', 'buff', 'Consommer',
                         'Consomme l\'objet choisi et applique ses effets.', 1)"
            );
            $conditions = [
                ['TargetType', ['allowed' => ['character']], 0],
                ['ItemPick', ['kind' => 'consommable'], 1],
                ['RequiresItem', ['n' => 1, 'consume' => true], 2],
                ['RequiresTraitValue', ['a' => 1], 3],
            ];
            foreach ($conditions as [$type, $params, $order]) {
                $conn->executeStatement(
                    "INSERT INTO action_conditions (conditionType, parameters, action_id, execution_order, blocking)
                     SELECT ?, ?, id, ?, 1 FROM actions WHERE name = 'consommer'",
                    [$type, json_encode($params), $order]
                );
            }
            $conn->executeStatement(
                "INSERT INTO action_outcomes (apply_to, name, on_success, action_id)
                 SELECT 'self', 'consommation', 1, id FROM actions WHERE name = 'consommer'"
            );
            $conn->executeStatement(
                "INSERT INTO outcome_instructions (type, parameters, orderIndex, outcome_id)
                 SELECT 'applyconsumable', '{}', 0, o.id
                 FROM action_outcomes o JOIN actions a ON a.id = o.action_id
                 WHERE a.name = 'consommer' AND o.name = 'consommation'"
            );
        }

        // ── 3. Adieu les construire_<type> ───────────────────────────
        // Jamais dans players_actions ni dans le contenu : seuls les
        // satellites du catalogue sont à démonter, enfants d'abord.
        $ids = $conn->fetchFirstColumn("SELECT id FROM actions WHERE name LIKE 'construire\\_%'");
        if ($ids !== []) {
            $in = implode(',', array_map('intval', $ids));
            $conn->executeStatement(
                "DELETE oi FROM outcome_instructions oi
                 JOIN action_outcomes o ON o.id = oi.outcome_id
                 WHERE o.action_id IN ({$in})"
            );
            $conn->executeStatement("DELETE FROM action_outcomes WHERE action_id IN ({$in})");
            $conn->executeStatement("DELETE FROM action_conditions WHERE action_id IN ({$in})");
            $conn->executeStatement("DELETE FROM actions WHERE id IN ({$in})");
        }
    }

    public function down(Schema $schema): void
    {
        // Retour arrière = rejouer le seed des constructibles
        // (StructureConversionService / WallsToStructures) ; les actions
        // génériques restent, elles sont inoffensives sans UI.
        $this->warnIf(true, 'Pas de down automatique — rejouer le seed des construire_<type> au besoin.');
    }
}
