<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add items.pui and items.res, seeded for the items whose +M bonus
 * becomes +Pui (weapons, foci) or +Res (armors, wards).
 *
 * items.m is left in place on purpose: migrations run before the code
 * during a deploy, and the previous code still reads it. A follow-up MR
 * drops the column once no deployed code references it.
 */
final class Version20260722200000_ChangeMtoPuiResItems extends AbstractMigration
{
    /** Values for the new 'pui' column per item name. */
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

    /** Values for the new 'res' column per item name. */
    private const RES_VALUES = [
        'armure_hoplitique' => 1,
        'cape_doree' => 1,
        'peau_granit_manifiee' => 1,
    ];

    public function getDescription(): string
    {
        return 'Add items.pui and items.res with per-item seed values (items.m dropped in a follow-up)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE items ADD COLUMN IF NOT EXISTS `pui` INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE items ADD COLUMN IF NOT EXISTS `res` INT NOT NULL DEFAULT 0');

        foreach (self::PUI_VALUES as $itemName => $value) {
            $this->addSql('UPDATE items SET pui = ? WHERE name = ?', [$value, $itemName]);
        }

        foreach (self::RES_VALUES as $itemName => $value) {
            $this->addSql('UPDATE items SET res = ? WHERE name = ?', [$value, $itemName]);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE items DROP COLUMN IF EXISTS `pui`');
        $this->addSql('ALTER TABLE items DROP COLUMN IF EXISTS `res`');
    }
}
