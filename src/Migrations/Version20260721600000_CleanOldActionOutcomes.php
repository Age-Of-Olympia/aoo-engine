<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Remove action outcomes linked to deprecated action IDs.
 */
final class Version20260721600000_CleanOldActionOutcomes extends AbstractMigration
{
    private const DEPRECATED_IDS = [
        4, 7, 9, 10, 15, 19, 20, 21, 23, 24, 25, 26, 27, 28, 29, 30, 
        32, 33, 34, 35, 36, 37, 38, 39, 40, 41, 42, 43, 44, 45, 46, 
        47, 48, 49, 52, 53, 54, 84, 85, 86
    ];

    public function getDescription(): string
    {
        return 'Delete rows from action_outcomes matching deprecated IDs';
    }

    public function up(Schema $schema): void
    {
        $placeholders = implode(', ', array_fill(0, count(self::DEPRECATED_IDS), '?'));
        
        $this->addSql(
            "DELETE FROM action_outcomes WHERE id IN ({$placeholders})",
            self::DEPRECATED_IDS
        );
    }

    public function down(Schema $schema): void
    {
        // Note: Deleted action outcomes rows cannot be automatically restored.
    }
}