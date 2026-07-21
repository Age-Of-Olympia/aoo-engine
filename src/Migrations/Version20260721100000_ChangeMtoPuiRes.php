<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Drop column 'm' from races, add 'pui' and 'res' columns with specific race default values.
 */
final class Version20260721130000_UpdateRacesStats extends AbstractMigration
{
    /** Values for the new 'pui' column per race (lowercase code). */
    private const PUI_VALUES = [
        'olympien' => 5,
        'geant' => 5,
        'nain' => 4,
        'elfe' => 6,
        'hs' => 5,
        'anima' => 5, 
        'dieu' => 7,
        'humain' => 4,
        'lutin' => 4,
        'protocole' => 4,
        'redoraan' => 4,
        'saurien' => 5,
        'triton' => 5,
        'troglodyte' => 5,
        'trotile' => 2,
        'centaure' => 5,
        'batiment' => 10,
    ];

    /** Values for the new 'res' column per race (lowercase code). */
    private const RES_VALUES = [
        'olympien' => 5,
        'geant' => 4,
        'nain' => 3,
        'elfe' => 5,
        'hs' => 6,
        'anima' => 5,
        'dieu' => 7,
        'humain' => 4,
        'lutin' => 3,
        'protocole' => 4,
        'redoraan' => 4,
        'saurien' => 5,
        'triton' => 5,
        'troglodyte' => 5,
        'trotile' => 2,
        'centaure' => 5,
        'batiment' => 10,
    ];

    public function getDescription(): string
    {
        return 'Drop column m from races, add columns pui and res and seed specific values';
    }

    public function up(Schema $schema): void
    {
        // 1. Drop column 'm' and add 'pui' & 'res'
        $this->addSql('ALTER TABLE races DROP COLUMN IF EXISTS `m`');
        $this->addSql('ALTER TABLE races ADD COLUMN IF NOT EXISTS `pui` INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE races ADD COLUMN IF NOT EXISTS `res` INT NOT NULL DEFAULT 0');

        // 2. Seed 'pui' values
        foreach (self::PUI_VALUES as $raceName => $value) {
            $this->addSql(
                'UPDATE races SET pui = ? WHERE name = ? OR code = ?',
                [$value, $raceName, strtoupper($raceName)]
            );
        }

        // 3. Seed 'res' values
        foreach (self::RES_VALUES as $raceName => $value) {
            $this->addSql(
                'UPDATE races SET res = ? WHERE name = ? OR code = ?',
                [$value, $raceName, strtoupper($raceName)]
            );
        }
    }

    public function down(Schema $schema): void
    {
        // Re-add column 'm' (defaults to 0) and drop 'pui' & 'res'
        $this->addSql('ALTER TABLE races ADD COLUMN IF NOT EXISTS `m` INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE races DROP COLUMN IF EXISTS `pui`');
        $this->addSql('ALTER TABLE races DROP COLUMN IF EXISTS `res`');
    }
}