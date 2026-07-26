<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Une pancarte est faite pour être lue de loin — c'est même sa raison
 * d'être : on la plante au bord d'un chemin pour qu'on la voie sans
 * avoir à quitter sa route. Elle naissait pourtant à portée courte,
 * comme tout le reste, la colonne ayant zéro pour défaut.
 *
 * Le réglage vit sur la NATURE de l'objet, donc une ligne suffit : les
 * exemplaires déjà posés comme ceux que la reprise des déclencheurs de
 * case va poser en héritent, sans qu'on ait à les toucher un par un.
 *
 * Les autres porteurs d'inscriptions recensés — tombes, sarcophages,
 * piédestaux, grimoires — gardent délibérément la portée courte : on se
 * penche sur une épitaphe, on ne la lit pas depuis trois cases. Un
 * animateur qui en juge autrement le change d'une case à cocher dans
 * la console des Races.
 */
final class Version20260726170000_SignsReadableFromAfar extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'une pancarte se lit de loin : races.readable_from_afar pour le type pancarte';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE races SET readable_from_afar = 1 WHERE name = 'pancarte'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE races SET readable_from_afar = 0 WHERE name = 'pancarte'");
    }
}
