<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Mise à jour des pré-requis des actions existantes.
 */
final class Version20260821300000_UpdateActionPrerequisites extends AbstractMigration
{
    /** Nouvelles valeurs des prérequis pour action_passives [name => prerequisites] */
    private const PASSIVES_PREREQUISITES_UP = [
        'maitre_bretteur'     => '{"forbidden": ["inepuisable"]}',
        'duelliste'           => '{"forbidden": ["anguille"]}',
        'escarmoucheur'       => '{"forbidden": ["inepuisable"]}',
        'lanceur'             => '{"forbidden": ["anguille"]}',
        'tireur_elite'        => '{"forbidden": ["anguille"]}',
        'anguille'            => '{"forbidden": ["duelliste"]}',
        'couverture'          => '{"forbidden": ["reflexes_fulgurants"]}',
        'inepuisable'         => '{"forbidden": ["maitre_bretteur","escarmoucheur"]}',
        'reflexes_fulgurants' => '{"forbidden":["couverture"]}',
        'volonte_fer'         => '{"forbidden":["focus_mental"]}',
    ];

    /** Anciennes valeurs pour le rollback (action_passives) */
    private const PASSIVES_PREREQUISITES_DOWN = [
        'maitre_bretteur'     => null,
        'duelliste'           => null,
        'escarmoucheur'       => null,
        'lanceur'             => null,
        'tireur_elite'        => null,
        'anguille'            => null,
        'couverture'          => null,
        'inepuisable'         => null,
        'reflexes_fulgurants' => null,
        'volonte_fer'         => null,
    ];

    /** Nouvelles valeurs des prérequis pour actions [name => prerequisites] */
    private const ACTIONS_PREREQUISITES_UP = [
        'colere_nature'           => '{"need": ["maladresse","vulnerabilite"]}',
        'puissance_nature'        => '{"need": ["coup_precis","peau_de_granit"]}',
        'fatigue'                 => '{"need": ["vulnerabilite"]}',
        'malchance'               => '{"need": ["maladresse"]}',
        'reflexes_accrus'         => '{"need": ["peau_de_granit"]}',
        'aide'                    => '{"need": ["coup_precis"]}',
        'regeneration_acceleree'  => '{"need": ["regeneration"]}',
        'restauration_majeure'    => '{"need": ["restauration"]}',
        'anemie'                  => '{"need": ["faiblesse"]}',
        'benediction'             => '{"need": ["reflexes_accrus","aide"]}',
        'cuirasse'                => '{"need": ["armure"]}',
        'ferocite'                => '{"need": ["agressivite"]}',
        'friabilite'              => '{"need": ["fragilite"]}',
        'puissance_lutin'         => '{"need": ["malchance","fatigue"]}',
        'recuperation_superieure' => '{"need": ["recuperation"]}',
        'extenuation'             => '{"need": ["fatigue"]}',
        'guigne'                  => '{"need": ["malchance"]}',
        'sauvegarde'              => '{"need": ["reflexes_accrus"]}',
        'virtuose'                => '{"need": ["aide"]}',
    ];

    /** Anciennes valeurs pour le rollback (actions) */
    private const ACTIONS_PREREQUISITES_DOWN = [
        'colere_nature'           => null,
        'puissance_nature'        => null,
        'fatigue'                 => null,
        'malchance'               => null,
        'reflexes_accrus'         => null,
        'aide'                    => null,
        'regeneration_acceleree'  => null,
        'restauration_majeure'    => null,
        'anemie'                  => null,
        'benediction'             => null,
        'cuirasse'                => null,
        'ferocite'                => null,
        'friabilite'              => null,
        'puissance_lutin'         => null,
        'recuperation_superieure' => null,
        'extenuation'             => null,
        'guigne'                  => null,
        'sauvegarde'              => null,
        'virtuose'                => null,
    ];

    public function getDescription(): string
    {
        return 'Mise à jour des prérequis pour les tables action_passives et actions.';
    }

    public function up(Schema $schema): void
    {
        // 1. Mise à jour de la table action_passives
        foreach (self::PASSIVES_PREREQUISITES_UP as $name => $prerequisites) {
            $this->addSql(
                'UPDATE action_passives SET prerequisites = ? WHERE name = ?',
                [$prerequisites, $name]
            );
        }

        // 2. Mise à jour de la table actions
        foreach (self::ACTIONS_PREREQUISITES_UP as $name => $prerequisites) {
            $this->addSql(
                'UPDATE actions SET prerequisites = ? WHERE name = ?',
                [$prerequisites, $name]
            );
        }
    }

    public function down(Schema $schema): void
    {
        // 1. Rollback de la table action_passives
        foreach (self::PASSIVES_PREREQUISITES_DOWN as $name => $prerequisites) {
            $this->addSql(
                'UPDATE action_passives SET prerequisites = ? WHERE name = ?',
                [$prerequisites, $name]
            );
        }

        // 2. Rollback de la table actions
        foreach (self::ACTIONS_PREREQUISITES_DOWN as $name => $prerequisites) {
            $this->addSql(
                'UPDATE actions SET prerequisites = ? WHERE name = ?',
                [$prerequisites, $name]
            );
        }
    }
}