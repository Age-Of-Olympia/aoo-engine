<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * What entering and leaving a biome does: a message each way, and an
 * effect put on entry (turns NULL = no end), ended on exit or left to run
 * out. Set on the type; a biome's own values, when set, replace the type's
 * (enter_effect NULL = the type's, '' = none).
 */
final class Version20261108190000_BiomeCrossings extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'biomes : message et effet à l\'entrée et à la sortie';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE biome_types
            ADD COLUMN IF NOT EXISTS enter_message TEXT NULL,
            ADD COLUMN IF NOT EXISTS exit_message TEXT NULL,
            ADD COLUMN IF NOT EXISTS enter_effect VARCHAR(64) NULL,
            ADD COLUMN IF NOT EXISTS enter_effect_turns INT NULL,
            ADD COLUMN IF NOT EXISTS enter_effect_value INT NOT NULL DEFAULT 1,
            ADD COLUMN IF NOT EXISTS enter_effect_ends_on_exit TINYINT(1) NOT NULL DEFAULT 1');
        $this->addSql('ALTER TABLE biomes
            ADD COLUMN IF NOT EXISTS enter_message TEXT NULL,
            ADD COLUMN IF NOT EXISTS exit_message TEXT NULL,
            ADD COLUMN IF NOT EXISTS enter_effect VARCHAR(64) NULL,
            ADD COLUMN IF NOT EXISTS enter_effect_turns INT NULL,
            ADD COLUMN IF NOT EXISTS enter_effect_value INT NULL,
            ADD COLUMN IF NOT EXISTS enter_effect_ends_on_exit TINYINT(1) NULL');
    }

    public function down(Schema $schema): void
    {
        foreach (['biome_types', 'biomes'] as $table) {
            $this->addSql("ALTER TABLE {$table}
                DROP COLUMN IF EXISTS enter_message,
                DROP COLUMN IF EXISTS exit_message,
                DROP COLUMN IF EXISTS enter_effect,
                DROP COLUMN IF EXISTS enter_effect_turns,
                DROP COLUMN IF EXISTS enter_effect_value,
                DROP COLUMN IF EXISTS enter_effect_ends_on_exit");
        }
    }
}
