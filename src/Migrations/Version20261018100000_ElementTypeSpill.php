<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261018100000_ElementTypeSpill extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'element_types.lifetime_turns et spill_chance : durée au sol et chance de couler d\'un élément versé par une blessure';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE element_types ADD COLUMN IF NOT EXISTS lifetime_turns SMALLINT NOT NULL DEFAULT 2');
        $this->addSql('ALTER TABLE element_types ADD COLUMN IF NOT EXISTS spill_chance TINYINT UNSIGNED NOT NULL DEFAULT 100');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE element_types DROP COLUMN IF EXISTS spill_chance');
        $this->addSql('ALTER TABLE element_types DROP COLUMN IF EXISTS lifetime_turns');
    }
}
