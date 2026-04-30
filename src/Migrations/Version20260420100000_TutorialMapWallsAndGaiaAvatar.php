<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Tutorial visual polish:
 *
 *   1. Extend the `tutorial` template map from a 7x7 grid (ABS coord ≤ 3)
 *      to 9x9 (ABS coord ≤ 4), turning the outer ±4 ring into perimeter
 *      stone walls. The playable interior (±3) stays fully walkable.
 *   2. Carpet the walkable interior with `eryn_dolen` ground tiles (the
 *      closest asset to "grass floor" available in img/tiles/).
 *   3. Drop a handful of decorative trees/rocks in corners that are not
 *      on any expected movement path.
 *   4. Point Gaïa's avatar at an asset that actually exists on disk
 *      (`img/avatars/ame/dieu.webp`) instead of the broken
 *      `img/avatars/dieu/1.png` that had no file.
 *
 * Each tutorial session copies from this template via
 * TutorialMapInstance::copyMapElements, so the changes propagate to all
 * new sessions automatically. Idempotent — every INSERT uses
 * NOT EXISTS guards and every UPDATE checks the current value.
 */
final class Version20260420100000_TutorialMapWallsAndGaiaAvatar extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Extend tutorial template to 9x9 with perimeter walls + grass interior; fix Gaïa avatar';
    }

    public function up(Schema $schema): void
    {
        /* 1. Gaïa avatar fix — also catches already-copied instance Gaïas.
         * Originally re-pointed at the placeholder ame/dieu silhouette;
         * the proper goddess imagery (dieu/25 + dieu/1) is in prod assets,
         * so we land directly there. Version20260430140000 follows up to
         * scrub any environment that already absorbed the placeholder. */
        $this->addSql("
            UPDATE players
            SET avatar = 'img/avatars/dieu/25.png',
                portrait = 'img/portraits/dieu/1.jpeg'
            WHERE name = 'Gaïa' AND avatar = 'img/avatars/dieu/1.png'
        ");

        /* 2. Extend the coords grid so the new ±4 ring exists (idempotent) */
        $this->addSql("
            INSERT INTO coords (x, y, z, plan)
            SELECT x, y, 0, 'tutorial' FROM (
                SELECT -4 AS x, -4 AS y UNION SELECT -3,-4 UNION SELECT -2,-4 UNION SELECT -1,-4
                UNION SELECT 0,-4 UNION SELECT 1,-4 UNION SELECT 2,-4 UNION SELECT 3,-4 UNION SELECT 4,-4
                UNION SELECT -4,-3 UNION SELECT 4,-3
                UNION SELECT -4,-2 UNION SELECT 4,-2
                UNION SELECT -4,-1 UNION SELECT 4,-1
                UNION SELECT -4,0  UNION SELECT 4,0
                UNION SELECT -4,1  UNION SELECT 4,1
                UNION SELECT -4,2  UNION SELECT 4,2
                UNION SELECT -4,3  UNION SELECT 4,3
                UNION SELECT -4,4 UNION SELECT -3,4 UNION SELECT -2,4 UNION SELECT -1,4
                UNION SELECT 0,4 UNION SELECT 1,4 UNION SELECT 2,4 UNION SELECT 3,4 UNION SELECT 4,4
            ) ring
            WHERE NOT EXISTS (
                SELECT 1 FROM coords c WHERE c.plan='tutorial' AND c.x=ring.x AND c.y=ring.y
            )
        ");

        /* 3. Perimeter stone walls on the ±4 ring (outside the playable interior) */
        $this->addSql("
            INSERT INTO map_walls (name, coords_id, damages)
            SELECT 'mur_pierre', c.id, 0
            FROM coords c
            WHERE c.plan='tutorial' AND (ABS(c.x)=4 OR ABS(c.y)=4)
              AND NOT EXISTS (SELECT 1 FROM map_walls mw WHERE mw.coords_id = c.id)
        ");

        /* 4. Decorative fill in the interior corners, away from movement paths. */
        foreach ([
            ['arbre2', -2,  2],
            ['arbre2',  2,  2],
            ['pierre1', -2, -2],
            ['pierre1',  2, -2],
        ] as [$wallName, $x, $y]) {
            $this->addSql(
                "INSERT INTO map_walls (name, coords_id, damages)
                 SELECT ?, c.id, 0 FROM coords c
                 WHERE c.plan='tutorial' AND c.x=? AND c.y=?
                   AND NOT EXISTS (SELECT 1 FROM map_walls mw WHERE mw.coords_id = c.id)",
                [$wallName, $x, $y]
            );
        }

        /* 5. Grass carpet on every interior walkable tile (ABS<=3, no wall on it).
         * `eryn_dolen` is the closest to a plain grass texture in img/tiles/. */
        $this->addSql("
            INSERT INTO map_tiles (name, coords_id, foreground)
            SELECT 'eryn_dolen', c.id, 0
            FROM coords c
            WHERE c.plan='tutorial' AND ABS(c.x)<=3 AND ABS(c.y)<=3
              AND NOT EXISTS (SELECT 1 FROM map_tiles mt WHERE mt.coords_id = c.id)
        ");
    }

    public function down(Schema $schema): void
    {
        /* Revert Gaïa avatar */
        $this->addSql("
            UPDATE players
            SET avatar = 'img/avatars/dieu/1.png', portrait = 'img/portraits/dieu/1.jpeg'
            WHERE name = 'Gaïa' AND avatar = 'img/avatars/dieu/25.png'
        ");

        /* Remove grass carpet we added */
        $this->addSql("
            DELETE mt FROM map_tiles mt
            JOIN coords c ON c.id = mt.coords_id
            WHERE c.plan='tutorial' AND mt.name='eryn_dolen'
        ");

        /* Remove the walls we added (perimeter + decorations). Keep the
         * original arbre1 tree at (0,1). */
        $this->addSql("
            DELETE mw FROM map_walls mw
            JOIN coords c ON c.id = mw.coords_id
            WHERE c.plan='tutorial'
              AND mw.name IN ('mur_pierre', 'arbre2', 'pierre1')
        ");

        /* Leave the extra coords in place; dropping them risks leaving
         * orphan FK references from map_walls/tiles on other plans that
         * happen to share the same coord row. */
    }
}
