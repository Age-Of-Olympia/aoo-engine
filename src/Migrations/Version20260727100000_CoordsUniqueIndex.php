<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Une case, une ligne : index unique sur (plan, z, x, y).
 *
 * `coords` n'avait QUE sa clé primaire alors que toutes les requêtes de
 * carte filtrent sur ces quatre colonnes — observe.php en enchaîne une
 * dizaine par clic de case. L'index sert deux fins :
 *
 *  1. la lecture (le gain de loin le plus important du chantier carte) ;
 *  2. l'unicité, sans laquelle l'upsert de View::get_coords_id ne peut pas
 *     être idempotent : ON DUPLICATE KEY UPDATE n'a pas de clé à violer.
 *
 * L'ordre des colonnes suit l'usage : on filtre (plan, z) exactement, puis
 * on balaie x et y par intervalles.
 *
 * Prérequis de code DÉJÀ déployé : View::get_coords_id ne doit plus faire
 * insert + get_last_id (« ORDER BY id DESC LIMIT 1 » sur toute la table),
 * sinon une collision devient une exception avalée en `return null`.
 */
final class Version20260727100000_CoordsUniqueIndex extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'une case, une ligne : index unique coords(plan, z, x, y)';
    }

    public function up(Schema $schema): void
    {
        // Un doublon préexistant ferait échouer la création de l'index au
        // milieu du déploiement. On préfère s'arrêter avec un message qui
        // dit quoi faire plutôt que de laisser MariaDB lever un 1062 nu.
        $duplicates = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM (
                SELECT 1 FROM coords GROUP BY plan, z, x, y HAVING COUNT(*) > 1
             ) AS d'
        );

        $this->abortIf(
            $duplicates > 0,
            "coords porte {$duplicates} groupe(s) (plan, z, x, y) en double : l'index unique "
            . 'ne peut pas être posé. Fusionner les doublons (repointer les couches map_* et '
            . 'players.coords_id vers la ligne conservée) AVANT de rejouer cette migration.'
        );

        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uk_pzxy ON coords (plan, z, x, y)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS uk_pzxy ON coords');
    }
}
