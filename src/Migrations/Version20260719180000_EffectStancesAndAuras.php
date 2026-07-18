<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Dernière fournée des comportements d'effets en données : les cinq
 * postures de combat (déclencheurs de défense consommés à l'attaque,
 * ex-DodgeCondition codée par nom), le vol (traversée d'obstacles),
 * le multiplicateur de coût (ex-imposture, coût × (valeur portée + 1)
 * sur les actions qui le déclarent) et le blocage marchand/écoles
 * (ex-adrénaline — le cron d'intérêts bancaires, lui, reste keyé sur
 * le nom en attendant sa refonte).
 *
 * Une posture se décrit par : la portée d'annulation (sort / physique /
 * tout), des exigences d'armes des deux côtés, une réaction (immobiliser
 * l'attaquant, se décaler d'une case, effacer le double) et un gabarit
 * de message ({attacker}/{defender}).
 */
final class Version20260719180000_EffectStancesAndAuras extends AbstractMigration
{
    /** Snapshot des comportements historiques. */
    private const BEHAVIORS = [
        'parade' => [
            'dodge_scope' => 'any', 'dodge_attacker_weapon' => 'melee', 'dodge_defender_weapon' => 'melee',
            'dodge_message' => '{defender} pare votre attaque grâce à sa technique !',
        ],
        'leurre' => [
            'dodge_scope' => 'spell',
            'dodge_message' => '{defender} pare votre attaque grâce à un sort !',
        ],
        'dedoublement' => [
            'dodge_scope' => 'any', 'dodge_reaction' => 'delete_double',
            'dodge_message' => 'Vous avez attaqué un double de {defender} !',
        ],
        'cle_de_bras' => [
            'dodge_scope' => 'any', 'dodge_attacker_weapon' => 'melee', 'dodge_defender_weapon' => 'poing',
            'dodge_reaction' => 'immobilize_attacker',
            'dodge_message' => '{defender} vous fait une clé de bras et vous immobilise !',
        ],
        'pas_de_cote' => [
            'dodge_scope' => 'physical', 'dodge_reaction' => 'step_aside',
            'dodge_message' => '{defender} esquive votre attaque avec un pas de côté !',
        ],
        'vol' => ['grants_flight' => 1],
        'imposture' => ['cost_multiplier' => 1, 'stack_refresh_duration' => 1],
        'adrenaline' => ['blocks_trading' => 1],
    ];

    public function getDescription(): string
    {
        return 'Data-driven combat stances (dodge triggers), flight, cost multiplier and trading block';
    }

    public function up(Schema $schema): void
    {
        $columns = [
            "dodge_scope VARCHAR(10) NOT NULL DEFAULT ''",
            "dodge_attacker_weapon VARCHAR(10) NOT NULL DEFAULT ''",
            "dodge_defender_weapon VARCHAR(10) NOT NULL DEFAULT ''",
            "dodge_reaction VARCHAR(20) NOT NULL DEFAULT ''",
            "dodge_message VARCHAR(255) NOT NULL DEFAULT ''",
            'grants_flight TINYINT(1) NOT NULL DEFAULT 0',
            'cost_multiplier TINYINT(1) NOT NULL DEFAULT 0',
            'blocks_trading TINYINT(1) NOT NULL DEFAULT 0',
            'stack_refresh_duration TINYINT(1) NOT NULL DEFAULT 0',
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
    }

    public function down(Schema $schema): void
    {
        $drops = [
            'dodge_scope', 'dodge_attacker_weapon', 'dodge_defender_weapon',
            'dodge_reaction', 'dodge_message',
            'grants_flight', 'cost_multiplier', 'blocks_trading', 'stack_refresh_duration',
        ];
        $this->addSql(
            'ALTER TABLE effects ' . implode(', ', array_map(
                static fn (string $col): string => "DROP COLUMN IF EXISTS `{$col}`",
                $drops
            ))
        );
    }
}
