<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260927120000_ElementTypeFluid extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'element_types.fluid : le type se fond avec ses voisins (bords, coudes) ou reste case par case';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE element_types ADD COLUMN IF NOT EXISTS fluid TINYINT(1) NOT NULL DEFAULT 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE element_types DROP COLUMN IF EXISTS fluid');
    }
}
