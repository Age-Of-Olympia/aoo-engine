<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Apply playtest-driven UX polish to walk-related tutorial steps so
 * environments that already ran Version20251127000000 land on the
 * same state fresh installs do.
 *
 * Changes:
 *   - movement_intro / first_move: target_selector now points at the
 *     player avatar with a 50px highlight padding (one-tile ring),
 *     replacing the noisy 8-individual-tile highlights from
 *     `.case.go`. The legacy `.case.go` row in tutorial_step_highlights
 *     is also dropped.
 *   - deplete_movements / walk_to_tree / walk_to_enemy: add a player
 *     ring (#current-player-avatar with padding 50) as an extra
 *     highlight so the player can see where they can walk *from* in
 *     addition to the destination target.
 *
 * All updates are scoped on prior values (or step_id) so admin-set
 * overrides are preserved. Idempotent: re-running on top of itself
 * is a no-op.
 */
final class Version20260430200000_TutorialUxPolishWalkSteps extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Tutorial UX polish: player ring on walk steps + drop redundant .case.go highlight";
    }

    public function up(Schema $schema): void
    {
        // movement_intro + first_move: point target at the player avatar
        // and bump padding to 50, only when still on the legacy values.
        $this->addSql("
            UPDATE tutorial_step_ui ui
            JOIN tutorial_steps ts ON ts.id = ui.step_id
            SET ui.target_selector = '#current-player-avatar',
                ui.highlight_padding = 50
            WHERE ts.version = '1.0.0'
              AND ts.step_id IN ('movement_intro', 'first_move')
              AND ui.target_selector = '.case.go'
              AND ui.highlight_padding = 0
        ");

        // Drop the legacy .case.go row from movement_intro highlights —
        // the player ring (set as target above) replaces it.
        $this->addSql("
            DELETE h FROM tutorial_step_highlights h
            JOIN tutorial_steps ts ON ts.id = h.step_id
            WHERE ts.version = '1.0.0'
              AND ts.step_id = 'movement_intro'
              AND h.selector = '.case.go'
        ");

        // Add a player ring (padding 50) on each walk-related step.
        // INSERT IGNORE keeps it idempotent — if a hand-curated row
        // already exists for the same (step_id, selector) it stays put.
        // (Table has no UNIQUE on (step_id,selector), so use NOT EXISTS.)
        foreach (['deplete_movements', 'walk_to_tree', 'walk_to_enemy'] as $stepId) {
            $this->addSql("
                INSERT INTO tutorial_step_highlights (step_id, selector, padding)
                SELECT ts.id, '#current-player-avatar', 50
                FROM tutorial_steps ts
                WHERE ts.version = '1.0.0'
                  AND ts.step_id = ?
                  AND NOT EXISTS (
                      SELECT 1 FROM tutorial_step_highlights h
                      WHERE h.step_id = ts.id
                        AND h.selector = '#current-player-avatar'
                  )
            ", [$stepId]);
        }
    }

    public function down(Schema $schema): void
    {
        // Drop the player rings we added.
        $this->addSql("
            DELETE h FROM tutorial_step_highlights h
            JOIN tutorial_steps ts ON ts.id = h.step_id
            WHERE ts.version = '1.0.0'
              AND ts.step_id IN ('deplete_movements', 'walk_to_tree', 'walk_to_enemy')
              AND h.selector = '#current-player-avatar'
              AND h.padding = 50
        ");

        // Restore the legacy .case.go highlight on movement_intro.
        $this->addSql("
            INSERT INTO tutorial_step_highlights (step_id, selector, padding)
            SELECT ts.id, '.case.go', 0
            FROM tutorial_steps ts
            WHERE ts.version = '1.0.0'
              AND ts.step_id = 'movement_intro'
              AND NOT EXISTS (
                  SELECT 1 FROM tutorial_step_highlights h
                  WHERE h.step_id = ts.id AND h.selector = '.case.go'
              )
        ");

        // Revert movement_intro / first_move target back to .case.go
        // only when still on our polished values.
        $this->addSql("
            UPDATE tutorial_step_ui ui
            JOIN tutorial_steps ts ON ts.id = ui.step_id
            SET ui.target_selector = '.case.go',
                ui.highlight_padding = 0
            WHERE ts.version = '1.0.0'
              AND ts.step_id IN ('movement_intro', 'first_move')
              AND ui.target_selector = '#current-player-avatar'
              AND ui.highlight_padding = 50
        ");
    }
}
