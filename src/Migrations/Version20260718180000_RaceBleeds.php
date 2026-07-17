<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ce qu'une entité VERSE au sol quand elle est blessée devient une
 * carac de RACE (décision 2026-07-18) : races.bleeds — 'sang' pour les
 * personnages (comportement historique), '' = rien pour les
 * structures (un mur ne saigne pas), et n'importe quel élément de
 * carte pour les créatures exotiques. Le déclencheur reste dans le
 * chemin de dégâts (putBonus), seule la SUBSTANCE est configurable.
 */
final class Version20260718180000_RaceBleeds extends AbstractMigration
{
    public function getDescription(): string
    {
        return "races.bleeds : l'élément versé au sol par blessure ('' = rien)";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE races ADD bleeds VARCHAR(50) NOT NULL DEFAULT 'sang'");
        $this->addSql("UPDATE races SET bleeds = '' WHERE kind = 'structure'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE races DROP COLUMN bleeds');
    }
}
