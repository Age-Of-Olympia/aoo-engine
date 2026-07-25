<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Le marché et les échanges ne savaient manipuler que du FONGIBLE : une
 * offre porte un objet de catalogue et une quantité, et la mise en vente
 * débite une pile de banque. Depuis que la banque accepte les objets
 * individualisés, le formulaire de vente les affiche — et les vendre
 * débitait la PILE du même objet quand le joueur en avait une : son
 * exemplaire usé restait au coffre pendant qu'une unité vierge partait
 * au marché, sans message ni trace. Perte d'identité silencieuse.
 *
 * On donne donc aux lignes d'ordre de quoi désigner UN exemplaire.
 *
 * L'identité ne se recopie jamais : la ligne d'offre ou d'échange porte
 * une RÉFÉRENCE, l'usure et le nom restent sur item_instances. La
 * localisation de l'exemplaire (players_items_instances.location, posée
 * par la migration de la banque) s'étend à 'market' et 'exchange' — sans
 * DDL, la colonne étant un VARCHAR libre. C'est ce qui met un exemplaire
 * séquestré hors de portée de tous les gestes de jeu sans toucher une
 * seule des lectures de possession : elles filtrent en liste blanche sur
 * 'inventory'.
 *
 * Les index UNIQUE sont l'essentiel de cette migration. MySQL tolère
 * plusieurs NULL dans un index unique : les offres de pile ne sont donc
 * pas gênées, et il devient STRUCTURELLEMENT impossible qu'un même
 * exemplaire soit engagé dans deux offres ou deux échanges. Une garde
 * applicative se contourne, un index non.
 *
 * Idempotent : IF NOT EXISTS partout.
 * Rétro-compatible : toutes les lignes existantes prennent instance_id
 * NULL, c'est-à-dire « ligne de pile » — leur sens actuel. Le code
 * d'avant la migration continue donc de fonctionner sur une base migrée.
 */
final class Version20260725120000_MarketAndExchangeInstances extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Market offers and player exchanges can carry ONE individualised instance, escrowed by location';
    }

    public function up(Schema $schema): void
    {
        /* --- Offres de vente --------------------------------------- */
        $this->addSql('ALTER TABLE items_bids ADD COLUMN IF NOT EXISTS instance_id INT DEFAULT NULL');
        $this->addSql(
            'CREATE UNIQUE INDEX IF NOT EXISTS uniq_bids_instance ON items_bids (instance_id)'
        );
        $this->addSql(
            'ALTER TABLE items_bids ADD CONSTRAINT fk_bids_instance
             FOREIGN KEY IF NOT EXISTS (instance_id) REFERENCES item_instances (id)'
        );

        /* --- Lignes d'échange --------------------------------------
         * La clé primaire d'abord : la table n'en avait AUCUNE, si bien
         * que remove_item_from_exchange visait ses lignes par
         * (exchange_id, item_id, n, player_id) et supprimait donc toutes
         * les lignes identiques d'un coup. Invisible tant que tout est
         * fongible ; avec deux exemplaires du même objet dans un
         * échange, plus rien ne permet d'en retirer un seul. */
        $this->addSql(
            'ALTER TABLE players_items_exchanges
             ADD COLUMN IF NOT EXISTS id INT AUTO_INCREMENT PRIMARY KEY FIRST'
        );
        $this->addSql('ALTER TABLE players_items_exchanges ADD COLUMN IF NOT EXISTS instance_id INT DEFAULT NULL');
        $this->addSql(
            'CREATE UNIQUE INDEX IF NOT EXISTS uniq_pie_instance ON players_items_exchanges (instance_id)'
        );
        $this->addSql(
            'ALTER TABLE players_items_exchanges ADD CONSTRAINT fk_pie_instance
             FOREIGN KEY IF NOT EXISTS (instance_id) REFERENCES item_instances (id)'
        );

        /* --- Documentation de la colonne de localisation ------------
         * Purement cosmétique : la valeur n'est pas contrainte, c'est
         * précisément pourquoi l'étendre ne coûte aucune migration. */
        $this->addSql(
            "ALTER TABLE players_items_instances
             MODIFY COLUMN location VARCHAR(16) NOT NULL DEFAULT 'inventory'
             COMMENT 'inventory | bank | market | exchange — where the owner keeps this instance'"
        );
    }

    public function down(Schema $schema): void
    {
        /* Les exemplaires séquestrés rentrent chez leur propriétaire :
         * sans la référence, plus rien ne dirait où ils sont partis. */
        $this->addSql(
            "UPDATE players_items_instances SET location = 'bank'
             WHERE location IN ('market', 'exchange')"
        );

        $this->addSql('ALTER TABLE items_bids DROP FOREIGN KEY IF EXISTS fk_bids_instance');
        $this->addSql('DROP INDEX IF EXISTS uniq_bids_instance ON items_bids');
        $this->addSql('ALTER TABLE items_bids DROP COLUMN IF EXISTS instance_id');

        $this->addSql('ALTER TABLE players_items_exchanges DROP FOREIGN KEY IF EXISTS fk_pie_instance');
        $this->addSql('DROP INDEX IF EXISTS uniq_pie_instance ON players_items_exchanges');
        $this->addSql('ALTER TABLE players_items_exchanges DROP COLUMN IF EXISTS instance_id');
        $this->addSql('ALTER TABLE players_items_exchanges DROP COLUMN IF EXISTS id');

        $this->addSql(
            "ALTER TABLE players_items_instances
             MODIFY COLUMN location VARCHAR(16) NOT NULL DEFAULT 'inventory'
             COMMENT 'inventory | bank — where the owner keeps this instance'"
        );
    }
}
