<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Throwable;

/**
 * Avant toute autre : la base parle utf8mb4.
 *
 * Une `CREATE TABLE` qui ne déclare pas son jeu de caractères prend celui de la
 * BASE. Vingt-trois migrations de l'historique créent ainsi des colonnes texte
 * sans rien déclarer — utf8mb4 en développement, latin1 sur un hébergement dont
 * la base a été créée à l'ancienne. Les tables naissent alors dans un jeu que
 * personne n'a choisi, et la première comparaison stricte échoue en production.
 *
 * Corriger vingt-trois migrations fusionnées serait la mauvaise forme du même
 * geste. La racine est le défaut de la base : on le pose une fois, ici, et
 * toutes les tables créées ensuite naissent utf8mb4 — y compris les TEMPORAIRES,
 * qui sont précisément celles qui ont tué un déploiement, et les seules qu'aucun
 * inventaire ne rattrape puisqu'elles meurent avec la migration.
 *
 * DATÉE AVANT TOUT LE RESTE, et c'est le fond du lot : rejouer la pile entière
 * sur une copie de production doit traverser ce point AVANT les migrations qui
 * créent des tables. Doctrine exécute toute version non encore jouée, même
 * datée avant des versions déjà appliquées — un environnement à jour la prendra
 * donc hors séquence, ce qui est sans conséquence : poser un défaut ne dépend
 * pas de ce qui précède.
 *
 * NE TOUCHE AUCUNE DONNÉE. `ALTER DATABASE` ne fixe que le défaut des tables à
 * VENIR ; les tables existantes gardent leur jeu, leurs octets et leur
 * collation. Mesuré : une table créée avant reste latin1, une table créée après
 * naît utf8mb4.
 *
 * Le privilège peut manquer sur un hébergement mutualisé. L'échec est alors
 * avalé À DESSEIN : le reste du code ne dépend pas de ce défaut — les
 * comparaisons convertissent, les tables récentes déclarent leur jeu — et faire
 * tomber un déploiement pour un réglage d'hygiène serait exactement le mal
 * qu'on soigne.
 */
final class Version20250101000000_TheDatabaseSpeaksUtf8mb4 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'la base parle utf8mb4 : les tables créées ensuite ne naissent plus latin1';
    }

    public function up(Schema $schema): void
    {
        $current = $this->connection->fetchOne('SELECT @@character_set_database');

        if ($current === 'utf8mb4') {
            return;
        }

        $database = $this->connection->fetchOne('SELECT DATABASE()');

        if (!is_string($database) || $database === '') {
            return;
        }

        /* Exécuté ici plutôt que par addSql : on veut pouvoir survivre au refus
         * du privilège, ce qu'une instruction remise à l'exécuteur ne permet
         * pas. */
        try {
            $this->connection->executeStatement(
                sprintf('ALTER DATABASE `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci', $database)
            );
        } catch (Throwable $e) {
            $this->write(sprintf(
                '  <comment>défaut de la base laissé en %s : %s</comment>',
                (string) $current,
                $e->getMessage()
            ));
        }
    }

    /**
     * Pas de retour : rendre à une base le défaut latin1 des années 2000
     * n'aurait aucun sens, et rien n'a été retiré qu'il faille rendre.
     */
    public function down(Schema $schema): void
    {
    }
}
