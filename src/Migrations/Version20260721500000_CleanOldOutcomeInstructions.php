<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Remove outcome instructions linked to deprecated action IDs.
 */
final class Version20260721500000_CleanOldOutcomeInstructions extends AbstractMigration
{
    private const DEPRECATED_OUTCOME_IDS = [
        4, 7, 9, 10, 15, 19, 20, 21, 23, 24, 25, 26, 27, 28, 29, 30, 
        32, 33, 34, 35, 36, 37, 38, 39, 40, 41, 42, 43, 44, 45, 46, 
        47, 48, 49, 52, 53, 54, 84, 85, 86
    ];

    public function getDescription(): string
    {
        return 'Delete rows from outcome_instructions matching deprecated outcome IDs';
    }

    public function up(Schema $schema): void
    {
        $placeholders = implode(', ', array_fill(0, count(self::DEPRECATED_OUTCOME_IDS), '?'));
        
        $this->addSql(
            "DELETE FROM outcome_instructions WHERE outcome_id IN ({$placeholders})",
            self::DEPRECATED_OUTCOME_IDS
        );
    }

    public function down(Schema $schema): void
    {
        // Note: Deleted outcome instructions rows cannot be automatically restored.
    }
}