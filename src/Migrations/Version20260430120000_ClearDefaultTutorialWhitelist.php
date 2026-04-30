<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Clear the seeded `whitelisted_players='1,2,3'` row that the original
 * tutorial-system migration left behind. In prod that whitelist would
 * silently grant the first three real player IDs early tutorial access,
 * which is not the intended rollout gate.
 *
 * Idempotent + non-destructive: only resets the value when it still
 * matches the exact seeded default. An admin-set whitelist (anything
 * other than '1,2,3') is left alone.
 */
final class Version20260430120000_ClearDefaultTutorialWhitelist extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Clear default '1,2,3' tutorial whitelist seeded by Version20251127000000";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            UPDATE tutorial_settings
            SET setting_value = ''
            WHERE setting_key = 'whitelisted_players'
              AND setting_value = '1,2,3'
        ");
    }

    public function down(Schema $schema): void
    {
        // Intentional no-op. Restoring '1,2,3' would re-introduce the
        // accidental access grant this migration exists to remove.
    }
}
