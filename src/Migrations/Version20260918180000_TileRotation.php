<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260918180000_TileRotation extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'map_tiles.rotation : une tuile de sol se pose tournée (0, 90, 180, 270), comme un élément';
    }

    public function up(Schema $schema): void
    {
        if (!$this->columnExists('map_tiles', 'rotation')) {
            $this->addSql('ALTER TABLE map_tiles ADD rotation SMALLINT NOT NULL DEFAULT 0');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE map_tiles DROP COLUMN IF EXISTS rotation');
    }

    private function columnExists(string $table, string $column): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );
    }
}
