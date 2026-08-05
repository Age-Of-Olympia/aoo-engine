<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * What breaks may VANISH: items.vanish_on_break — a destroyed placed
 * exemplar spills its loot (character rules), empties its hidden
 * slots, then erases itself instead of lying broken on the tile.
 * Configurable per item on its admin sheet; the chests start with it.
 */
final class Version20260805230000_WhatBreaksMayVanish extends AbstractMigration
{
    public function getDescription(): string
    {
        return "items.vanish_on_break — brisé, l'objet répand son butin puis s'efface";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE items ADD COLUMN IF NOT EXISTS vanish_on_break TINYINT(1) NOT NULL DEFAULT 0');
        $this->addSql("UPDATE items SET vanish_on_break = 1 WHERE name LIKE 'coffre%'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE items DROP COLUMN IF EXISTS vanish_on_break');
    }
}
