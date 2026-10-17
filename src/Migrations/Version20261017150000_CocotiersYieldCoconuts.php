<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Cocotiers are trees that yield coconuts, exhausted and regrowing like the
 * other trees. A yield already set from the admin is left alone.
 */
final class Version20261017150000_CocotiersYieldCoconuts extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'cocotier1-3 yield coco';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "UPDATE races SET harvest_item = 'coco', harvest_exhaust = 75, harvest_regrow = 20
              WHERE name IN ('cocotier1', 'cocotier2', 'cocotier3')
                AND (harvest_item IS NULL OR TRIM(harvest_item) = '')"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "UPDATE races SET harvest_item = NULL, harvest_exhaust = NULL, harvest_regrow = NULL
              WHERE name IN ('cocotier1', 'cocotier2', 'cocotier3') AND harvest_item = 'coco'"
        );
    }
}
