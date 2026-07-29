<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The altar becomes a structure type, so it can be an entity like the rest.
 *
 * Referenced by NAME everywhere, never by id: `races.altar` is 104 on the
 * production copy and absent here, and `construire` is 161 here against 164
 * on the copy. Ids do not travel between databases.
 *
 * `items.altar.type` is already 'constructible' in production, so `construire`
 * places altar entities there today — mute, godless, invisible to the ranking.
 * Aligning the catalogues is what stops that drifting further.
 */
final class Version20260729180000_AltarIsAStructureType extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The altar enters the races catalogue as a structure type';
    }

    public function up(Schema $schema): void
    {
        /* Values from the production copy, where the type already exists:
         * pv 25, obstacle, invisible to character creation. */
        $this->addSql(
            "INSERT IGNORE INTO races
                (code, name, label, description, playable, hidden, kind, structure_nature,
                 bleeds, wound_color, blocks_passage, blocks_projectiles, bgColor, color,
                 faction, plan, pv)
             VALUES ('ALTAR', 'altar', 'Autel',
                     'Pierre dressée où un Dieu reçoit les prières de ses fidèles.',
                     0, 1, 'structure', 'obstacle', '', '#cd7f32', 1, 1, '#8b6d43', 'black', '', '', 25)"
        );

        /* Without a type, `construire` does not offer it. Production already
         * says 'constructible'; this is the environments agreeing. */
        $this->addSql("UPDATE items SET type = 'constructible' WHERE name = 'altar' AND type = ''");
    }

    public function down(Schema $schema): void
    {
        /* The type goes only if nothing wears it — dropping a race out from
         * under a placed entity would leave it unable to say what it is. */
        $this->addSql(
            "DELETE FROM races
              WHERE name = 'altar'
                AND NOT EXISTS (SELECT 1 FROM players p WHERE p.race = 'altar')"
        );
        $this->addSql("UPDATE items SET type = '' WHERE name = 'altar' AND type = 'constructible'");
    }
}
