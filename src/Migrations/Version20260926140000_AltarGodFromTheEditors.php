<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260926140000_AltarGodFromTheEditors extends AbstractMigration
{
    /** First extension release that reads the god of a building row. */
    private const TILED_MIN = '0.10.0';

    public function getDescription(): string
    {
        return 'gods already in use become prayable; the Tiled extension must read the god of an altar';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "INSERT INTO players_options (player_id, name)
             SELECT g.id, 'prayable'
               FROM players g
              WHERE g.race = 'dieu'
                AND (EXISTS (SELECT 1 FROM players a WHERE a.race = 'altar' AND a.godId = g.id)
                     OR EXISTS (SELECT 1 FROM players f WHERE f.godId = g.id AND f.race <> 'altar'))
                AND NOT EXISTS (
                    SELECT 1 FROM players_options o WHERE o.player_id = g.id AND o.name = 'prayable'
                )"
        );

        // An older extension drops the altars whose god it cannot show, then pushes the gap
        $current = (string) $this->connection->fetchOne(
            "SELECT value FROM admin_settings WHERE name = 'tiled_min_extension'"
        );
        if ($current === '' || version_compare(ltrim($current, 'vV'), self::TILED_MIN, '<')) {
            $this->addSql(
                "INSERT INTO admin_settings (name, value) VALUES ('tiled_min_extension', ?)
                 ON DUPLICATE KEY UPDATE value = VALUES(value)",
                [self::TILED_MIN]
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM players_options WHERE name = 'prayable'");
    }
}
