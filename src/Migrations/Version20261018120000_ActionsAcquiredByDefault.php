<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * actions.innate: an action every character knows without a players_actions
 * row (« acquis d'office »). It never counts toward the competence cap; its
 * conditions alone decide where its button shows. The chest gestures are
 * the first ones.
 *
 * Backward-compatible: the per-character rows of these gestures stay, the
 * previous code keeps reading them. Idempotent.
 */
final class Version20261018120000_ActionsAcquiredByDefault extends AbstractMigration
{
    public function getDescription(): string
    {
        return "actions.innate : actions acquises d'office (ouvrir, fermer, ecrire)";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE actions ADD COLUMN IF NOT EXISTS innate TINYINT(1) NOT NULL DEFAULT 0');
        $this->addSql("UPDATE actions SET innate = 1 WHERE name IN ('ouvrir', 'fermer', 'ecrire')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE actions DROP COLUMN IF EXISTS innate');
    }
}
