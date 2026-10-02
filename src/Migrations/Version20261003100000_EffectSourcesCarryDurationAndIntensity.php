<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Elements and weapon strikes set how long and how hard their effect
 * lands, as skills do. The defaults are what was hard-coded: one turn,
 * intensity 1.
 */
final class Version20261003100000_EffectSourcesCarryDurationAndIntensity extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Durée et intensité de l'effet sur les types d'éléments ; intensité sur les effets d'arme";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE element_types ADD COLUMN IF NOT EXISTS effect_duration INT NOT NULL DEFAULT 1');
        $this->addSql('ALTER TABLE element_types ADD COLUMN IF NOT EXISTS effect_value INT NOT NULL DEFAULT 1');
        $this->addSql('ALTER TABLE item_effects ADD COLUMN IF NOT EXISTS value INT NOT NULL DEFAULT 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE element_types DROP COLUMN IF EXISTS effect_duration');
        $this->addSql('ALTER TABLE element_types DROP COLUMN IF EXISTS effect_value');
        $this->addSql('ALTER TABLE item_effects DROP COLUMN IF EXISTS value');
    }
}
