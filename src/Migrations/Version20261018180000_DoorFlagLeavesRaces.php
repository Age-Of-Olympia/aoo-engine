<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Drops races.opens_the_way, replaced by structure_nature 'porte'
 * (Version20260927210000_ADoorIsItsOwnNature). The release before this one
 * unmapped it, so no deployed code reads it. Idempotent; down() restores the
 * column empty.
 */
final class Version20261018180000_DoorFlagLeavesRaces extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'suppression de races.opens_the_way, remplacée par la nature porte';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE races DROP COLUMN IF EXISTS opens_the_way');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE races ADD COLUMN IF NOT EXISTS opens_the_way TINYINT(1) NOT NULL DEFAULT 0');
    }
}
