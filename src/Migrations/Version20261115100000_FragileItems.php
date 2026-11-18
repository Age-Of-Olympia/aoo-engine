<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Adds the item flag for objects destroyed when their wear reaches zero. */
final class Version20261115100000_FragileItems extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le flag fragile aux objets';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE items ADD COLUMN IF NOT EXISTS fragile TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE items DROP COLUMN IF EXISTS fragile');
    }
}
