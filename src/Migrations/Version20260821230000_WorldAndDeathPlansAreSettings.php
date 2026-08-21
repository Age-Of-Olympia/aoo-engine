<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Le plan principal et le plan des morts deviennent des réglages.
 *
 * « olympia » et « enfers » étaient codés en dur partout : carte du monde,
 * respawn des factions, téléportation à la mort, condition d'action. Deux
 * réglages du tableau de bord admin les remplacent (world_plan et
 * death_plan, lus par PlanService::worldPlan()/deathPlan()) — deux listes
 * déroulantes à côté de la saison courante. Renommer ces plans, ou en
 * donner d'autres à une nouvelle saison, ne demande plus de toucher au
 * code.
 *
 * Semés à leurs valeurs historiques ; un réglage existant n'est jamais
 * écrasé.
 */
final class Version20260821230000_WorldAndDeathPlansAreSettings extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'réglages world_plan et death_plan (tableau de bord admin), en remplacement des slugs olympia / enfers codés en dur';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE IF NOT EXISTS admin_settings (
                name VARCHAR(64) NOT NULL PRIMARY KEY,
                value VARCHAR(255) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $this->addSql("INSERT IGNORE INTO admin_settings (name, value) VALUES ('world_plan', 'olympia'), ('death_plan', 'enfers')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM admin_settings WHERE name IN ('world_plan', 'death_plan')");
    }
}
