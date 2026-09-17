<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Every real player gets the `newHud` option: the redesigned layout is the
 * season's interface, the legacy one stays reachable by removing the option
 * from the account page. New accounts get it from Player::put_player.
 *
 * Idempotent: only players without the row are inserted.
 */
final class Version20260918100000_NewHudForEveryone extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'newHud option for every real player';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "INSERT INTO players_options (player_id, name)
             SELECT p.id, 'newHud'
             FROM players p
             WHERE p.player_type = 'real'
               AND NOT EXISTS (
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
