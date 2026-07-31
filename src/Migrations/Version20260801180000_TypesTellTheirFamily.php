<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Chaque type dit à quelle famille il appartient — première étape du tronc
 * commun (docs/design-entity-types-inheritance.md).
 *
 * `races` porte cinq populations que rien ne sépare qu'un couple de colonnes,
 * `kind` et `structure_nature`. Le couple se lit mal : seize races de
 * personnages portent `structure_nature = 'edifice'`, non pas parce qu'elles
 * sont des bâtiments, mais parce que la colonne est NOT NULL et qu'il fallait
 * bien écrire quelque chose.
 *
 * La famille n'est pas inventée : c'est la règle que `TypeEditorFace::of()`
 * applique déjà à chaque affichage, écrite une fois pour toutes.
 *
 * **Colonne GÉNÉRÉE, et c'est tout l'intérêt.** La première version de cette
 * migration ajoutait une colonne ordinaire remplie par un `UPDATE`. La CI l'a
 * refusée, et elle avait raison : un test insère une race en cours de suite,
 * après le passage de la migration, sans connaître la nouvelle colonne — la
 * ligne naissait donc sans famille. Ce n'était pas un artefact de test mais un
 * vrai trou, car `RaceImporter`, `RaceSeedService` et l'écran d'admin insèrent
 * eux aussi des races sans rien savoir de cette colonne.
 *
 * Un écrivain peut oublier ; le schéma, non. La dérivation vit donc dans la
 * définition de la colonne : toute ligne, présente ou future, porte sa famille
 * sans que personne ait à y penser.
 *
 * Virtuelle, non stockée : elle se calcule à la lecture, ne coûte pas d'espace,
 * et rien ne la lit encore. À l'étape 2, quand Doctrine s'en servira comme
 * discriminant, il devra l'ÉCRIRE : elle deviendra une colonne ordinaire, par
 * une migration qui figera la valeur calculée ici.
 */
final class Version20260801180000_TypesTellTheirFamily extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'races.type_kind: generated column stating each type family (character|building|scenery|resource)';
    }

    public function up(Schema $schema): void
    {
        /* Rejouable, et rattrape la première forme de cette migration (colonne
         * ordinaire) là où elle serait déjà passée. */
        $this->addSql('ALTER TABLE races DROP COLUMN IF EXISTS type_kind');

        $this->addSql(
            "ALTER TABLE races ADD COLUMN type_kind VARCHAR(20) AS (CASE
                WHEN kind <> 'structure' THEN 'character'
                WHEN structure_nature = 'decor' THEN 'scenery'
                WHEN structure_nature = 'ressource' THEN 'resource'
                ELSE 'building'
            END) VIRTUAL"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE races DROP COLUMN IF EXISTS type_kind');
    }
}
