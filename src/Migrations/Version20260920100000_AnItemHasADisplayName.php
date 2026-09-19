<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The JSON→DB move of items kept the technical name only: "Épée de Casca"
 * stayed in datas/…/epee_casca.json, which the seeder skipped, and every
 * DB-sourced item shows its code. The column comes here; its content
 * comes from the seeder (admin → Objets → Seed JSON legacy), which runs
 * from the docroot where datas/ exists — a migration cannot read it.
 */
final class Version20260920100000_AnItemHasADisplayName extends AbstractMigration
{
    public function getDescription(): string
    {
        return "items.label: the name shown to players ('' = the technical name, capitalised)";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE items ADD COLUMN IF NOT EXISTS label VARCHAR(100) NOT NULL DEFAULT ''");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE items DROP COLUMN IF EXISTS label');
    }
}
