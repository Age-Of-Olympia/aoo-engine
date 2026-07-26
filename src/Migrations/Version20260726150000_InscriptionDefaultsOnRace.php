<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ce qu'un objet a d'inscrit dessus, et jusqu'où ça se lit, tient
 * d'abord de sa NATURE : une pancarte s'annonce de loin et porte
 * souvent la même chose ; une plaque gravée demande qu'on s'approche.
 * L'exemplaire ne fait qu'y déroger.
 *
 * D'où deux défauts sur la race —
 *   `readable_from_afar` : ce type d'objet se lit-il sans s'approcher,
 *   `default_text`       : ce qu'un exemplaire neuf porte déjà.
 *
 * — et un drapeau NULLABLE sur le bâtiment, où NULL veut dire « comme
 * sa nature ». Sans nullabilité, impossible de distinguer « on a
 * décidé que non » de « on n'a rien décidé » : changer le défaut d'un
 * type ne rattraperait jamais les exemplaires déjà posés.
 */
final class Version20260726150000_InscriptionDefaultsOnRace extends AbstractMigration
{
    public function getDescription(): string
    {
        return "inscription et portée : défauts sur la race, exception sur le bâtiment";
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE races
             ADD COLUMN IF NOT EXISTS readable_from_afar TINYINT(1) NOT NULL DEFAULT 0'
        );
        $this->addSql(
            'ALTER TABLE races
             ADD COLUMN IF NOT EXISTS default_text TEXT DEFAULT NULL'
        );

        // NULL = suit sa race ; 0 ou 1 = l'exemplaire tranche lui-même.
        $this->addSql(
            'ALTER TABLE buildings
             ADD COLUMN IF NOT EXISTS readable_from_afar TINYINT(1) DEFAULT NULL'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE buildings DROP COLUMN IF EXISTS readable_from_afar');
        $this->addSql('ALTER TABLE races DROP COLUMN IF EXISTS readable_from_afar');
        $this->addSql('ALTER TABLE races DROP COLUMN IF EXISTS default_text');
    }
}
