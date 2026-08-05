<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The buildable door — the palissade pattern reapplied. The TYPE
 * (races 'porte_bois', lockable) already stands in the world,
 * admin-placed; it gains its constructible OBJECT (items) and a basic
 * recipe, and declares build_work so raising one opens a chantier
 * (action travailler) instead of standing instantly.
 */
final class Version20260806140000_TheDoorIsBuilt extends AbstractMigration
{
    public function getDescription(): string
    {
        return "la porte se construit : objet 'porte_bois', recette de base, chantier (build_work)";
    }

    public function up(Schema $schema): void
    {
        $itemExists = $this->connection->fetchOne("SELECT id FROM items WHERE name = 'porte_bois'");
        if ($itemExists === false) {
            $this->addSql(
                "INSERT INTO items (name, private, is_bankable, stats_in_db, type, price, text)
                 VALUES ('porte_bois', 0, 1, 1, 'constructible', 80,
                         'Vantail, gonds et serrure prêts à poser — se dresse sur une case libre adjacente (action Construire).')"
            );
        }

        $recipeExists = $this->connection->fetchOne("SELECT id FROM craft_recipes WHERE name = 'porte_bois'");
        if ($recipeExists === false) {
            $this->addSql("INSERT INTO craft_recipes (name, workshop) VALUES ('porte_bois', NULL)");
            $this->addSql(
                "INSERT INTO craft_recipes_ingredients (count, recipe_id, item_id)
                 SELECT 15, r.id, i.id FROM craft_recipes r JOIN items i ON i.name = 'bois'
                 WHERE r.name = 'porte_bois'"
            );
            $this->addSql(
                "INSERT INTO craft_recipes_results (count, recipe_id, item_id)
                 SELECT 1, r.id, i.id FROM craft_recipes r JOIN items i ON i.name = 'porte_bois'
                 WHERE r.name = 'porte_bois'"
            );
        }

        /* Guarded on the previous default: an admin tuning survives replays. */
        $this->addSql("UPDATE races SET build_work = 4 WHERE name = 'porte_bois' AND build_work = 0");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE ri FROM craft_recipes_ingredients ri JOIN craft_recipes r ON r.id = ri.recipe_id WHERE r.name = 'porte_bois'");
        $this->addSql("DELETE rr FROM craft_recipes_results rr JOIN craft_recipes r ON r.id = rr.recipe_id WHERE r.name = 'porte_bois'");
        $this->addSql("DELETE FROM craft_recipes WHERE name = 'porte_bois'");
        $this->addSql("DELETE FROM items WHERE name = 'porte_bois' AND type = 'constructible'");
        $this->addSql("UPDATE races SET build_work = 0 WHERE name = 'porte_bois'");
    }
}
