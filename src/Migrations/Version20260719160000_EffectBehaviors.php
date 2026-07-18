<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 2 du catalogue des effets : les COMPORTEMENTS génériques passent
 * en données. Un effet peut modifier les jets (attaque/défense), les
 * dégâts (infligés/subis, facteur), les poussées, bloquer la
 * récupération d'une carac au tour, régénérer, ou infliger un malus de
 * mouvement — tout cela était codé en dur par nom d'effet dans les
 * conditions de combat, LifeLoss et le moteur de tour.
 *
 * Les modificateurs ±1 sont multipliés par la VALEUR portée
 * (players_effects.value) : dexterite(+2) ajoute 2 au jet d'attaque.
 * Restent codées : les postures de combat (parade, leurre… — des
 * déclenchements contextuels), le vol, l'imposture.
 */
final class Version20260719160000_EffectBehaviors extends AbstractMigration
{
    /** Snapshot des comportements historiques, par effet. */
    private const BEHAVIORS = [
        'dexterite'      => ['roll_attack_mod' => 1],
        'maladresse'     => ['roll_attack_mod' => -1],
        'protection'     => ['roll_defense_mod' => 1],
        'vulnerabilite'  => ['roll_defense_mod' => -1],
        'agressivite'    => ['damage_dealt_mod' => 1],
        'faiblesse'      => ['damage_dealt_mod' => -1],
        'fragilite'      => ['damage_taken_mod' => 1],
        'armure'         => ['damage_taken_mod' => -1],
        'encaisse'       => ['damage_taken_factor' => 0.75],
        'renforcement'   => ['push_attack_mod' => 1],
        'stabilite'      => ['push_defense_mod' => 1],
        'instabilite'    => ['push_defense_mod' => -1],
        'poison'         => ['block_recovery' => 'pv'],
        'poison_magique' => ['block_recovery' => 'pm'],
        'regeneration'   => ['turn_regen' => 1],
        'ralentissement' => ['turn_mvt_malus' => 1],
    ];

    public function getDescription(): string
    {
        return 'Data-driven effect behaviors (roll/damage/push modifiers, recovery block, regen, mvt malus)';
    }

    public function up(Schema $schema): void
    {
        $columns = [
            'roll_attack_mod TINYINT NOT NULL DEFAULT 0',
            'roll_defense_mod TINYINT NOT NULL DEFAULT 0',
            'damage_dealt_mod TINYINT NOT NULL DEFAULT 0',
            'damage_taken_mod TINYINT NOT NULL DEFAULT 0',
            'push_attack_mod TINYINT NOT NULL DEFAULT 0',
            'push_defense_mod TINYINT NOT NULL DEFAULT 0',
            'damage_taken_factor DOUBLE NOT NULL DEFAULT 1',
            "block_recovery VARCHAR(4) NOT NULL DEFAULT ''",
            'turn_regen TINYINT(1) NOT NULL DEFAULT 0',
            'turn_mvt_malus TINYINT(1) NOT NULL DEFAULT 0',
        ];
        $this->addSql(
            'ALTER TABLE effects ' . implode(', ', array_map(
                static fn (string $def): string => 'ADD COLUMN IF NOT EXISTS ' . $def,
                $columns
            ))
        );

        foreach (self::BEHAVIORS as $name => $behavior) {
            $sets = implode(', ', array_map(
                static fn (string $column): string => "`{$column}` = ?",
                array_keys($behavior)
            ));
            $this->addSql(
                "UPDATE effects SET {$sets} WHERE name = ?",
                array_merge(array_values($behavior), [$name])
            );
        }

        // Le récap de tour disait « Régénération » ; le libellé hérité
        // d'EFFECTS_TXT portait une coquille.
        $this->addSql("UPDATE effects SET label = 'Régénération' WHERE name = 'regeneration' AND label = 'Regénération'");
    }

    public function down(Schema $schema): void
    {
        $drops = [
            'roll_attack_mod', 'roll_defense_mod', 'damage_dealt_mod', 'damage_taken_mod',
            'push_attack_mod', 'push_defense_mod', 'damage_taken_factor',
            'block_recovery', 'turn_regen', 'turn_mvt_malus',
        ];
        $this->addSql(
            'ALTER TABLE effects ' . implode(', ', array_map(
                static fn (string $col): string => "DROP COLUMN IF EXISTS `{$col}`",
                $drops
            ))
        );
    }
}
