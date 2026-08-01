<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ce qui était remisé aux limbes n'est plus quelque part.
 *
 * `limbes_batiments` était un plan inventé pour dire « hors du plateau » avec
 * le seul mot dont le modèle disposait : une case. Maintenant que l'absence de
 * localisation se dit, les remisés la prennent, et le faux lieu peut fermer.
 *
 * Les entités concernées gardent tout le reste — leur ligne, leur id, leur nom.
 * C'est le sens du remisage : les événements qui les nomment restent vrais et
 * `getNextEntityId` ne recycle jamais leur id. Elles n'étaient déjà visibles
 * d'aucune requête de plateau, qui filtre par plan ; elles ne le sont pas
 * davantage en n'étant nulle part.
 *
 * Les cases du plan partent ensuite, mais seulement celles que plus personne ne
 * regarde : une ligne `coords` est visée par une douzaine de tables, et un
 * DELETE aveugle échouerait sur la première. Ce qui résiste reste — inerte,
 * sur un plan que plus rien ne peuple.
 */
final class Version20260803020000_TheLimbsLoseTheirPlan extends AbstractMigration
{
    private const VANISHED_PLAN = 'limbes_batiments';

    public function getDescription(): string
    {
        return 'shelved entities move from the limbes_batiments plan to no location at all';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "UPDATE players p
               JOIN coords c ON c.id = p.coords_id
                SET p.coords_id = NULL
              WHERE c.plan = ?",
            [self::VANISHED_PLAN]
        );

        /* Une remisée n'occupe rien : si un passage précédent a laissé des
         * cellules sur le faux plan, elles s'en vont avec lui. */
        $this->addSql(
            "DELETE ec FROM entity_cells ec
               JOIN coords c ON c.id = ec.coords_id
              WHERE c.plan = ?",
            [self::VANISHED_PLAN]
        );

        $this->addSql(
            "DELETE FROM coords
              WHERE plan = ?
                AND id NOT IN (SELECT coords_id FROM entity_cells)
                AND id NOT IN (SELECT DISTINCT coords_id FROM players WHERE coords_id IS NOT NULL)",
            [self::VANISHED_PLAN]
        );
    }

    /**
     * Irréversible, et c'est honnête : rien ne dit laquelle des remisées
     * dormait sur quelle case d'un plan qui ne voulait rien dire. Redescendre
     * le schéma de la contenance suffit à retrouver l'état d'avant.
     */
    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            'Les remisées ont perdu une localisation qui ne signifiait rien ; on ne la réinvente pas.'
        );
    }
}
