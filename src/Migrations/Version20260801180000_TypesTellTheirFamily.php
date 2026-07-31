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
 * La famille n'est donc pas inventée ici : elle est DÉRIVÉE de ce que le code
 * calcule déjà à chaque affichage, dans `TypeEditorFace::of()`. Cette
 * migration ne fait que l'écrire une fois pour toutes.
 *
 * Additive et muette : rien ne lit encore cette colonne. Elle sera le
 * discriminant du tronc `EntityType` à l'étape suivante — et c'est pour cela
 * qu'elle est posée seule, déployable sans rien changer au comportement.
 */
final class Version20260801180000_TypesTellTheirFamily extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'races.type_kind: each type states its family (character|building|scenery|resource)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "ALTER TABLE races ADD COLUMN IF NOT EXISTS type_kind VARCHAR(20) NOT NULL DEFAULT ''"
        );

        /* La règle de TypeEditorFace::of(), mot pour mot :
         *   pas une structure          → character
         *   structure + decor          → scenery
         *   structure + ressource      → resource
         *   structure + edifice|autre  → building
         * L'ordre des CASE compte : `kind` tranche avant la nature, puisque
         * c'est justement la nature qui ne veut rien dire pour un personnage. */
        $this->addSql(
            "UPDATE races
                SET type_kind = CASE
                    WHEN kind <> 'structure' THEN 'character'
                    WHEN structure_nature = 'decor' THEN 'scenery'
                    WHEN structure_nature = 'ressource' THEN 'resource'
                    ELSE 'building'
                END
              WHERE type_kind = ''"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE races DROP COLUMN IF EXISTS type_kind');
    }
}
