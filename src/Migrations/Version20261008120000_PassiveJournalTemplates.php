<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Journal lines of a passive: one public line when it takes effect during an
 * action, one personal line when a player learns it. NULL or empty = no line.
 * The public line starts empty: admins turn it on passive by passive. The
 * learn default is only written where still NULL, so a replay keeps edits.
 */
final class Version20261008120000_PassiveJournalTemplates extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'action_passives : textes de journal au déclenchement et à l\'apprentissage';
    }

    public function up(Schema $schema): void
    {
        // The table is latin1: the new columns carry their own charset for French text.
        $this->addSql('ALTER TABLE action_passives
            ADD COLUMN IF NOT EXISTS trigger_template TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS learn_template TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL');

        $this->addSql(
            'UPDATE action_passives SET learn_template = ? WHERE learn_template IS NULL',
            ['Vous avez appris {passive}.']
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE action_passives DROP COLUMN IF EXISTS trigger_template, DROP COLUMN IF EXISTS learn_template');
    }
}
