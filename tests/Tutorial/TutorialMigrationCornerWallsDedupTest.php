<?php

namespace Tests\Tutorial;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Regression guard: Version20251127000000 must seed the tutorial
 * perimeter walls with a corner-dedup-safe SQL pattern.
 *
 * Original shape seeded the north / south / west / east walls as
 * four separate `INSERT IGNORE INTO map_walls … WHERE c.y=-4` (etc.)
 * statements. The 4 corner coords `(±4, ±4)` are hit by two rings
 * each; `INSERT IGNORE` only drops duplicates when a unique key
 * matches, and `map_walls` has NO unique index on
 * `(coords_id, name)` — so fresh-install migrate ends up with 36
 * perimeter walls instead of 32. Commit 2ed229a fixed the parallel
 * `init_noupdates.sql` path (used by CI) but NOT this migration
 * (still consulted on production replays).
 *
 * Fix: single INSERT that seeds the entire perimeter ring with a
 * `NOT EXISTS (SELECT 1 FROM map_walls …)` guard — same shape
 * Version20260420100000:67-74 already uses. Exactly-once guarantee
 * without depending on schema-level unique constraints.
 */
class TutorialMigrationCornerWallsDedupTest extends TestCase
{
    private const MIGRATION_PATH =
        __DIR__ . '/../../src/Migrations/Version20251127000000_CreateCompleteTutorialSystem.php';

    #[Group('tutorial-migration-corner-walls')]
    public function testPerimeterWallSeedUsesNotExistsDedup(): void
    {
        $source = (string) file_get_contents(self::MIGRATION_PATH);

        $this->assertMatchesRegularExpression(
            '/NOT\s+EXISTS\s*\(\s*SELECT\s+1\s+FROM\s+map_walls/i',
            $source,
            'Version20251127000000 must seed map_walls with a "NOT EXISTS '
            . '(SELECT 1 FROM map_walls …)" guard — otherwise the 4 cardinal '
            . 'INSERT IGNORE statements collide at the corners and produce '
            . '36 perimeter walls instead of 32 on fresh migrate.'
        );
    }

    #[Group('tutorial-migration-corner-walls')]
    public function testPerimeterWallSeedDoesNotSplitByCardinal(): void
    {
        // The four separate `WHERE … c.y = -4` / `c.y = 4` / `c.x = -4`
        // / `c.x = 4` INSERTs are the specific anti-pattern we want to
        // keep retired. A single-statement seed using
        // `WHERE (ABS(c.x)=4 OR ABS(c.y)=4)` covers the whole ring and
        // cannot double-insert at the corners.
        $source = (string) file_get_contents(self::MIGRATION_PATH);

        $cardinalHits = 0;
        $cardinalHits += preg_match_all('/WHERE[^;]*c\.y\s*=\s*-?4\b/i', $source);
        $cardinalHits += preg_match_all('/WHERE[^;]*c\.x\s*=\s*-?4\b/i', $source);

        $this->assertLessThan(
            4,
            $cardinalHits,
            'Version20251127000000 still looks like it seeds the perimeter '
            . 'with four separate cardinal WHERE clauses — collapse into a '
            . 'single ring INSERT with `(ABS(c.x)=4 OR ABS(c.y)=4)` to drop '
            . 'the corner collision.'
        );
    }
}
