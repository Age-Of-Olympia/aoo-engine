<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * What a resource type yields, PER PLAN.
 *
 * The yield is not a property of the type: measured on the real plan files,
 * 28 of the 39 harvestable types give something different depending on where
 * they stand — a `pierre1` has three sets of rates across the world. A
 * catalogue keyed on the type alone cannot say that, which is why the rates
 * still live in 53 plan JSONs today.
 *
 * Keyed on (plan, race_id) so the world stays representable, and empty on
 * arrival: it is filled by console command, never by a migration. A migration
 * runs from the git checkout, where `datas/` is absent — the races seed was
 * burnt by exactly that.
 */
final class Version20260730160000_RaceHarvestTable extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'race_harvest: what a resource type yields on a given plan';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "CREATE TABLE IF NOT EXISTS race_harvest (
                plan VARCHAR(255) NOT NULL,
                race_id INT NOT NULL,
                item VARCHAR(255) NOT NULL,
                exhaust SMALLINT NULL,
                regrow SMALLINT NULL,
                PRIMARY KEY (plan, race_id),
                KEY k_race (race_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );

        /* No foreign key on `races`: the pour resolves types by NAME, and the
         * three name columns disagree on collation across databases — a key
         * would refuse rows the game accepts. The pour reports what it cannot
         * resolve instead. */
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS race_harvest');
    }
}
