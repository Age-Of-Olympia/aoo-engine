<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Admin rights are read from the login, never from a PNJ. Each right a PNJ
 * carries moves to the real players owning that PNJ, then leaves the PNJ.
 */
final class Version20261025100000_NoAdminRightsOnPnjs extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Passe isAdmin / isSuperAdmin des PNJ à leurs propriétaires';
    }

    public function up(Schema $schema): void
    {
        // NOT EXISTS: players_options has no unique key, a replay must not duplicate.
        $this->addSql("INSERT INTO players_options (player_id, name)
            SELECT DISTINCT pp.player_id, o.name
            FROM players_options o
            JOIN players_pnjs pp ON pp.pnj_id = o.player_id
            WHERE o.player_id < 0 AND pp.player_id > 0
              AND o.name IN ('isAdmin', 'isSuperAdmin')
              AND NOT EXISTS (SELECT 1 FROM players_options x WHERE x.player_id = pp.player_id AND x.name = o.name)");
        $this->addSql("DELETE FROM players_options WHERE player_id < 0 AND name IN ('isAdmin', 'isSuperAdmin')");
    }

    public function down(Schema $schema): void
    {
        // The removed rows are not kept: nothing to restore.
    }
}
