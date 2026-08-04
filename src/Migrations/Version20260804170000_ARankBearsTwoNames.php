<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A rank may bear two names — Roi / Reine.
 *
 * The pair lives on the ROLE (faction_roles.name_alt, '' = single name),
 * and each MEMBER carries which half titles them
 * (players.factionRoleVariant, 0 = name, 1 = name_alt) — chosen in the
 * rank list, where both halves are offered as if they were two ranks.
 */
final class Version20260804170000_ARankBearsTwoNames extends AbstractMigration
{
    public function getDescription(): string
    {
        return "faction_roles.name_alt : le second nom d'un rang (Roi / Reine)";
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "ALTER TABLE faction_roles ADD COLUMN IF NOT EXISTS name_alt VARCHAR(100) NOT NULL DEFAULT ''"
        );
        $this->addSql(
            'ALTER TABLE players ADD COLUMN IF NOT EXISTS factionRoleVariant TINYINT(1) NOT NULL DEFAULT 0'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE players DROP COLUMN IF EXISTS factionRoleVariant');
        $this->addSql('ALTER TABLE faction_roles DROP COLUMN IF EXISTS name_alt');
    }
}
