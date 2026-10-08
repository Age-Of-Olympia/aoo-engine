<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261008120000_ItemsXp extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'items.xp : XP (et PI) donnés par un consommable';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE items ADD COLUMN IF NOT EXISTS xp INT NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE items DROP COLUMN IF EXISTS xp');
    }
}
