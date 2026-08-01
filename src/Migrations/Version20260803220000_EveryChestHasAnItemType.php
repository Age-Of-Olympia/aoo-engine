<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Give `coffre_humain` an items row and every chest a durability its material
 * decides, before the conversion makes the value visible.
 */
final class Version20260803220000_EveryChestHasAnItemType extends AbstractMigration
{
    /** durability_max per material, decided rather than inherited. */
    private const LIFE = [
        'coffre_bois' => 40,
        'coffre_bois_petrifie' => 70,
        'coffre_metal' => 100,
        'coffre_humain' => 25,
    ];

    private const HUMAN_EXTRA = '{"name":"Coffre humain","img":"img/walls/coffre_humain.png",'
        . '"mini":"img/walls/coffre_humain.png"}';

    public function getDescription(): string
    {
        return 'items: coffre_humain gets a type, and every chest a life its material decides';
    }

    public function up(Schema $schema): void
    {
        // Cloned rather than spelled out: `items` carries some fifty columns.
        $this->addSql('CREATE TEMPORARY TABLE tmp_coffre_humain LIKE items');
        $this->addSql(
            "INSERT INTO tmp_coffre_humain
             SELECT * FROM items
              WHERE name = 'coffre_bois'
                AND NOT EXISTS (SELECT 1 FROM items i2 WHERE i2.name = 'coffre_humain')"
        );
        /* id = 0 lets AUTO_INCREMENT assign the next one. */
        $this->addSql(
            "UPDATE tmp_coffre_humain
                SET id = 0,
                    name = 'coffre_humain',
                    text = 'Un coffre de facture humaine, plus frêle que ceux des nains.',
                    extra = '" . self::HUMAN_EXTRA . "'"
        );
        $this->addSql('INSERT INTO items SELECT * FROM tmp_coffre_humain');
        $this->addSql('DROP TEMPORARY TABLE IF EXISTS tmp_coffre_humain');

        foreach (self::LIFE as $name => $durability) {
            $this->addSql("UPDATE items SET durability_max = {$durability} WHERE name = '{$name}'");
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM items WHERE name = 'coffre_humain'");

        /* Back to the seeder's default; the races kept their own pv. */
        $this->addSql("UPDATE items SET durability_max = 100 WHERE name LIKE 'coffre%'");
    }
}
