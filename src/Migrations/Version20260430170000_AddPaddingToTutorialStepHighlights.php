<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add per-row `padding` to the 1:N tutorial_step_highlights table.
 *
 * Step-level `tutorial_step_ui.highlight_padding` only fits the main
 * target. Additional highlights (e.g. a chunky 50px ring around the
 * player avatar to expose the 8 surrounding tiles) needed their own
 * padding so a single noisy ring doesn't force the same ring on a
 * tight counter highlight on the same step.
 *
 * IF NOT EXISTS keeps it idempotent.
 */
final class Version20260430170000_AddPaddingToTutorialStepHighlights extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Add tutorial_step_highlights.padding for per-row highlight padding";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            ALTER TABLE tutorial_step_highlights
            ADD COLUMN IF NOT EXISTS padding INT DEFAULT 0
            COMMENT 'Extra px around this highlight (independent of the step-level target padding)'
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE tutorial_step_highlights DROP COLUMN IF EXISTS padding");
    }
}
