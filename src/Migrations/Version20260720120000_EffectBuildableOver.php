<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Constructible par-dessus (revue du 2026-07-20) : un élément au sol
 * bloquait TOUTE construction et tout aménagement de sa case — y
 * compris une simple trace de pas. Chaque effet décide désormais
 * (effects.buildable_over) : faux par défaut (feu, lave, ronce…), vrai
 * pour les salissures — sang, boue, traces de pas. Réglable dans
 * admin → Effets. Idempotente.
 */
final class Version20260720120000_EffectBuildableOver extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'effects.buildable_over — sang, boue et traces laissent construire';
    }

    public function up(Schema $schema): void
    {
        $this->connection->executeStatement(
            'ALTER TABLE effects
             ADD COLUMN IF NOT EXISTS buildable_over TINYINT(1) NOT NULL DEFAULT 0'
        );
        $this->connection->executeStatement(
            "UPDATE effects SET buildable_over = 1
             WHERE name IN ('sang', 'boue') OR name LIKE 'trace\\_pas%'"
        );
    }

    public function down(Schema $schema): void
    {
        $this->connection->executeStatement(
            'ALTER TABLE effects DROP COLUMN IF EXISTS buildable_over'
        );
    }
}
