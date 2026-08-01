<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The wooden chest gets a recipe, so that building one can be exercised.
 *
 * No recipe produced a chest and no bag held one, which is what made retiring
 * the `coffre_*` races harmless — the build path had never been walked for a
 * container. That also meant the new path could not be walked ON PURPOSE, and
 * an untested path is the one that breaks.
 *
 * Twenty planks, following the wall recipes that are the only precedent: a
 * palisade costs ten, a wooden wall fifteen. A chest is the dearer of the
 * three, which is the whole of the reasoning — this is a number to play with,
 * not a balance decision, and it belongs to whoever tunes the economy.
 */
final class Version20260803250000_AWoodenChestCanBeCrafted extends AbstractMigration
{
    private const PLANKS = 20;

    public function getDescription(): string
    {
        return 'craft: the wooden chest becomes craftable, so building one can be exercised';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "INSERT INTO craft_recipes (name)
             SELECT 'coffre_bois'
              WHERE NOT EXISTS (SELECT 1 FROM craft_recipes WHERE name = 'coffre_bois')"
        );

        $this->addSql(
            "INSERT INTO craft_recipes_ingredients (recipe_id, item_id, count)
             SELECT cr.id, it.id, " . self::PLANKS . "
               FROM craft_recipes cr
               JOIN items it ON it.name = 'bois'
              WHERE cr.name = 'coffre_bois'
                AND NOT EXISTS (
                        SELECT 1 FROM craft_recipes_ingredients x WHERE x.recipe_id = cr.id
                    )"
        );

        $this->addSql(
            "INSERT INTO craft_recipes_results (recipe_id, item_id, count)
             SELECT cr.id, it.id, 1
               FROM craft_recipes cr
               JOIN items it ON it.name = 'coffre_bois'
              WHERE cr.name = 'coffre_bois'
                AND NOT EXISTS (
                        SELECT 1 FROM craft_recipes_results x WHERE x.recipe_id = cr.id
                    )"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "DELETE cri FROM craft_recipes_ingredients cri
               JOIN craft_recipes cr ON cr.id = cri.recipe_id
              WHERE cr.name = 'coffre_bois'"
        );
        $this->addSql(
            "DELETE crr FROM craft_recipes_results crr
               JOIN craft_recipes cr ON cr.id = crr.recipe_id
              WHERE cr.name = 'coffre_bois'"
        );
        $this->addSql("DELETE FROM craft_recipes WHERE name = 'coffre_bois'");
    }
}
