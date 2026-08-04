<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The atelier — the one building advanced recipes are crafted at.
 *
 * Three rows, no code: the building TYPE (races, edifice, lockable — a
 * closed atelier serves nobody per the closure rule), the constructible
 * OBJECT (items, the palissade pattern: craft it, carry it, construire
 * consumes it), and its recipe. That recipe stays BASIC (workshop NULL)
 * on purpose: the first atelier of a region must be craftable without
 * one, or no atelier could ever exist. AdvancedRecipesBootstrapTest
 * pins it.
 *
 * No sprite yet: the board falls back to the initials frame
 * (BuildingService::NO_IMAGE), and the admin can attach art later.
 */
final class Version20260804110000_TheAtelierEntersTheWorld extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Le type de bâtiment 'atelier', son objet constructible et sa recette de base";
    }

    public function up(Schema $schema): void
    {
        $typeExists = $this->connection->fetchOne("SELECT id FROM races WHERE name = 'atelier'");
        if ($typeExists === false) {
            $this->addSql(
                "INSERT INTO races
                    (code, name, label, description, playable, hidden, kind, structure_nature,
                     bleeds, wound_color, blocks_passage, blocks_projectiles,
                     lockable, opens_the_way, readable_from_afar,
                     bgColor, color, faction, plan, pv)
                 VALUES
                    ('ATELIER', 'atelier', 'Atelier',
                     'Un toit, un établi et de bons outils : les recettes avancées se façonnent ici.',
                     0, 1, 'structure', 'edifice',
                     '', '#cd7f32', 1, 1,
                     1, 0, 1,
                     '#8b6d43', 'black', '', '', 150)"
            );
        }

        $itemExists = $this->connection->fetchOne("SELECT id FROM items WHERE name = 'atelier'");
        if ($itemExists === false) {
            $this->addSql(
                "INSERT INTO items (name, private, is_bankable, stats_in_db, type, price, text)
                 VALUES ('atelier', 0, 1, 1, 'constructible', 200,
                         'Établi, outils et charpente prêts à monter — se dresse sur une case libre adjacente (action Construire).')"
            );
        }

        $recipeExists = $this->connection->fetchOne("SELECT id FROM craft_recipes WHERE name = 'atelier'");
        if ($recipeExists === false) {
            $this->addSql("INSERT INTO craft_recipes (name, workshop) VALUES ('atelier', NULL)");
            $this->addSql(
                "INSERT INTO craft_recipes_ingredients (count, recipe_id, item_id)
                 SELECT 30, r.id, i.id FROM craft_recipes r JOIN items i ON i.name = 'bois'
                 WHERE r.name = 'atelier'"
            );
            $this->addSql(
                "INSERT INTO craft_recipes_ingredients (count, recipe_id, item_id)
                 SELECT 10, r.id, i.id FROM craft_recipes r JOIN items i ON i.name = 'pierre'
                 WHERE r.name = 'atelier'"
            );
            $this->addSql(
                "INSERT INTO craft_recipes_results (count, recipe_id, item_id)
                 SELECT 1, r.id, i.id FROM craft_recipes r JOIN items i ON i.name = 'atelier'
                 WHERE r.name = 'atelier'"
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE ri FROM craft_recipes_ingredients ri JOIN craft_recipes r ON r.id = ri.recipe_id WHERE r.name = 'atelier'");
        $this->addSql("DELETE rr FROM craft_recipes_results rr JOIN craft_recipes r ON r.id = rr.recipe_id WHERE r.name = 'atelier'");
        $this->addSql("DELETE FROM craft_recipes WHERE name = 'atelier'");
        $this->addSql("DELETE FROM items WHERE name = 'atelier' AND type = 'constructible'");
        // The type stays if anything in the world already IS one.
        $this->addSql(
            "DELETE FROM races WHERE name = 'atelier'
             AND NOT EXISTS (SELECT 1 FROM players p WHERE p.race = 'atelier')"
        );
    }
}
