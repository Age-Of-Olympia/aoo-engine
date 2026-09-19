<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260919140000_TheBagIsACarac extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'items.sac: the bag lines an equipped item lends (the race column keeps its name, capacity)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE items ADD COLUMN IF NOT EXISTS sac INT NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE items DROP COLUMN IF EXISTS sac');
    }
}
