<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A faction with a bank on a plan chooses the floors where its members
 * may place chests. Stored as the floors it CLOSES: no row means the
 * admin's plan_z_levels.chests_allowed alone decides, as before.
 */
final class Version20260927200000_AFactionChoosesWhereItsChestsStand extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'faction_closed_chest_floors — les niveaux où une faction interdit les coffres à ses membres';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE IF NOT EXISTS faction_closed_chest_floors (
                faction_id INT NOT NULL,
                plan VARCHAR(255) NOT NULL,
                z INT NOT NULL,
                PRIMARY KEY (faction_id, plan, z),
                CONSTRAINT fk_faction_closed_chest_floors_faction FOREIGN KEY (faction_id)
                    REFERENCES factions (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS faction_closed_chest_floors');
    }
}
