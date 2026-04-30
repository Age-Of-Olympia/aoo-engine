<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Reposition two tutorial tooltips per playtest:
 *   - tree_info ('Ressource récoltable'): the .resource-status indicator
 *     sits at the bottom of the screen, so a 'left' tooltip lands too
 *     low on small viewports. Place ABOVE.
 *   - inventory_wood ('Du bois !'): place UNDER the wood item-case
 *     tile so the tooltip doesn't cover other inventory slots on the
 *     left.
 *
 * Idempotent + scoped on the previous 'left' value so a hand-curated
 * position via the admin step editor is preserved.
 */
final class Version20260430230000_TweakTreeAndWoodTooltipPositions extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Move tree_info tooltip to 'top' and inventory_wood tooltip to 'bottom'";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            UPDATE tutorial_step_ui ui
            JOIN tutorial_steps ts ON ts.id = ui.step_id
            SET ui.tooltip_position = 'top'
            WHERE ts.step_id = 'tree_info'
              AND ts.version = '1.0.0'
              AND ui.tooltip_position = 'left'
        ");
        $this->addSql("
            UPDATE tutorial_step_ui ui
            JOIN tutorial_steps ts ON ts.id = ui.step_id
            SET ui.tooltip_position = 'bottom'
            WHERE ts.step_id = 'inventory_wood'
              AND ts.version = '1.0.0'
              AND ui.tooltip_position = 'left'
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("
            UPDATE tutorial_step_ui ui
            JOIN tutorial_steps ts ON ts.id = ui.step_id
            SET ui.tooltip_position = 'left'
            WHERE ts.step_id = 'tree_info'
              AND ts.version = '1.0.0'
              AND ui.tooltip_position = 'top'
        ");
        $this->addSql("
            UPDATE tutorial_step_ui ui
            JOIN tutorial_steps ts ON ts.id = ui.step_id
            SET ui.tooltip_position = 'left'
            WHERE ts.step_id = 'inventory_wood'
              AND ts.version = '1.0.0'
              AND ui.tooltip_position = 'bottom'
        ");
    }
}
