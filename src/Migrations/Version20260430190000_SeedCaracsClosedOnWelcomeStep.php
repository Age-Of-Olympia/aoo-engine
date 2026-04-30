<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Seed caracs_panel_state='closed' on the welcome step (id=1.0,
 * step_id='welcome', version='1.0.0') so the panel does not flash on
 * top of the very first dialog. Players open it themselves later when
 * the show_characteristics step prompts them; the cookie persists from
 * there.
 *
 * Idempotent + scoped: only touches the welcome step row, only when
 * caracs_panel_state is still NULL (so a hand-curated value from
 * admin is preserved).
 */
final class Version20260430190000_SeedCaracsClosedOnWelcomeStep extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Force the welcome step to start with the caracs panel closed";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            UPDATE tutorial_step_ui ui
            JOIN tutorial_steps ts ON ts.id = ui.step_id
            SET ui.caracs_panel_state = 'closed'
            WHERE ts.step_id = 'welcome'
              AND ts.version = '1.0.0'
              AND ui.caracs_panel_state IS NULL
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("
            UPDATE tutorial_step_ui ui
            JOIN tutorial_steps ts ON ts.id = ui.step_id
            SET ui.caracs_panel_state = NULL
            WHERE ts.step_id = 'welcome'
              AND ts.version = '1.0.0'
              AND ui.caracs_panel_state = 'closed'
        ");
    }
}
