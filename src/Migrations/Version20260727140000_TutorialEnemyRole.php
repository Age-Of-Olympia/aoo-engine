<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Le rôle du PNJ, reporté sur sa ligne d'apparition.
 *
 * `tutorial_npcs` distingue déjà ce qu'un PNJ vient faire : `guide` pour
 * Gaïa, `enemy` pour l'adversaire d'entraînement. Mais l'apparition
 * (TutorialResourceManager) n'inscrivait que l'identifiant et la case —
 * le rôle restait dans la configuration, hors de portée de qui lit la
 * session.
 *
 * Le damier, lui, a besoin de savoir lequel des PNJ présents est
 * l'adversaire : c'est sur lui qu'il pose `.tutorial-enemy`, la prise à
 * laquelle les étapes accrochent leur surlignage. Faute de mieux, il le
 * reconnaissait à son NOM — un libellé d'affichage, que l'administration
 * du tutoriel peut changer, et dont le changement éteignait le surlignage
 * sans rien signaler.
 *
 * La colonne referme le dernier écart : tant qu'il n'y avait qu'un seul
 * PNJ dynamique, « inscrit pour cette session » et « adversaire »
 * désignaient la même ligne ; ce ne sera plus vrai au premier marchand
 * dynamique.
 *
 * Idempotent, et rétro-compatible : les lignes existantes restent à NULL,
 * que le lecteur traite comme `enemy` — c'est ce qu'elles sont toutes, le
 * seul PNJ dynamique configuré à ce jour étant l'adversaire.
 */
final class Version20260727140000_TutorialEnemyRole extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'tutorial_enemies.role — carry the tutorial_npcs role onto the spawn row';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "ALTER TABLE tutorial_enemies
             ADD COLUMN IF NOT EXISTS role VARCHAR(50) DEFAULT NULL
             COMMENT 'tutorial_npcs.role du PNJ apparu ; NULL (lignes antérieures) = enemy'"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tutorial_enemies DROP COLUMN IF EXISTS role');
    }
}
