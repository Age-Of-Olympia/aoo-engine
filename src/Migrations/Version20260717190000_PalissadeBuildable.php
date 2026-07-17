<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Décision de revue (2026-07-17) : « la palissade ne doit pas exister
 * comme un bâtiment mais comme un OBJET CONSTRUCTIBLE ».
 *
 * Le concept auteur est l'OBJET du catalogue items : on le fabrique
 * (recette 10 bois), on le porte (empilable, échangeable, bancable),
 * et l'action construire le CONSOMME pour produire l'entité bâtie
 * (états du monde : posé = bourse, construit = entité). La race
 * structure 'palissade' reste de la plomberie interne (porteuse des
 * PV de la forme bâtie, cachée) — à terme la migration murs→structures
 * pourra dériver ces stats des colonnes de l'objet.
 *
 * - item 'palissade' (stats_in_db, type constructible) ;
 * - recette : 10 bois → 1 palissade (sans restriction de race) ;
 * - l'action construire_palissade consomme désormais 1 palissade
 *   (au lieu de 10 bois bruts).
 */
final class Version20260717190000_PalissadeBuildable extends AbstractMigration
{
    public function getDescription(): string
    {
        return "La palissade devient un objet constructible (item + recette), construire la consomme";
    }

    public function up(Schema $schema): void
    {
        $boisId = $this->connection->fetchOne("SELECT id FROM items WHERE name = 'bois'");
        if ($boisId === false) {
            $this->warnIf(true, "item 'bois' absent — migration palissade constructible sautée");
            return;
        }

        $palissadeId = $this->connection->fetchOne("SELECT id FROM items WHERE name = 'palissade'");
        if ($palissadeId === false) {
            $this->addSql(
                "INSERT INTO items (name, private, is_bankable, stats_in_db, type, price, text)
                 VALUES ('palissade', 0, 1, 1, 'constructible', 60,
                         'Panneaux de pieux prêts à monter — se dresse sur une case libre adjacente (action Construire).')"
            );
        }

        $recipeExists = $this->connection->fetchOne("SELECT id FROM craft_recipes WHERE name = 'palissade'");
        if ($recipeExists === false) {
            $this->addSql("INSERT INTO craft_recipes (name) VALUES ('palissade')");
            $this->addSql(
                "INSERT INTO craft_recipes_ingredients (count, recipe_id, item_id)
                 SELECT 10, r.id, ? FROM craft_recipes r WHERE r.name = 'palissade'",
                [(int) $boisId]
            );
            $this->addSql(
                "INSERT INTO craft_recipes_results (count, recipe_id, item_id)
                 SELECT 1, r.id, i.id FROM craft_recipes r JOIN items i ON i.name = 'palissade'
                 WHERE r.name = 'palissade'"
            );
        }

        // L'action construire consomme l'OBJET, plus les matières premières.
        $this->addSql(
            "UPDATE action_conditions ac
             JOIN actions a ON a.id = ac.action_id
             SET ac.parameters = (
                 SELECT JSON_OBJECT('item', i.id, 'n', 1, 'consume', true)
                 FROM items i WHERE i.name = 'palissade'
             )
             WHERE a.name = 'construire_palissade' AND ac.conditionType = 'RequiresItem'"
        );
        $this->addSql(
            "UPDATE actions SET text = 'Dresse une palissade prête à monter sur une case libre adjacente.'
             WHERE name = 'construire_palissade'"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "UPDATE action_conditions ac
             JOIN actions a ON a.id = ac.action_id
             SET ac.parameters = (
                 SELECT JSON_OBJECT('item', i.id, 'n', 10, 'consume', true)
                 FROM items i WHERE i.name = 'bois'
             )
             WHERE a.name = 'construire_palissade' AND ac.conditionType = 'RequiresItem'"
        );
        $this->addSql("DELETE ri FROM craft_recipes_ingredients ri JOIN craft_recipes r ON r.id = ri.recipe_id WHERE r.name = 'palissade'");
        $this->addSql("DELETE rr FROM craft_recipes_results rr JOIN craft_recipes r ON r.id = rr.recipe_id WHERE r.name = 'palissade'");
        $this->addSql("DELETE FROM craft_recipes WHERE name = 'palissade'");
        $this->addSql("DELETE FROM items WHERE name = 'palissade' AND type = 'constructible'");
    }
}
