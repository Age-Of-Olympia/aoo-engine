<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Remove actions linked to deprecated IDs.
 */
final class Version20260721800000_CleanOldActions extends AbstractMigration
{
    private const DEPRECATED_IDS = [
        3, 4, 5, 6, 10, 14, 15, 16, 18, 19, 20, 21, 22, 23, 24, 
        25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36, 37, 38, 
        39, 40, 41, 42, 45, 76
    ];

    public function getDescription(): string
    {
        return 'Delete rows from actions matching deprecated IDs';
    }

    public function up(Schema $schema): void
    {
        $placeholders = implode(', ', array_fill(0, count(self::DEPRECATED_IDS), '?'));
        
        $this->addSql(
            "DELETE FROM actions WHERE id IN ({$placeholders})",
            self::DEPRECATED_IDS
        );
    }

    public function down(Schema $schema): void
    {
        // Note: Deleted actions rows cannot be automatically restored.
    }
}