<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Drop column 'm' from items, add 'pui' and 'res' columns with specific item default values.
 */
final class Version20260721100000_ChangeMtoPuiRes extends AbstractMigration
{
    /** Values for the new 'pui' column per item (lowercase code). */
    private const PUI_VALUES = [
        'anneau_puissance' => 1,
        'lame_sainte' => 1,
        'sceptre_puissance' => 1,
        'sceptre_mage' => 1,
        'baton_archimage' => 2,
        'baton_sage' => 1,
        'orbe_mana' => 1,
        'epee_casca' => 3,
    ];

    /** Values for the new 'res' column per item (lowercase code). */
    private const RES_VALUES = [
        'armure_hoplitique' => 1,
        'cape_doree' => 1,
        'peau_granit_manifiee' => 1,
    ];

    public function getDescription(): string
    {
        return 'Drop column m from items, add columns pui and res and seed specific values';
    }

    public function up(Schema $schema): void
    {
        // 1. Drop column 'm' and add 'pui' & 'res'
        $this->addSql('ALTER TABLE items DROP COLUMN IF EXISTS `m`');
        $this->addSql('ALTER TABLE items ADD COLUMN IF NOT EXISTS `pui` INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE items ADD COLUMN IF NOT EXISTS `res` INT NOT NULL DEFAULT 0');

        // 2. Seed 'pui' values
        foreach (self::PUI_VALUES as $itemName => $value) {
            $this->addSql(
                'UPDATE items SET pui = ? WHERE name = ? OR code = ?',
                [$value, $itemName, strtoupper($itemName)]
            );
        }

        // 3. Seed 'res' values
        foreach (self::RES_VALUES as $itemName => $value) {
            $this->addSql(
                'UPDATE items SET res = ? WHERE name = ? OR code = ?',
                [$value, $itemName, strtoupper($itemName)]
            );
        }
    }

    public function down(Schema $schema): void
    {
        // Re-add column 'm' (defaults to 0) and drop 'pui' & 'res'
        $this->addSql('ALTER TABLE items ADD COLUMN IF NOT EXISTS `m` INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE items DROP COLUMN IF EXISTS `pui`');
        $this->addSql('ALTER TABLE items DROP COLUMN IF EXISTS `res`');
    }
}