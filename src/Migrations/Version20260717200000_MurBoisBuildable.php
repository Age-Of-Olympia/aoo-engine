<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Même modèle que la palissade (revue 2026-07-17) : le mur de bois est
 * un OBJET CONSTRUCTIBLE — recette d'artisanat, objet porté, l'action
 * construire le consomme et produit l'ENTITÉ bâtie (Attaquer/Réparer,
 * carte, voile de dégâts — sprite du mur via le résolveur d'avatar).
 *
 * L'item mur_bois lui-même n'est PAS touché : ses stats prod (type
 * structure/subtype walls du chemin build.php hérité) arriveront par
 * le seed — les DEUX chemins cohabitent pendant la transition, le
 * chemin hérité prendra sa retraite avec la migration murs→structures.
 *
 * - race structure 'mur_bois' (pv aligné sur WALLS_PV : 100) ;
 * - recette : 15 bois → 1 mur_bois (toutes races) ;
 * - action construire_mur_bois (consomme 1 objet mur_bois, 1 A).
 */
final class Version20260717200000_MurBoisBuildable extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Le mur de bois devient un objet constructible (recette + action + race structure)';
    }

    public function up(Schema $schema): void
    {
        $murId = $this->connection->fetchOne("SELECT id FROM items WHERE name = 'mur_bois'");
        $boisId = $this->connection->fetchOne("SELECT id FROM items WHERE name = 'bois'");
        if ($murId === false || $boisId === false) {
            $this->warnIf(true, 'items mur_bois/bois absents — migration sautée');
            return;
        }

        $this->addSql(
            "INSERT IGNORE INTO races
                (code, name, label, description, playable, hidden, kind, bgColor, color, faction, plan, pv)
             VALUES
                ('MUR_BOIS', 'mur_bois', 'Mur de bois',
                 'Rondins assemblés — plus fruste qu''une palissade, tout aussi obstinément en travers du chemin.',
                 0, 1, 'structure', '#8b6d43', 'black', '', '', 100)"
        );

        $recipeExists = $this->connection->fetchOne("SELECT id FROM craft_recipes WHERE name = 'mur_bois'");
        if ($recipeExists === false) {
            $this->addSql("INSERT INTO craft_recipes (name) VALUES ('mur_bois')");
            $this->addSql(
                "INSERT INTO craft_recipes_ingredients (count, recipe_id, item_id)
                 SELECT 15, r.id, ? FROM craft_recipes r WHERE r.name = 'mur_bois'",
                [(int) $boisId]
            );
            $this->addSql(
                "INSERT INTO craft_recipes_results (count, recipe_id, item_id)
                 SELECT 1, r.id, ? FROM craft_recipes r WHERE r.name = 'mur_bois'",
                [(int) $murId]
            );
        }

        $actionExists = $this->connection->fetchOne("SELECT id FROM actions WHERE name = 'construire_mur_bois'");
        if ($actionExists !== false) {
            return;
        }

        $this->addSql(
            "INSERT INTO actions (name, icon, type, display_name, text, level)
             VALUES ('construire_mur_bois', 'ra-wooden-sign', 'buff', 'Construire un mur de bois',
                     'Monte un mur de bois prêt à assembler sur une case libre adjacente.', 1)"
        );
        foreach ([
            ['TargetType', ['allowed' => ['character']], 0],
            ['RequiresItem', ['item' => (int) $murId, 'n' => 1, 'consume' => true], 1],
            ['RequiresTraitValue', ['a' => 1], 3],
        ] as [$type, $params, $order]) {
            $this->addSql(
                "INSERT INTO action_conditions (conditionType, parameters, action_id, execution_order, blocking)
                 SELECT ?, ?, id, ?, 1 FROM actions WHERE name = 'construire_mur_bois'",
                [$type, json_encode($params), $order]
            );
        }
        $this->addSql(
            "INSERT INTO action_outcomes (apply_to, name, on_success, action_id)
             SELECT 'self', 'construction', 1, id FROM actions WHERE name = 'construire_mur_bois'"
        );
        $this->addSql(
            "INSERT INTO outcome_instructions (type, parameters, orderIndex, outcome_id)
             SELECT 'placestructure', ?, 0, o.id
             FROM action_outcomes o JOIN actions a ON a.id = o.action_id
             WHERE a.name = 'construire_mur_bois' AND o.name = 'construction'",
            [json_encode(['type' => 'mur_bois'])]
        );
    }

    public function down(Schema $schema): void
    {
        foreach (['construire_mur_bois'] as $name) {
            $this->addSql(
                "DELETE oi FROM outcome_instructions oi
                 JOIN action_outcomes o ON o.id = oi.outcome_id
                 JOIN actions a ON a.id = o.action_id WHERE a.name = '{$name}'"
            );
            $this->addSql("DELETE o FROM action_outcomes o JOIN actions a ON a.id = o.action_id WHERE a.name = '{$name}'");
            $this->addSql("DELETE FROM action_conditions WHERE action_id IN (SELECT id FROM actions WHERE name = '{$name}')");
            $this->addSql("DELETE FROM players_actions WHERE name = '{$name}'");
            $this->addSql("DELETE FROM actions WHERE name = '{$name}'");
        }
        $this->addSql("DELETE ri FROM craft_recipes_ingredients ri JOIN craft_recipes r ON r.id = ri.recipe_id WHERE r.name = 'mur_bois'");
        $this->addSql("DELETE rr FROM craft_recipes_results rr JOIN craft_recipes r ON r.id = rr.recipe_id WHERE r.name = 'mur_bois'");
        $this->addSql("DELETE FROM craft_recipes WHERE name = 'mur_bois'");
        $this->addSql("DELETE FROM races WHERE name = 'mur_bois' AND playable = 0");
    }
}
