<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Every row of `players` gets the `newHud` option, whatever its type: an
 * admin can drive a PNJ or a building, and the legacy layout is going away.
 * Version20260918100000 covered real characters only.
 *
 * Idempotent: only rows without the option are inserted.
 */
final class Version20260918140000_NewHudForEveryCharacter extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'newHud option for every character, PNJ and entity included';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "INSERT INTO players_options (player_id, name)
             SELECT p.id, 'newHud'
             FROM players p
             WHERE NOT EXISTS (
                 SELECT 1 FROM players_options o
                 WHERE o.player_id = p.id AND o.name = 'newHud'
             )"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM players_options WHERE name = 'newHud'");
    }
}
