<?php

namespace Tests\Tutorial;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Regression guard: Version20251127000000 perimeter wall seed must
 * dedup at the corners (no four-cardinal INSERT IGNORE shape).
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
