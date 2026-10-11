<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261011130000_ElementTypeOpacity extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'element_types.opacity : opacité du type sur la carte, en % (NULL = 50 % pour un webp, 100 % sinon)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE element_types ADD COLUMN IF NOT EXISTS opacity TINYINT UNSIGNED DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE element_types DROP COLUMN IF EXISTS opacity');
    }
}
