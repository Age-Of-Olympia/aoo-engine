<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Move the two per-item rate tables of config/constants.php into the
 * items catalog, where each keyed a row of `items` by name:
 *
 * - LOOT_CHANCE → items.lootChance : the column existed (per-item custom
 *   chance, admin-editable) but the catalog defaults still lived in the
 *   constant. Player::death() now reads the column only; entries whose
 *   item does not exist in this install were dead config (an item absent
 *   from `items` can never sit in players_items) and are skipped.
 * - GROW_RATE → items.grow_rate (new column) : regrowth denominator of
 *   plant items (1 chance in N per grow trigger, PlantsService).
 *   NULL/0 = does not regrow, same behaviour as the unknown-plant case.
 *
 * Idempotent: UPDATEs only fill rows still at their empty default, so
 * re-running never clobbers admin-edited values.
 */
final class Version20260723121000_ItemRatesFromConstants extends AbstractMigration
{
    /** Snapshot of LOOT_CHANCE (the source is deleted with this migration).
     * @var array<string, int> */
    private const LOOT_CHANCE = [
        'or' => 200,
        'anneau_caprice' => 200,
        'anneau_ferocite' => 200,
        'anneau_finesse' => 200,
        'anneau_horizon' => 200,
        'anneau_pretention' => 200,
        'anneau_puissance' => 200,
        'anneau_souplesse' => 200,
        'anneau_tenacite' => 200,
        'bois_petrifie' => 50,
        'cuivre' => 50,
        'cendre' => 50,
        'fer' => 50,
        'tourbe' => 50,
        'cuir' => 80,
        'etain' => 80,
        'nickel' => 80,
        'pierre_mana' => 80,
        'salpetre' => 80,
        'emeraude' => 100,
        'lapis_lazuli' => 100,
        'opale' => 100,
        'rubis' => 100,
        'plume_doree' => 100,
        'plume_irisee' => 100,
        'plume_ebenne' => 100,
        'morceau_de_carte' => 100,
        'carte_recomposee' => 100,
    ];

    /** Snapshot of GROW_RATE (the source is deleted with this migration).
     * @var array<string, int> */
    private const GROW_RATE = [
        'adonis' => 2,
        'astral' => 10,
        'cafe' => 3,
        'houblon' => 3,
        'lichen_sacre' => 7,
        'lotus_noir' => 20,
        'menthe' => 7,
        'pavot' => 7,
    ];

    public function getDescription(): string
    {
        return 'Move LOOT_CHANCE and GROW_RATE into items.lootChance / items.grow_rate';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE items ADD COLUMN IF NOT EXISTS grow_rate INT DEFAULT NULL');

        foreach (self::LOOT_CHANCE as $name => $chance) {
            $this->addSql(
                'UPDATE items SET lootChance = ?
                 WHERE name = ? AND (lootChance IS NULL OR lootChance = 0)',
                [$chance, $name]
            );
        }

        foreach (self::GROW_RATE as $name => $rate) {
            $this->addSql(
                'UPDATE items SET grow_rate = ? WHERE name = ? AND grow_rate IS NULL',
                [$rate, $name]
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE items DROP COLUMN IF EXISTS grow_rate');
    }
}
