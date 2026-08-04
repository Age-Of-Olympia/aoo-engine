<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Two levels of crafting: what any hands can do, and what needs a roof.
 *
 * The recipe carries the difference — `workshop` names the building type
 * (races, kind building) an OPEN specimen of which must stand within reach
 * of the crafter. NULL keeps the recipe basic: craftable anywhere, which
 * is every recipe existing today, so the column changes nothing until the
 * admin sets it. A name rather than a flag, because "one atelier for now"
 * is a catalogue row, not a ceiling.
 */
final class Version20260804100000_AdvancedRecipesNeedAWorkshop extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'craft_recipes.workshop: the building type an advanced recipe is crafted at (NULL = anywhere)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE craft_recipes ADD COLUMN IF NOT EXISTS workshop VARCHAR(255) DEFAULT NULL'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE craft_recipes DROP COLUMN IF EXISTS workshop');
    }
}
