<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * An altar trigger that names no god is a broken pointer, not an altar.
 *
 * `map_triggers.params` carries the god an altar belongs to. One of them points
 * at Griffith — race `animal` — on a cell that bears nothing at all: no
 * resource, no decor, no entity. It has never counted in the Faith ranking,
 * which only looks at `race = 'dieu'`, and there is nothing on screen to
 * explain it.
 *
 * Deleted by the RULE rather than by id, because an id belongs to one database
 * and this has to hold on every one: an altar trigger whose `params` does not
 * name a god. On the production copy that is exactly one row out of sixteen —
 * the other fifteen name a god and are left alone.
 *
 * A naked altar is a different matter and stays: it is a legitimate object
 * waiting to be consecrated. What is removed here is a pointer to nothing.
 */
final class Version20260729160000_AnAltarWithoutAGodPointsAtNothing extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Altar triggers naming no god are removed — broken pointers, not altars';
    }

    public function up(Schema $schema): void
    {
        /* CAST of free text gives 0 in MySQL, which matches no player, so a
         * non-numeric `params` falls on the same side as a wrong id. */
        $this->addSql(
            "DELETE t FROM map_triggers t
              WHERE t.name = 'altar'
                AND NOT EXISTS (
                    SELECT 1 FROM players p
                     WHERE p.race = 'dieu'
                       AND p.id = CAST(t.params AS SIGNED)
                )"
        );
    }

    /**
     * Nothing to put back: the row said an altar belonged to something that is
     * not a god, on a cell holding nothing.
     */
    public function down(Schema $schema): void
    {
    }
}
