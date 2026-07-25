<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * La banque n'acceptait que des PILES : `players_items_bank` est
 * (player_id, item_id, n), sans lien vers une instance. Un objet
 * individualisé — donc tout objet usé, nommé ou de qualité — ne pouvait
 * pas y être déposé du tout, et son état n'était visible nulle part
 * (retour joueur : « dans la banque je ne vois pas la durabilité »).
 *
 * L'invariant du service d'instances est qu'un exemplaire a exactement
 * UNE localisation. Elle était implicite : présent dans
 * players_items_instances = porté par le joueur, présent dans
 * map_items_instances = au sol. La banque devient la troisième, et elle
 * s'exprime ici plutôt que par une table de plus : l'exemplaire ne
 * change pas de ligne, donc son identité, son usure et son historique
 * traversent le dépôt sans qu'aucun code n'ait à les recopier.
 *
 * Idempotent : ADD COLUMN IF NOT EXISTS.
 * Rétro-compatible : toutes les lignes existantes prennent 'inventory',
 * qui est exactement leur sens actuel — le code d'avant la migration
 * continue donc de fonctionner sur une base migrée.
 */
final class Version20260725100000_ItemInstanceBankLocation extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'players_items_instances.location — an owned instance sits in the inventory or in the bank';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "ALTER TABLE players_items_instances
             ADD COLUMN IF NOT EXISTS location VARCHAR(16) NOT NULL DEFAULT 'inventory'
             COMMENT 'inventory | bank — where the owner keeps this instance'"
        );

        /* Un exemplaire porté est par définition dans l'inventaire : le
         * défaut couvre déjà l'existant, cette ligne n'est là que pour
         * les bases où la colonne aurait été ajoutée à la main. */
        $this->addSql(
            "UPDATE players_items_instances SET location = 'inventory'
             WHERE location NOT IN ('inventory', 'bank')"
        );

        $this->addSql(
            'CREATE INDEX IF NOT EXISTS idx_pii_player_location
             ON players_items_instances (player_id, location)'
        );
    }

    public function down(Schema $schema): void
    {
        /* Les exemplaires rangés en banque redeviennent des exemplaires
         * portés : sans la colonne, c'est la seule lecture possible du
         * lien de possession. Aucune perte d'identité. */
        $this->addSql('DROP INDEX IF EXISTS idx_pii_player_location ON players_items_instances');
        $this->addSql('ALTER TABLE players_items_instances DROP COLUMN IF EXISTS location');
    }
}
