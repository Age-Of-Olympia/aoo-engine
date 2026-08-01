<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A harvestable resource can be felled, and it takes a hundred points to do it.
 *
 * Arbitrated: resources stop being indestructible. `melee` and `distance`
 * already accept a structure as a target, so once a resource is an entity the
 * only question left is how much life it has — ten was the walls conversion's
 * default and would have felled a tree in a blow or two.
 *
 * A hundred is a balance dial, so it also becomes an admin setting from here
 * on; this migration only moves the types that still carry the old default,
 * leaving any figure someone has already chosen by hand.
 */
final class Version20260730200000_HarvestablesAreFellable extends AbstractMigration
{
    private const OLD_DEFAULT = 10;
    private const NEW_DEFAULT = 100;

    public function getDescription(): string
    {
        return 'HarvestableInterface types go from 10 to 100 life points; the default becomes a setting';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "UPDATE races r
                SET r.pv = ?
              WHERE r.kind = 'structure'
                AND r.structure_nature = 'ressource'
                AND r.pv = ?",
            [self::NEW_DEFAULT, self::OLD_DEFAULT]
        );

        $this->addSql(
            "INSERT INTO admin_settings (name, value) VALUES ('harvest_default_pv', ?)
             ON DUPLICATE KEY UPDATE value = value",
            [(string) self::NEW_DEFAULT]
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "UPDATE races
                SET pv = ?
              WHERE kind = 'structure' AND structure_nature = 'ressource' AND pv = ?",
            [self::OLD_DEFAULT, self::NEW_DEFAULT]
        );

        $this->addSql("DELETE FROM admin_settings WHERE name = 'harvest_default_pv'");
    }
}
