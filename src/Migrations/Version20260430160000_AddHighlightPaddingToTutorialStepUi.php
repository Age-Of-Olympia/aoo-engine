<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add `tutorial_step_ui.highlight_padding` so admins can extend the
 * highlight + spotlight cut-out outward from the target element.
 *
 * Used to expose surrounding context the player needs (e.g. the 8
 * walkable tiles around the player avatar on a "use all your moves"
 * step). Source migration Version20251127000000 ships the column on
 * fresh installs; this one adds it to environments that already ran
 * the original. IF NOT EXISTS keeps it idempotent.
 */
final class Version20260430160000_AddHighlightPaddingToTutorialStepUi extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Add tutorial_step_ui.highlight_padding";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            ALTER TABLE tutorial_step_ui
            ADD COLUMN IF NOT EXISTS highlight_padding INT DEFAULT 0
            COMMENT 'Extra px around the highlight box and spotlight cut-out'
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE tutorial_step_ui DROP COLUMN IF EXISTS highlight_padding");
    }
}
