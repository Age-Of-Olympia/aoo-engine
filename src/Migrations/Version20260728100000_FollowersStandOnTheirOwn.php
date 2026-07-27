<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Les suivants cessent d'être du décor.
 *
 * Un « suivant » est ce qui accompagne un personnage sur la carte : l'étal du
 * marchand, le double d'illusion. Il était rangé dans `map_foregrounds`, avec
 * les rochers et les bannières, et `players_followers` ne portait qu'un
 * pointeur vers cette ligne de décor.
 *
 * # Ce que ça cassait
 *
 * `Player::add_follower()` insérait un décor au nom CODÉ EN DUR
 * (`'marchand'`, quel que soit le suivant demandé), puis le retrouvait par
 * un `SELECT … WHERE name = ? AND coords_id = ?`. Deux défauts s'ensuivent :
 *
 * - demander un autre suivant que le marchand — le double, `doubles/<id>` —
 *   posait un `marchand` puis cherchait un `doubles/<id>` inexistant, et
 *   déréférençait null ;
 * - même pour le marchand, la relecture pouvait ADOPTER une ligne de décor
 *   déjà présente sur la case au lieu de celle qu'on venait de poser. Comme
 *   `delete_follower()` supprime ensuite la ligne de décor pointée, un
 *   marchand qui rendait son tablier effaçait un décor de la carte.
 *
 * Le risque est réel et mesurable : sur la carte de production, 33 décors
 * `marchand` cohabitent avec 14 suivants — 19 de ces décors n'appartiennent
 * à personne, et rien ne les distingue des autres.
 *
 * # Ce que ça devient
 *
 * `players_followers` porte son propre nom et sa propre case. Un suivant
 * n'est plus une ligne de décor déguisée : c'est un accessoire d'entité, qui
 * se pose, se déplace et se retire sans jamais toucher au décor.
 *
 * Les lignes de `map_foregrounds` qui n'étaient là que pour eux disparaissent
 * — les 19 décors `marchand` qui n'appartiennent à personne restent, eux :
 * ce sont de vrais décors, posés par un animateur.
 *
 * Idempotente : une seconde exécution ne trouve plus de pointeur à convertir.
 */
final class Version20260728100000_FollowersStandOnTheirOwn extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'players_followers carries its own name and tile instead of pointing at a decor row';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            ALTER TABLE players_followers
            ADD COLUMN IF NOT EXISTS name VARCHAR(255) NOT NULL DEFAULT ''
                COMMENT 'Visuel du suivant — img/foregrounds/<name>.png',
            ADD COLUMN IF NOT EXISTS coords_id INT NOT NULL DEFAULT 0
                COMMENT 'Case du suivant : il traîne derrière son porteur, il a sa propre position'
        ");

        /* Le nom et la case viennent de la ligne de décor pointée : c'est
         * exactement ce qu'elle portait pour eux. */
        $this->addSql("
            UPDATE players_followers f
            JOIN map_foregrounds m ON m.id = f.foreground_id
            SET f.name = m.name, f.coords_id = m.coords_id
            WHERE f.name = ''
        ");

        /* La clé étrangère vers le décor part la première : tant qu'elle tient,
         * ni la ligne de décor ni la colonne ne peuvent s'en aller. Son nom est
         * cherché plutôt que supposé — il est auto-généré (`_ibfk_3` ici), donc
         * rien ne garantit qu'il soit le même partout. */
        foreach ($this->foreignKeysTo('players_followers', 'map_foregrounds') as $constraint) {
            $this->addSql('ALTER TABLE players_followers DROP FOREIGN KEY ' . $constraint);
        }

        /* Les décors qui n'existaient que pour un suivant s'en vont avec lui.
         * Ceux que personne ne revendique restent : ce sont de vrais décors. */
        $this->addSql("
            DELETE m FROM map_foregrounds m
            JOIN players_followers f ON f.foreground_id = m.id
        ");

        $this->addSql('ALTER TABLE players_followers DROP COLUMN IF EXISTS foreground_id');
    }

    /**
     * Le retour repose les lignes de décor et le pointeur.
     *
     * Les identifiants ne seront pas les mêmes qu'avant — ils sont
     * auto-incrémentés —, mais la carte retrouve ses suivants au bon endroit,
     * ce qui est ce qui compte.
     */
    public function down(Schema $schema): void
    {
        $this->addSql("
            ALTER TABLE players_followers
            ADD COLUMN IF NOT EXISTS foreground_id INT NOT NULL DEFAULT 0
        ");

        $this->addSql("
            INSERT INTO map_foregrounds (name, coords_id)
            SELECT name, coords_id FROM players_followers WHERE name <> ''
        ");

        $this->addSql("
            UPDATE players_followers f
            JOIN map_foregrounds m ON m.name = f.name AND m.coords_id = f.coords_id
            SET f.foreground_id = m.id
            WHERE f.foreground_id = 0
        ");

        $this->addSql('ALTER TABLE players_followers DROP COLUMN IF EXISTS name');
        $this->addSql('ALTER TABLE players_followers DROP COLUMN IF EXISTS coords_id');
    }

    /**
     * Les clés étrangères d'une table vers une autre, par leur nom réel.
     *
     * MariaDB les nomme toute seule quand on ne le fait pas — `_ibfk_3` ici —
     * et cette numérotation dépend de l'ordre de création. La supposer, c'est
     * écrire une migration qui marche sur une base et pas sur la suivante.
     *
     * @return list<string>
     */
    private function foreignKeysTo(string $table, string $referenced): array
    {
        /** @var list<string> */
        return $this->connection->fetchFirstColumn(
            'SELECT DISTINCT CONSTRAINT_NAME
               FROM information_schema.KEY_COLUMN_USAGE
              WHERE CONSTRAINT_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                AND REFERENCED_TABLE_NAME = ?',
            [$table, $referenced]
        );
    }
}
