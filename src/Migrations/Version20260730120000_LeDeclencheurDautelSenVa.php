<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The altar trigger goes: the entity IS the altar.
 *
 * It carried the god, which is now a column on the entity. Nothing reads it
 * any more — the Faith ranking moved first, on purpose, so this deletion
 * happens with no one looking.
 *
 * `down()` rebuilds a trigger per consecrated altar. Not the same rows, which
 * are gone with their ids, but the same facts: a cell, a god.
 */
final class Version20260730120000_LeDeclencheurDautelSenVa extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Altar triggers are removed: the entity carries the god';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("DELETE FROM map_triggers WHERE name = 'altar'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "INSERT INTO map_triggers (coords_id, name, params)
             SELECT ec.coords_id, 'altar', CAST(p.godId AS CHAR)
               FROM players p
               JOIN entity_cells ec ON ec.player_id = p.id
              WHERE p.race = 'altar' AND p.godId != 0"
        );
    }
}
