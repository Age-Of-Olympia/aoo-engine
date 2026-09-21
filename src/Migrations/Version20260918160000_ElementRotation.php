<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260918160000_ElementRotation extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'map_elements.rotation : un élément se pose tourné (0, 90, 180, 270), une seule image par sens';
    }

    public function up(Schema $schema): void
    {
        if (!$this->columnExists('map_elements', 'rotation')) {
            $this->addSql('ALTER TABLE map_elements ADD rotation SMALLINT NOT NULL DEFAULT 0');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE map_elements DROP COLUMN IF EXISTS rotation');
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
