<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The ranks govern the faction's assets.
 *
 * Two flags join the charter: driveBuilding (take the commands of the
 * faction's playable buildings) and useChest (see inside, use and lock
 * its containers). The top rank of every faction receives both —
 * idempotent, and a ladder the admin already tuned only gains.
 */
final class Version20260804200000_RanksGovernTheFactionsAssets extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'faction_roles.driveBuilding / useChest, accordés au plus haut rang';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE faction_roles ADD COLUMN IF NOT EXISTS driveBuilding TINYINT(1) NOT NULL DEFAULT 0'
        );
        $this->addSql(
            'ALTER TABLE faction_roles ADD COLUMN IF NOT EXISTS useChest TINYINT(1) NOT NULL DEFAULT 0'
        );
        $this->addSql(
            'UPDATE faction_roles fr
               JOIN (SELECT faction_id, MAX(position) AS top FROM faction_roles GROUP BY faction_id) t
                 ON t.faction_id = fr.faction_id AND fr.position = t.top
                SET fr.driveBuilding = 1, fr.useChest = 1'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE faction_roles DROP COLUMN IF EXISTS driveBuilding');
        $this->addSql('ALTER TABLE faction_roles DROP COLUMN IF EXISTS useChest');
    }
}
