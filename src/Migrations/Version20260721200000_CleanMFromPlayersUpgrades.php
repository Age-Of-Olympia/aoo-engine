<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Remove obsolete 'm' upgrades from players_upgrades table.
 */
final class Version20260721200000_CleanMFromPlayersUpgrades extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Delete all rows from players_upgrades where name is m';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("DELETE FROM players_upgrades WHERE name = 'm'");
    }

    public function down(Schema $schema): void
    {
        // Note: The deleted upgrade rows cannot be automatically restored upon rollback,
        // so down() remains a no-op.
    }
}