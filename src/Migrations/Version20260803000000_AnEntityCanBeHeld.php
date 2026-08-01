<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Une entité est sur une case, DANS une autre, ou nulle part.
 *
 * Le modèle croyait qu'une ligne `players` valait une place sur le damier, et
 * il s'est arrangé avec sa croyance : un bâtiment détruit est remisé sur le
 * plan `limbes_batiments`, un mort part aux `enfers`. Chaque localisation
 * hors-carte est déjà là, déguisée en case. On arrête de déguiser.
 *
 * `holder_id` dit QUI me tient, `slot` dit COMMENT — sac, main1, banque. Ce que
 * tient une entité, c'est ce qui pointe vers elle : l'inventaire devient une
 * relation, et non plus une table par cas. Un objet en sac, un objet équipé, le
 * contenu d'un coffre : trois formulations d'une seule chose.
 *
 * `coords_id` devient nullable, et NULL est le SEUL « nulle part » possible :
 * `players_ibfk_1` contraint la colonne vers `coords`, aucune case ne porte
 * l'id 0, aucune ligne ne vaut 0. Le `coords_id > 0` que teste partout
 * `EntityCellService` se garde d'un état que le schéma interdit déjà — il
 * restera vrai, sans jamais être la façon d'écrire l'absence.
 *
 * Défaut passé à NULL : les quatre écritures de la table nomment leur case,
 * donc l'ancien défaut à 0 ne pouvait de toute façon que violer la contrainte.
 *
 * RESTRICT sur la clé étrangère, et c'est un choix : supprimer une entité qui
 * tient encore quelque chose doit ÉCHOUER. Un contenant se vide avant de mourir,
 * il n'abandonne pas son contenu à l'orphelinat.
 *
 * Personne ne s'en sert encore. Toutes les lignes existantes gardent leur case
 * et n'ont pas de porteur : rien ne change dans le jeu.
 */
final class Version20260803000000_AnEntityCanBeHeld extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'players.holder_id/slot: an entity is on a cell, inside another, or nowhere';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE players ADD COLUMN IF NOT EXISTS holder_id INT(11) NULL DEFAULT NULL');
        $this->addSql("ALTER TABLE players ADD COLUMN IF NOT EXISTS slot VARCHAR(32) NOT NULL DEFAULT ''");

        $this->addSql('ALTER TABLE players MODIFY COLUMN coords_id INT(11) NULL DEFAULT NULL');

        /* (holder_id, slot) et pas holder_id seul : « ce que je tiens » se
         * demande presque toujours par emplacement — l'équipé, le sac. La clé
         * étrangère se contente de ce préfixe. */
        $this->addSql('ALTER TABLE players ADD INDEX IF NOT EXISTS idx_players_holder (holder_id, slot)');

        $this->addConstraintIfMissing(
            'players',
            'fk_players_holder',
            'ALTER TABLE players
             ADD CONSTRAINT fk_players_holder FOREIGN KEY (holder_id)
             REFERENCES players (id) ON DELETE RESTRICT'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE players DROP FOREIGN KEY IF EXISTS fk_players_holder');
        $this->addSql('ALTER TABLE players DROP INDEX IF EXISTS idx_players_holder');
        $this->addSql('ALTER TABLE players DROP COLUMN IF EXISTS slot');
        $this->addSql('ALTER TABLE players DROP COLUMN IF EXISTS holder_id');

        /* Refuse si quoi que ce soit est déjà nulle part : il n'y a pas de case
         * à inventer pour l'y remettre. Remonter la donnée d'abord, redescendre
         * le schéma ensuite. */
        $this->addSql('ALTER TABLE players MODIFY COLUMN coords_id INT(11) NOT NULL DEFAULT 0');
    }

    /**
     * MariaDB n'accepte pas `ADD CONSTRAINT IF NOT EXISTS` : on regarde
     * d'abord, et on ne pose que si la contrainte manque.
     */
    private function addConstraintIfMissing(string $table, string $name, string $sql): void
    {
        $exists = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
              WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
            [$table, $name]
        );

        if ($exists === 0) {
            $this->addSql($sql);
        }
    }
}
