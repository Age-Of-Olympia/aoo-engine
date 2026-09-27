<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260928090000_ElementTypeLabel extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'element_types.label : nom affiché au joueur (vide = le code)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE element_types ADD COLUMN IF NOT EXISTS label VARCHAR(100) NOT NULL DEFAULT ''");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE element_types DROP COLUMN IF EXISTS label');
    }
}
