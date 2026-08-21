<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A floor says whether chests may be placed on it: one flag per z
 * level (plan_z_levels.chests_allowed, default yes — the flag only
 * ever forbids). Read by ChestSiteCondition on the `construire`
 * action, edited with the other z-level settings.
 */
final class Version20260821233000_AFloorSaysWhereChestsMayStand extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'plan_z_levels.chests_allowed — les niveaux où les coffres peuvent être posés';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE plan_z_levels ADD COLUMN IF NOT EXISTS chests_allowed TINYINT(1) NOT NULL DEFAULT 1'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plan_z_levels DROP COLUMN IF EXISTS chests_allowed');
    }
}
