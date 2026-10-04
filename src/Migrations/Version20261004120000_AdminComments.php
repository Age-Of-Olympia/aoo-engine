<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Admin-only notes on plans and on entity types (structures, resources…).
 */
final class Version20261004120000_AdminComments extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Commentaire admin sur les plans et les types';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plans ADD COLUMN IF NOT EXISTS comment TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE races ADD COLUMN IF NOT EXISTS comment TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plans DROP COLUMN IF EXISTS comment');
        $this->addSql('ALTER TABLE races DROP COLUMN IF EXISTS comment');
    }
}
