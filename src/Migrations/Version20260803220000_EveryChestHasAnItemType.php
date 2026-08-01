<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Every standing chest gets a type in the items catalogue.
 *
 * A chest exists twice: an `items` row and a `races` row of kind structure.
 * Three of the four match — `coffre_humain` is a race only, so the one that
 * stands on the board has no item type to become. It gets its row here, cloned
 * from the wooden chest so no column is forgotten as the table grows; the
 * display name and the sprite come from `extra`, which is what the board reads.
 *
 * No recipe produces a chest and no bag holds one, so adding the row makes
 * nothing obtainable that was not — it gives the entity something to point at.
 *
 * The life is set here too, before anything converts, because converting is
 * what makes it visible. A built chest draws its max life from `races.pv`,
 * which reads 1 for wood, metal and petrified wood — one hit and the chest is
 * gone — while `items.durability_max` reads 100 for all three, the seeder's
 * default. Neither is a decision. The material now decides, which is the whole
 * point of a type carrying its own configuration.
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
        /* Cloned rather than spelled out: `items` carries some fifty columns
         * and a hand-written INSERT rots the day one is added. */
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
