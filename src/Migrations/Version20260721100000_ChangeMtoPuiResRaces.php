<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add races.pui and races.res, seeded per race: M splits into Puissance
 * (magic damage) and Résistance (magic damage reduction).
 *
 * races.m is left in place on purpose: migrations run before the code
 * during a deploy, and the previous code still reads it. A follow-up MR
 * drops the column once no deployed code references it.
 */
final class Version20260721100000_ChangeMtoPuiResRaces extends AbstractMigration
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
        return 'Add races.pui and races.res with per-race seed values (races.m dropped in a follow-up)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE races ADD COLUMN IF NOT EXISTS `pui` INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE races ADD COLUMN IF NOT EXISTS `res` INT NOT NULL DEFAULT 0');

        foreach (self::PUI_VALUES as $raceName => $value) {
            $this->addSql('UPDATE races SET pui = ? WHERE name = ?', [$value, $raceName]);
        }

        foreach (self::RES_VALUES as $raceName => $value) {
            $this->addSql('UPDATE races SET res = ? WHERE name = ?', [$value, $raceName]);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE races DROP COLUMN IF EXISTS `pui`');
        $this->addSql('ALTER TABLE races DROP COLUMN IF EXISTS `res`');
    }
}
