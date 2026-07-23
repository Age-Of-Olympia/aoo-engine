<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Move the resource/wall type catalog from config/constants.php
 * (RESOURCES_PV) into the DB (resource_types), behind the
 * ResourceTypeService gateway — same move as the effects, races and
 * factions catalogs before it.
 *
 * Semantics unchanged: pv < 0 marks a resource type (-1 récoltable,
 * -2 épuisé at the map_resources.damages instance level), pv > 0 is the
 * hit-point total of the destructible survivors (altars, unique_* types
 * still handled by destroy.php), and an unknown name is indestructible.
 * The positive entries of the ex-walls now converted to structure races
 * are kept for the survivors and for history, exactly as the constant
 * kept them.
 *
 * Idempotent (IF NOT EXISTS + no-op ON DUPLICATE KEY): re-running never
 * clobbers admin-edited rows.
 */
final class Version20260723120000_ResourceTypesFromConstants extends AbstractMigration
{
    /**
     * Snapshot of RESOURCES_PV (the source is deleted with this migration).
     *
     * @var array<string, int>
     */
    private const RESOURCE_TYPES = [
        // murs
        'mur_pierre' => 150,
        'mur_pierre_broken' => 150,
        'mur_pierre_bleue' => 150,
        'mur_pierre_bleue_broken' => 150,
        'mur_noir' => 120,
        'mur_noir_broken' => 120,
        'mur_bois' => 100,
        'mur_bois_broken' => 100,
        'mur_bois_petrifie' => 120,
        'mur_bois_petrifie_broken' => 120,
        'mur_vegetal' => 120,
        'mur_vegetal_broken' => 120,
        'mur_fer' => 180,
        'mur_fer_broken' => 180,
        'mur_crepusculaire' => 120,
        'mur_crepusculaire_broken' => 120,
        'mur_blanc' => 180,
        'mur_blanc_broken' => 180,
        'muret' => 40,
        'barricade' => 40,

        // coffres
        'coffre_metal' => 1,
        'coffre_bois' => 1,
        'coffre_bois_petrifie' => 1,
        'coffre_metal_broken' => 1,
        'coffre_bois_broken' => 1,
        'coffre_bois_petrifie_broken' => 1,

        'pierre_precieuse' => 500,

        // décos
        'altar' => 25,
        'altar_broken' => 25,

        'unique_disque_solaire' => 800,

        'piedestal' => 15,
        'piedestal_broken' => 15,
        'piedestal_pierre' => 10,
        'piedestal_pierre_broken' => 10,

        'table_bois' => 5,
        'table_bois_broken' => 5,
        'tonneau' => 5,
        'tonneau_broken' => 5,
        'torche_sol' => 10,
        'torche_sol_broken' => 10,
        'trone' => 25,
        'trone_broken' => 25,
        'tombe2' => 10,
        'statue_monstrueuse' => 10,
        'statue_ailee' => 10,
        'statue_heroique' => 10,
        'statue_gisant' => 10,
        'statue_forestiere' => 10,
        'roue_a_aubes' => 10,
        'lanternesurpied_geant' => 10,
        'monolithe_flamboyant' => 10,
        'statue_colosses' => 10,
        'totem_crane' => 10,
        'statue_garde' => 10,
        'statue_servant' => 10,
        'totem_sauvage' => 10,
        'totem_magique' => 10,
        'pilier_nain' => 10,
        'pilier' => 10,
        'statue_noble' => 10,
        'flamme_bleue' => 10,
        'sarcophage' => 50,
        'statue_kraken' => 30,
        'tombe' => 30,
        'tombe_detruite' => 10,

        // cocotiers
        'cocotier1' => 1,
        'cocotier2' => 1,
        'cocotier3' => 1,

        // ressources (-1 récoltable, -2 épuisé)
        'arbre1' => -1,
        'arbre2' => -1,
        'arbre3' => -1,
        'arbre4' => -1,
        'arbre5' => -1,
        'arbre6' => -1,

        'arbre_petrifie1' => -1,
        'arbre_petrifie2' => -1,
        'arbre_petrifie3' => -1,
        'arbre_petrifie4' => -1,
        'arbre_petrifie5' => -1,
        'arbre_petrifie6' => -1,

        'cendre' => -1,
        'cuir' => -1,
        'cuivre' => -1,
        'etain' => -1,
        'fer' => -1,
        'nickel' => -1,
        'salpetre' => -1,
        'tourbe' => -1,
        'mana' => -1,
        'bronze' => -1,

        'herbe1' => -1,
        'herbe2' => -1,
        'herbe3' => -1,

        'jungle1' => -1,
        'jungle2' => -1,
        'jungle3' => -1,

        'pierre1' => -1,
        'pierre2' => -1,
        'pierre3' => -1,

        'pierre_noire1' => -1,
        'pierre_noire2' => -1,
        'pierre_noire3' => -1,

        'rocher_desert1' => -1,
        'rocher_desert2' => -1,
        'rocher_desert3' => -1,
    ];

    public function getDescription(): string
    {
        return 'Move the resource/wall type catalog (RESOURCES_PV) into resource_types';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "CREATE TABLE IF NOT EXISTS resource_types (
                name VARCHAR(100) NOT NULL PRIMARY KEY,
                pv INT NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        foreach (self::RESOURCE_TYPES as $name => $pv) {
            $this->addSql(
                'INSERT INTO resource_types (name, pv) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE name = name',
                [$name, $pv]
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS resource_types');
    }
}
