<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The repair bill lost its labour fee, and RepairService no longer reads
 * `repair_labour_share`. The row exists only where an admin saved the atelier
 * settings while the fee still existed.
 */
final class Version20260923100000_TheLabourFeeSettingIsGone extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Suppression du réglage repair_labour_share, plus lu par l'atelier";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("DELETE FROM admin_settings WHERE name = 'repair_labour_share'");
    }

    public function down(Schema $schema): void
    {
        // Nothing reads the value: there is nothing to restore
    }
}
