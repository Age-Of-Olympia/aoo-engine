<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * items.lootChance: NULL now means "the default chance" (LootRulesService)
 * and 0 means "never drops". Until now 0 meant the default, so an item could
 * not be made undroppable.
 */
final class Version20261118120000_ItemLootChanceDefaultIsNull extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'objets : chance de loot vide = règle par défaut, 0 = jamais';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE items MODIFY lootChance INT NULL DEFAULT NULL');
        $this->addSql('UPDATE items SET lootChance = NULL WHERE lootChance = 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('UPDATE items SET lootChance = 0 WHERE lootChance IS NULL');
        $this->addSql('ALTER TABLE items MODIFY lootChance INT NOT NULL DEFAULT 0');
    }
}
