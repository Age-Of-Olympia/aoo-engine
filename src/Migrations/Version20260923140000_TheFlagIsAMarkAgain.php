<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Flags and footsteps laid as elements since Version20260914120000 go
 * back to the marks: a stale copy of their images in img/elements kept
 * offering them as elements. Same statements as that migration,
 * idempotent.
 */
final class Version20260923140000_TheFlagIsAMarkAgain extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'déplace vers map_marks les drapeaux et traces de pas posés comme éléments';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "INSERT IGNORE INTO map_marks (name, coords_id, endTime)
             SELECT name, coords_id, endTime FROM map_elements
              WHERE name LIKE 'trace\\_pas%' OR name = 'flag_red'"
        );
        $this->addSql("DELETE FROM map_elements WHERE name LIKE 'trace\\_pas%' OR name = 'flag_red'");
        $this->addSql("DELETE FROM players_effects WHERE name LIKE 'trace\\_pas%' OR name = 'flag_red'");
    }

    public function down(Schema $schema): void
    {
        // Those rows were never meant to be elements: nothing to restore
    }
}
