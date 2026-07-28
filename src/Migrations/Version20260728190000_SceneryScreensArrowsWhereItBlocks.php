<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A red tile stops arrows by default.
 *
 * The L4 conversion seeded every scenery type with `blocks_projectiles = 0`,
 * so a cell an animator had marked as blocking still let shots through. Since
 * the flag now only matters on cells that are NOT `cover` — that is, on the
 * ones marked `block` — turning it on affects exactly those, and leaves a
 * bush with no blocking cell untouched.
 *
 * An arch is the case for turning it back off on a type: its base refuses the
 * step, its opening lets arrows pass.
 */
final class Version20260728190000_SceneryScreensArrowsWhereItBlocks extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Scenery types stop projectiles, which now only applies to their blocking cells';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "UPDATE races r
               SET r.blocks_projectiles = 1
             WHERE r.blocks_projectiles = 0
               AND EXISTS (
                   SELECT 1 FROM players p
                    WHERE p.race = r.name AND p.player_type = 'scenery'
               )"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "UPDATE races r
               SET r.blocks_projectiles = 0
             WHERE EXISTS (
                   SELECT 1 FROM players p
                    WHERE p.race = r.name AND p.player_type = 'scenery'
               )"
        );
    }
}
