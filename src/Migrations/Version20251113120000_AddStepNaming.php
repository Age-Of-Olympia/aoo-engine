<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add step_id and next_step columns to tutorial_configurations
 * This refactoring changes from numeric ordering to named steps with explicit next_step references
 */
final class Version20251113120000_AddStepNaming extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add step_id and next_step columns to tutorial_configurations for better maintainability';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            ALTER TABLE tutorial_configurations
            ADD COLUMN step_id VARCHAR(50) NULL AFTER version,
            ADD COLUMN next_step VARCHAR(50) NULL AFTER step_id
        ');

        $this->addSql('
            CREATE INDEX idx_tutorial_step_id ON tutorial_configurations(version, step_id)
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_tutorial_step_id ON tutorial_configurations');
        $this->addSql('
            ALTER TABLE tutorial_configurations
            DROP COLUMN next_step,
            DROP COLUMN step_id
        ');
    }
}
