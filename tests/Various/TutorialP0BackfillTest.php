<?php

namespace Tests\Various;

use App\Tutorial\Steps\Actions\ActionStep;
use App\Tutorial\TutorialSessionManager;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Contract backfill for the P0 fixes of MRs !330 and !331.
 */
class TutorialP0BackfillTest extends TestCase
{
    /**
     * MR !330 — jump-to-step depends on getActiveSession(int): ?array;
     * a rename or signature drift must fail immediately.
     */
    #[Group('p0-backfill')]
    #[Group('mr-330')]
    public function testGetActiveSessionContractMatchesJumpToStepCall(): void
    {
        $method = new ReflectionMethod(TutorialSessionManager::class, 'getActiveSession');
        $params = $method->getParameters();

        $this->assertCount(1, $params, 'getActiveSession must take exactly one argument');
        $this->assertSame('int', (string) $params[0]->getType());
        $this->assertSame('?array', (string) $method->getReturnType());
    }

    /**
     * MR !331 — ActionStep::validate must not leave a debug log file
     * behind (the fwrite to tmp/action_debug.log was removed).
     */
    #[Group('p0-backfill')]
    #[Group('mr-331')]
    public function testActionStepValidateLeavesNoDebugLogFile(): void
    {
        $candidatePaths = [
            __DIR__ . '/../../tmp/action_debug.log',
            __DIR__ . '/../../action_debug.log',
            sys_get_temp_dir() . '/action_debug.log',
        ];

        foreach ($candidatePaths as $path) {
            if (file_exists($path)) {
                $this->fail("Debug log file present before test: {$path}");
            }
        }

        $step = (new ReflectionClass(ActionStep::class))->newInstanceWithoutConstructor();
        $configProp = new ReflectionProperty($step, 'config');
        $configProp->setValue($step, ['validation_type' => 'action_used']);

        $step->validate(['action_name' => 'melee']);

        foreach ($candidatePaths as $path) {
            $this->assertFileDoesNotExist(
                $path,
                "ActionStep::validate must not write to {$path}"
            );
        }
    }
}
