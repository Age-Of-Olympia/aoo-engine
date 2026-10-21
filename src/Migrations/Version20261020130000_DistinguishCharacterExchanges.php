<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Marks exchanges opened directly between characters. */
final class Version20261020130000_DistinguishCharacterExchanges extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Distinguish character-to-character exchanges from Halle exchanges';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE items_exchanges ADD COLUMN IF NOT EXISTS exchange_type VARCHAR(16) NOT NULL DEFAULT 'merchant'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE items_exchanges DROP COLUMN IF EXISTS exchange_type');
    }
}
