<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * First structure pseudo-race: 'palissade'
 * (docs/design-buildings-entities.md §4.6).
 *
 * Structures reuse the caracs pipeline untouched: their max PV is a
 * races row, exactly like characters. playable = 0 keeps it out of
 * registration (RaceService::getPlayableRaces filters on the flag),
 * hidden = 1 keeps it out of public race pages. Every carac is 0
 * except pv — mvt 0 = cannot move, esquive is not granted, etc.
 *
 * Further archetypes ('tour', 'entrepot'…) are game content: admins
 * create them in admin/races.php as non-playable races; this migration
 * only ships the canonical first one so BuildingService has a seed to
 * stand on everywhere.
 *
 * INSERT IGNORE: re-runnable, and never clobbers admin tuning of an
 * existing row.
 */
final class Version20260716130000_PalissadePseudoRace extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Seed the non-playable 'palissade' pseudo-race carrying building base stats";
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "INSERT IGNORE INTO races
                (code, name, label, description, playable, hidden, bgColor, color, faction, plan, pv)
             VALUES
                ('PALISSADE', 'palissade', 'Palissade',
                 'Mur de pieux dressés — première ligne de défense d''un campement.',
                 0, 1, '#8b6d43', 'black', '', '', 100)"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM races WHERE name = 'palissade' AND playable = 0");
    }
}
