<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A BAG has its lines too: the chest-capacity concept, lifted to the
 * race — races.capacity says how many content lines its characters
 * carry (a stack of one item = a line, an exemplar = a line; gold and
 * what is equipped count for nothing). 0 = unlimited, which every
 * structure type keeps; character races start at 10, tunable in the
 * race's caracs form.
 */
final class Version20260805210000_ABagHasItsLines extends AbstractMigration
{
    public function getDescription(): string
    {
        return "races.capacity — la contenance du sac, en lignes, réglée par le peuple";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE races ADD COLUMN IF NOT EXISTS capacity INT NOT NULL DEFAULT 0');
        // Only where unset: an admin's own tuning is never overwritten.
        $this->addSql("UPDATE races SET capacity = 10 WHERE kind = 'character' AND capacity = 0");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE races DROP COLUMN IF EXISTS capacity');
    }
}
