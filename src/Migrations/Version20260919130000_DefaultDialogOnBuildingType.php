<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260919130000_DefaultDialogOnBuildingType extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'races.default_dialog: the dialogue a new building of this type is born with';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE races ADD COLUMN IF NOT EXISTS default_dialog VARCHAR(100) NOT NULL DEFAULT ''");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE races DROP COLUMN IF EXISTS default_dialog');
    }
}
