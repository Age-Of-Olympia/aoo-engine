<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251126183645 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add value column to players_effects table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE players_effects ADD COLUMN value INT(11) NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE players_effects DROP COLUMN value');
    }
}
