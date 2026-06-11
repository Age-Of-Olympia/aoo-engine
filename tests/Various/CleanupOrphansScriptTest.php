<?php

namespace Tests\Various;

use App\Tutorial\TutorialEnemyCleanup;
use App\Tutorial\TutorialPlayerCleanup;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Contract tests for the cleanup-service methods that
 * scripts/tutorial/cleanup_orphans.php depends on: a rename or
 * signature drift must fail in CI instead of the cron throwing fatals.
 */
class CleanupOrphansScriptTest extends TestCase
{
    #[Group('cleanup-orphans')]
    public function testRequiredEnemyCleanupContractMatches(): void
    {
        // Use reflection so PHPStan does not narrow this away; we want
        // the test to actually FAIL (not pass-by-static-analysis) if the
        // method is renamed or changes signature. Parameter name is NOT
        // pinned — no caller uses named arguments, so the name is
        // internal to TutorialEnemyCleanup.
        $method = new ReflectionMethod(TutorialEnemyCleanup::class, 'removeBySessionId');
        $params = $method->getParameters();

        $this->assertCount(1, $params, 'removeBySessionId must take exactly one argument');
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
