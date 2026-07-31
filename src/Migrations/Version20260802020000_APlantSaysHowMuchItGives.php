<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Une plante dit combien elle rend, au lieu de s'en remettre au code.
 *
 * La cueillette donnait 1 à 3 unités, en dur : `rand(1, 3)` au fond du service.
 * Toutes les fleurs du monde rendaient donc la même chose, et personne ne
 * pouvait décider qu'une baie est plus généreuse qu'un lichen.
 *
 * Deux colonnes sur le TYPE, comme le reste de sa configuration — ajouter une
 * plante et régler ce qu'elle donne doit suffire.
 *
 * Pourquoi PAS sur le trait partagé avec les ressources : leurs quantités ne se
 * décident pas de la même façon. `fouiller` est une action de ZONE — ce qu'une
 * ressource rend dépend du nombre de voisines, un dé sur le compte alentour.
 * Une plante se cueille seule. La capacité commune est de RENDRE quelque chose ;
 * combien, chaque famille le dit à sa manière.
 *
 * Semées à 1 et 3 : le comportement d'aujourd'hui, rendu réglable sans être
 * changé. Aucune partie ne verra la différence tant que personne n'y touche.
 */
final class Version20260802020000_APlantSaysHowMuchItGives extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'races.harvest_min/max: a plant type states how much it yields';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE races ADD COLUMN IF NOT EXISTS harvest_min SMALLINT NULL');
        $this->addSql('ALTER TABLE races ADD COLUMN IF NOT EXISTS harvest_max SMALLINT NULL');

        /* 1 à 3 : ce que le code tirait. Le réglage naît sur la valeur en
         * vigueur, pour que ce lot ne change rien à ce qui se joue. */
        $this->addSql(
            "UPDATE races SET harvest_min = 1, harvest_max = 3
              WHERE type_kind = 'plant' AND harvest_min IS NULL AND harvest_max IS NULL"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE races DROP COLUMN IF EXISTS harvest_min');
        $this->addSql('ALTER TABLE races DROP COLUMN IF EXISTS harvest_max');
    }
}
