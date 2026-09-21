<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260919180000_AnEffectHurtsWhenItLands extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'effects.pv_on_apply: PV change applied once, each time the effect lands (fire: -10)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE effects ADD COLUMN IF NOT EXISTS pv_on_apply INT NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE effects DROP COLUMN IF EXISTS pv_on_apply');
    }
}
