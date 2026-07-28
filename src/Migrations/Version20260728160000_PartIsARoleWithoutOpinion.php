<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * `part` — la case d'emprise sur laquelle personne ne s'est prononcé.
 *
 * Poser une emprise depuis une découpe demande d'écrire un rôle pour chaque
 * case, et le vocabulaire n'en avait aucun pour « appartient à l'entité, sans
 * rien prétendre de plus ». Les cinq rôles existants disent tous quelque
 * chose : `block` barre, `cover`, `door` et `open` laissent passer, `anchor`
 * est la case de référence et ne peut pas se répéter — l'invariant veut une
 * ancre et une seule par entité.
 *
 * Restait à choisir entre deux mauvaises réponses : réutiliser `anchor` et
 * rompre l'invariant, ou RÉSOUDRE le rôle à l'écriture — `block` si le type
 * bloque, `open` sinon. La seconde fige une réponse qui change quand
 * `races.blocks_passage` change : une fois le mur rendu franchissable, ses
 * cases continueraient de barrer le chemin, sans que rien ne le dise.
 *
 * `part` laisse la question ouverte, exactement comme `anchor` la laisse
 * ouverte aujourd'hui. `TileOccupancyService` s'en accommode sans changer :
 * un rôle qu'il ne connaît pas s'en remet déjà au type.
 *
 * La migration ne change que le commentaire de la colonne — le vocabulaire
 * n'est contraint par aucune énumération, et c'est tant mieux : une colonne
 * ENUM aurait fait de cet ajout une reconstruction de table. Le commentaire
 * reste la seule documentation du schéma lue depuis phpMyAdmin, et la laisser
 * mentir sur les valeurs admises est le genre de dette qui se paie en
 * relecture.
 */
final class Version20260728160000_PartIsARoleWithoutOpinion extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'entity_cells.role documents the `part` role — an emprise cell with no opinion of its own';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "ALTER TABLE entity_cells
             MODIFY role VARCHAR(16) NOT NULL DEFAULT 'anchor'
             COMMENT 'anchor|part|block|cover|door|open — part = appartient à l''emprise, le type tranche'"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "ALTER TABLE entity_cells
             MODIFY role VARCHAR(16) NOT NULL DEFAULT 'anchor'
             COMMENT 'anchor|block|cover|door|open'"
        );
    }
}
