<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * La famille devient une colonne ORDINAIRE, sans cesser de se remplir seule.
 *
 * Étape 2 du tronc commun (docs/design-entity-types-inheritance.md) : Doctrine
 * va se servir de `type_kind` comme DISCRIMINANT, et un discriminant, il
 * l'écrit. Une colonne générée est en lecture seule — elle ne peut donc plus
 * convenir.
 *
 * Mais la leçon de l'étape 1 tient toujours, et la CI l'avait apprise à nos
 * dépens : un écrivain qui ignore la colonne — `RaceImporter`, un INSERT de
 * fixture, l'écran d'admin — produirait de nouveau des lignes sans famille.
 * Rendre la colonne ordinaire sans filet, ce serait rouvrir le trou qu'on
 * vient de boucher.
 *
 * La dérivation passe donc dans deux déclencheurs, qui ne remplissent QUE le
 * vide : ce que Doctrine écrit explicitement est respecté, ce qu'un écrivain
 * oublie est calculé. Les deux sources ne se disputent pas, elles se
 * complètent — et le jour où `type_kind` deviendra la vérité et `kind` /
 * `structure_nature` des vestiges, il suffira de retirer les déclencheurs.
 *
 * MariaDB refuse de convertir une colonne générée en place (« not yet
 * supported for generated columns »), d'où le passage par une colonne
 * intermédiaire qui recopie la valeur calculée avant de prendre sa place.
 */
final class Version20260801200000_TheFamilyBecomesWritable extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'races.type_kind: generated column becomes a writable discriminator, kept filled by triggers';
    }

    /** La règle de TypeEditorFace::of(), pour un contexte NEW de déclencheur. */
    private const DERIVATION = "CASE
        WHEN NEW.kind <> 'structure' THEN 'character'
        WHEN NEW.structure_nature = 'decor' THEN 'scenery'
        WHEN NEW.structure_nature = 'ressource' THEN 'resource'
        ELSE 'building'
    END";

    public function up(Schema $schema): void
    {
        /* Colonne intermédiaire : elle recopie ce que la colonne générée
         * calcule, puis prend son nom. */
        $this->addSql("ALTER TABLE races ADD COLUMN IF NOT EXISTS type_kind_w VARCHAR(20) NOT NULL DEFAULT ''");
        $this->addSql('UPDATE races SET type_kind_w = type_kind');
        $this->addSql('ALTER TABLE races DROP COLUMN IF EXISTS type_kind');
        $this->addSql("ALTER TABLE races CHANGE type_kind_w type_kind VARCHAR(20) NOT NULL DEFAULT ''");

        foreach (['bi' => 'BEFORE INSERT', 'bu' => 'BEFORE UPDATE'] as $suffix => $moment) {
            $this->addSql("DROP TRIGGER IF EXISTS races_type_kind_{$suffix}");
            $this->addSql(
                "CREATE TRIGGER races_type_kind_{$suffix} {$moment} ON races FOR EACH ROW
                 SET NEW.type_kind = IF(
                     NEW.type_kind IS NULL OR NEW.type_kind = '',
                     " . self::DERIVATION . ",
                     NEW.type_kind
                 )"
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TRIGGER IF EXISTS races_type_kind_bi');
        $this->addSql('DROP TRIGGER IF EXISTS races_type_kind_bu');
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
}
