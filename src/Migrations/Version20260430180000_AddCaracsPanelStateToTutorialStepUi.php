<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Per-step caracs-panel control. Some tutorial steps need the
 * Caractéristiques panel forced closed (e.g. introductory steps where
 * the player should discover and open it themselves) or forced open
 * (e.g. "look at your remaining MVT"). NULL = leave the player's
 * cookie-driven state untouched (default for legacy steps).
 *
 * IF NOT EXISTS keeps it idempotent. Source migration ships the
 * column on fresh installs.
 */
final class Version20260430180000_AddCaracsPanelStateToTutorialStepUi extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Add tutorial_step_ui.caracs_panel_state for per-step panel control";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            ALTER TABLE tutorial_step_ui
            ADD COLUMN IF NOT EXISTS caracs_panel_state ENUM('open', 'closed') NULL DEFAULT NULL
            COMMENT 'Force the caracs panel open/closed at step start; NULL = leave as-is'
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE tutorial_step_ui DROP COLUMN IF EXISTS caracs_panel_state");
    }
}
