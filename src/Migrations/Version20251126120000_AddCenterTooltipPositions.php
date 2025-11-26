<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add center-top and center-bottom tooltip positions
 */
final class Version20251126120000_AddCenterTooltipPositions extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add center-top and center-bottom to tooltip_position ENUM in tutorial_step_ui table';
    }

    public function up(Schema $schema): void
    {
        // Add center-top and center-bottom to tooltip_position enum
        $this->addSql("
            ALTER TABLE tutorial_step_ui
            MODIFY COLUMN tooltip_position
            ENUM('top','bottom','left','right','center','center-top','center-bottom')
            DEFAULT 'bottom'
        ");
    }

    public function down(Schema $schema): void
    {
        // Revert to original enum values
        // WARNING: This will fail if any rows use center-top or center-bottom
        $this->addSql("
            ALTER TABLE tutorial_step_ui
            MODIFY COLUMN tooltip_position
            ENUM('top','bottom','left','right','center')
            DEFAULT 'bottom'
        ");
    }
}
