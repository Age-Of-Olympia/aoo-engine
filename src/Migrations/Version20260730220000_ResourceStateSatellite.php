<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Where a resource's own state lives: the `resources` satellite.
 *
 * Arbitrated: an exhausted resource STAYS. It keeps barring the way, it stays
 * on screen, and the cron regrows it in place — today's behaviour, and the one
 * that keeps the entity's identity, so its logs and its cell survive a cycle.
 * Deleting and recreating it is what erased an anvil's history earlier in this
 * chantier.
 *
 * A satellite rather than a column on `players`: the same shape as `buildings`
 * and `unique_objects`, so a family's own data stays out of the shared table.
 *
 * `exhausted_at` rather than a flag: when a vein ran dry is worth knowing —
 * a regrowth rate is a probability per cron pass, so "how long has this been
 * dry" is the only way to tell bad luck from a broken rate.
 */
final class Version20260730220000_ResourceStateSatellite extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'resources satellite: a resource entity carries its exhausted state';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE IF NOT EXISTS resources (
                player_id INT NOT NULL,
                exhausted_at DATETIME NULL,
                PRIMARY KEY (player_id),
                KEY k_exhausted (exhausted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
        );

        /* No foreign key on `players`: `fk_buildings_player` has none either,
         * and the services unpick satellites by hand everywhere — a key here
         * would refuse the deletions the rest of the code performs. */
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS resources');
    }
}
