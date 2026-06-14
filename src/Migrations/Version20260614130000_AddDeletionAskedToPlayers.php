<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add the `deletion_asked` column to the `players` table.
 *
 * The account options screen has long exposed a "Demander la suppression
 * du compte" toggle, but it only ever stored a `deleteAccount` row in
 * `players_options` — nothing read it, so the request was silently lost.
 * This column records WHEN a player asked for deletion so the admin team
 * has a queryable list to action within the promised 7-day window.
 *
 * NULL = no pending request. A timestamp = deletion requested at that time.
 *
 * Idempotent: MariaDB 10.2+ supports ADD/DROP COLUMN IF [NOT] EXISTS.
 */
final class Version20260614130000_AddDeletionAskedToPlayers extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add players.deletion_asked column (records account deletion requests)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE `players` '
            . 'ADD COLUMN IF NOT EXISTS `deletion_asked` DATETIME DEFAULT NULL'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `players` DROP COLUMN IF EXISTS `deletion_asked`');
    }
}
