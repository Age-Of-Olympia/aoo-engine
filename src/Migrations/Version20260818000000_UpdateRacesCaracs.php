<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rééquilibrage des caractéristiques des races après séparation de la M en Pui/Rés.
 */
final class Version20260818120000_UpdateRacesStatsBalancing extends AbstractMigration
{
    /** Nouvelles valeurs pour la méthode up() [code_race => [colonne => valeur]] */
    private const RACES_UP_VALUES = [
        'nain' => [
            'agi' => 7,
            'pv'  => 60,
            'pm'  => 25,
            'r'   => 7,
            'rm'  => 5,
        ],
        'geant' => [
            'agi' => 8,
            'pv'  => 70,
            'pm'  => 30,
            'r'   => 6,
            'rm'  => 6,
        ],
        'olympien' => [
            'agi' => 9,
        ],
        'hs' => [
            'f'   => 8,
            'pv'  => 50,
            'rm'  => 6,
        ],
    ];

    /** Anciennes valeurs pour la méthode down() [code_race => [colonne => valeur]] */
    private const RACES_DOWN_VALUES = [
        'nain' => [
            'agi' => 6,
            'pv'  => 50,
            'pm'  => 15,
            'r'   => 5,
            'rm'  => 4,
        ],
        'geant' => [
            'agi' => 7,
            'pv'  => 65,
            'pm'  => 20,
            'r'   => 5,
            'rm'  => 5,
        ],
        'olympien' => [
            'agi' => 8,
        ],
        'hs' => [
            'f'   => 7,
            'pv'  => 45,
            'rm'  => 7,
        ],
    ];

    public function getDescription(): string
    {
        return 'Rééquilibrage des caractéristiques des races après séparation de la M en Pui/Rés';
    }

    public function up(Schema $schema): void
    {
        foreach (self::RACES_UP_VALUES as $raceName => $stats) {
            $setClause = implode(', ', array_map(fn($col) => "`$col` = ?", array_keys($stats)));
            $params = array_merge(array_values($stats), [$raceName, strtoupper($raceName)]);

            $this->addSql(
                "UPDATE races SET {$setClause} WHERE name = ? OR code = ?",
                $params
            );
        }
    }

    public function down(Schema $schema): void
    {
        foreach (self::RACES_DOWN_VALUES as $raceName => $stats) {
            $setClause = implode(', ', array_map(fn($col) => "`$col` = ?", array_keys($stats)));
            $params = array_merge(array_values($stats), [$raceName, strtoupper($raceName)]);

            $this->addSql(
                "UPDATE races SET {$setClause} WHERE name = ? OR code = ?",
                $params
            );
        }
    }
}