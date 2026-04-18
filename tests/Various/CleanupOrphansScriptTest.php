<?php

namespace Tests\Various;

use App\Tutorial\TutorialEnemyCleanup;
use App\Tutorial\TutorialPlayerCleanup;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Contract tests for scripts/tutorial/cleanup_orphans.php.
 *
 * The script is operational tooling — most of its logic is procedural
 * SQL + service calls that are hard to exercise without a fixture DB.
 * What WE can pin cheaply, and what catches the realistic regression
 * vectors:
 *
 *  1. The script parses as valid PHP (no half-merged syntax errors).
 *  2. The cleanup-service methods the script depends on still exist
 *     with the expected signatures. If TutorialEnemyCleanup::removeBySessionId
 *     or TutorialPlayerCleanup::deleteTutorialPlayer is ever renamed,
 *     this test fails immediately instead of the cron silently throwing
 *     fatals at 3am.
 *
 * Wet-path testing (real DB fixture, real cleanup) is intentionally out
 * of scope — the project does not currently have a Tests\Scripts\
 * pattern, and adding one for a single script would be a precedent
 * decision separate from this MR.
 */
class CleanupOrphansScriptTest extends TestCase
{
    private const SCRIPT = __DIR__ . '/../../scripts/tutorial/cleanup_orphans.php';

    #[Group('cleanup-orphans')]
    public function testScriptParsesAsValidPhp(): void
    {
        $output = shell_exec('php -l ' . escapeshellarg(self::SCRIPT) . ' 2>&1');
        $this->assertNotNull($output);
        $this->assertStringContainsString('No syntax errors', $output);
    }

    #[Group('cleanup-orphans')]
    public function testRequiredEnemyCleanupContractMatches(): void
    {
        // Use reflection so PHPStan does not narrow this away; we want
        // the test to actually FAIL (not pass-by-static-analysis) if the
        // method is renamed or changes signature.
        $method = new ReflectionMethod(TutorialEnemyCleanup::class, 'removeBySessionId');
        $params = $method->getParameters();

        $this->assertCount(1, $params, 'removeBySessionId must take exactly one argument');
        $this->assertSame('sessionId', $params[0]->getName());
        $this->assertSame('string', (string) $params[0]->getType());
    }

    #[Group('cleanup-orphans')]
    public function testRequiredPlayerCleanupContractMatches(): void
    {
        $method = new ReflectionMethod(TutorialPlayerCleanup::class, 'deleteTutorialPlayer');
        $params = $method->getParameters();

        $this->assertCount(2, $params, 'deleteTutorialPlayer must take (int $tutorialPlayersId, int $actualPlayerId)');
        $this->assertSame('int', (string) $params[0]->getType());
        $this->assertSame('int', (string) $params[1]->getType());
    }
}
