<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A wooden DOOR enters the type catalogue: a pierced wall
 * (structure_nature obstacle, never an édifice — an édifice is walked
 * into, a door is walked THROUGH), lockable and opens_the_way — shut
 * it bars the step and the arrow, open it frees both. Everything else
 * already knows what to do with it: the lock gestures, the pastille,
 * the passage rule, the faction panel.
 *
 * The chests (coffre_bois, coffre_metal, …) need no seeding here: they
 * live in the base items catalogue and their satellites since the
 * chest conversion migrations. Idempotent; no art yet — the initials
 * frame answers until img/avatars/porte_bois gets a picture.
 */
final class Version20260805150000_AWoodenDoorEntersTheCatalogue extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'races: porte_bois — un mur percé, verrouillable, qui ouvre le passage';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "INSERT INTO races (code, name, label, description, kind, structure_nature,
                                pv, bgColor, color, playable, hidden, lockable,
                                opens_the_way, blocks_passage, blocks_projectiles,
                                bleeds, faction, plan)
             SELECT 'PORTE_BOIS', 'porte_bois', 'Porte en bois',
                    'Une porte : ouverte elle laisse passer, fermée elle barre le chemin.',
                    'structure', 'obstacle', 80, '#8b6d43', 'black', 0, 1, 1, 1, 1, 1,
                    '', '', ''
              WHERE NOT EXISTS (SELECT 1 FROM races WHERE name = 'porte_bois')"
        );
    }

    public function down(Schema $schema): void
    {
        /* Only while no entity carries the type: a standing door keeps
         * its type row. */
        $this->addSql(
            "DELETE FROM races
              WHERE name = 'porte_bois'
                AND NOT EXISTS (SELECT 1 FROM players WHERE race = 'porte_bois')"
        );
    }
}
