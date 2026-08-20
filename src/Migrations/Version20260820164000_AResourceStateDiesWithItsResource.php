<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * L'état d'une ressource meurt avec elle.
 *
 * Le satellite `resources` (épuisé le…) n'avait pas de clé étrangère : la
 * suppression d'une entité laissait sa ligne d'état orpheline. Or les ids
 * d'entités se RECYCLENT (premier libre de la plage) — l'arbre d'une
 * nouvelle instance de tutoriel naissait sous l'id d'un arbre mort épuisé,
 * et sa fiche affichait « Épuisé » avant la moindre récolte.
 *
 * On purge les orphelins, puis la clé étrangère en cascade ferme la porte —
 * comme `entity_cells` le fait déjà.
 */
final class Version20260820164000_AResourceStateDiesWithItsResource extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'resources: purge des états orphelins et clé étrangère en cascade vers players';
    }

    public function up(Schema $schema): void
    {
        // Les états dont l'entité n'existe plus.
        $this->addSql("
            DELETE r FROM resources r
              LEFT JOIN players p ON p.id = r.player_id
             WHERE p.id IS NULL
        ");

        /* Les états ADOPTÉS par un id recyclé : sur un plan d'instance de
         * tutoriel, aucune ressource ne s'épuise (surcharge exhaust=0) —
         * tout état restant y est un fantôme. */
        $this->addSql("
            DELETE r FROM resources r
              JOIN players p ON p.id = r.player_id
              JOIN coords c ON c.id = p.coords_id
             WHERE c.plan LIKE 'tut\\_%'
        ");

        $fkExists = (bool) $this->connection->fetchOne("
            SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND TABLE_NAME = 'resources'
               AND CONSTRAINT_NAME = 'fk_resources_player'
        ");

        if (!$fkExists) {
            $this->addSql("
                ALTER TABLE resources
                  ADD CONSTRAINT fk_resources_player
                  FOREIGN KEY (player_id) REFERENCES players (id)
                  ON DELETE CASCADE
            ");
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE resources DROP FOREIGN KEY IF EXISTS fk_resources_player');
        // Les lignes purgées étaient des orphelins ou des fantômes : rien à restaurer.
    }
}
