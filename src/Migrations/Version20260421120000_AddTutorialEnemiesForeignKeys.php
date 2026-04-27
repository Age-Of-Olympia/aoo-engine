<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add FKs to tutorial_enemies. Without them, deleting the enemy NPC's
 * players row leaves orphan tracking rows; deleting an enemy's coords
 * row breaks observation joins. Idempotent; safe to re-run.
 */
final class Version20260421120000_AddTutorialEnemiesForeignKeys extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add FKs tutorial_enemies(enemy_player_id) → players(id) CASCADE '
            . 'and tutorial_enemies(enemy_coords_id) → coords(id) RESTRICT';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        // Pre-scrub: any tutorial_enemies row whose enemy_player_id no
        // longer points at a players row would trip the FK validation.
        $this->addSql('
            DELETE te FROM tutorial_enemies te
            LEFT JOIN players p ON p.id = te.enemy_player_id
            WHERE p.id IS NULL
        ');

        // Same for orphan coords refs (RESTRICT FK would also refuse).
        $this->addSql('
            DELETE te FROM tutorial_enemies te
            LEFT JOIN coords c ON c.id = te.enemy_coords_id
            WHERE c.id IS NULL
        ');

        $this->addFkIfMissing(
            'fk_tutorial_enemies_player',
            "ALTER TABLE `tutorial_enemies` ADD CONSTRAINT `fk_tutorial_enemies_player` "
            . "FOREIGN KEY (`enemy_player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE"
        );

        $this->addFkIfMissing(
            'fk_tutorial_enemies_coords',
            "ALTER TABLE `tutorial_enemies` ADD CONSTRAINT `fk_tutorial_enemies_coords` "
            . "FOREIGN KEY (`enemy_coords_id`) REFERENCES `coords` (`id`) ON DELETE RESTRICT"
        );
    }

    public function down(Schema $schema): void
    {
        $this->dropFkIfPresent('fk_tutorial_enemies_player');
        $this->dropFkIfPresent('fk_tutorial_enemies_coords');
    }

    private function addFkIfMissing(string $constraintName, string $alterSql): void
    {
        if (!$this->fkExists('tutorial_enemies', $constraintName)) {
            $this->addSql($alterSql);
        }
    }

    private function dropFkIfPresent(string $constraintName): void
    {
        if ($this->fkExists('tutorial_enemies', $constraintName)) {
            $this->addSql("ALTER TABLE `tutorial_enemies` DROP FOREIGN KEY `{$constraintName}`");
        }
    }

    private function fkExists(string $table, string $constraintName): bool
    {
        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND CONSTRAINT_NAME = ?',
            [$table, $constraintName]
        );

        return (int) $count > 0;
    }
}
