<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A rank may bear two names — Roi / Reine.
 *
 * The pair lives on the ROLE (faction_roles.name_alt, '' = single name)
 * and displays joined wherever the rank is named. Players carry no
 * gender, so no per-member variant is picked yet: the day a member
 * preference exists, the display has both halves ready.
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
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE faction_roles DROP COLUMN IF EXISTS name_alt');
    }
}
