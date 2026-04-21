<?php

namespace Tests\Various;

use App\Tutorial\TutorialHelper;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Characterization tests for getActivePlayerId session-resolution paths
 * that DO NOT touch the database.
 *
 * Two purposes:
 *
 *  1. Pin the public contract of getActivePlayerId for the four DB-free
 *     branches so a future refactor cannot silently change which player
 *     id is returned (the entire tutorial-isolation guarantee depends on
 *     this method picking the right id).
 *
 *  2. Verify the new D1 telemetry (logTelemetry → error_log) does NOT
 *     fire on the happy-path branches. Telemetry should only emit when
 *     the DB-validation branch detects a stale session — which is
 *     exactly the TOCTOU signal we want to measure.
 *
 * Tutorial-mode resolution that hits validateTutorialPlayer (DB query)
 * is exercised by the Cypress tutorial-production-ready spec.
 */
class TutorialHelperTelemetryTest extends TestCase
{
    private string $logFile;
    private string $previousErrorLog;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->logFile = tempnam(sys_get_temp_dir(), 'tutorial-helper-test-');
        $this->previousErrorLog = (string) ini_get('error_log');
        ini_set('error_log', $this->logFile);
    }

    protected function tearDown(): void
    {
        ini_set('error_log', $this->previousErrorLog);
        @unlink($this->logFile);
        $_SESSION = [];
    }

    #[Group('tutorial-helper-telemetry')]
    public function testReturnsZeroWhenSessionEmpty(): void
    {
        $this->assertSame(0, TutorialHelper::getActivePlayerId());
        $this->assertSame('', (string) file_get_contents($this->logFile));
    }

    #[Group('tutorial-helper-telemetry')]
    public function testReturnsMainPlayerIdWhenNotInTutorial(): void
    {
        $_SESSION['playerId'] = 42;

        $this->assertSame(42, TutorialHelper::getActivePlayerId());
        $this->assertSame('', (string) file_get_contents($this->logFile));
    }

    #[Group('tutorial-helper-telemetry')]
    public function testFallsBackToMainWhenTutorialFlagSetButPlayerIdMissing(): void
    {
        // `in_tutorial` is truthy but `tutorial_player_id` is absent —
        // this is the "session half-cleared" state that occurs after a
        // partial tutorial cleanup. Method must NOT enter the DB-validation
        // branch and must NOT emit telemetry.
        $_SESSION['playerId'] = 7;
        $_SESSION['in_tutorial'] = true;

        $this->assertSame(7, TutorialHelper::getActivePlayerId());
        $this->assertSame('', (string) file_get_contents($this->logFile));
    }

    #[Group('tutorial-helper-telemetry')]
    public function testFallsBackToMainWhenTutorialPlayerIdIsZero(): void
    {
        $_SESSION['playerId'] = 99;
        $_SESSION['in_tutorial'] = true;
        $_SESSION['tutorial_player_id'] = 0;

        $this->assertSame(99, TutorialHelper::getActivePlayerId());
        $this->assertSame('', (string) file_get_contents($this->logFile));
    }

    /**
     * Regression guard: logTelemetry() must actually emit to error_log.
     *
     * During the debug-cleanup sweep (commits 6c492b4 / 553072e) the
     * error_log call was stripped from logTelemetry's if-body, leaving
     * an empty block that silently dropped every stale-session event —
     * the very metric the docblock advertises as the D1 observability
     * signal. Pin the contract: a well-formed JSON line containing the
     * event discriminator must land in the configured error_log.
     */
    #[Group('tutorial-helper-telemetry')]
    public function testLogTelemetryEmitsJsonLineToErrorLog(): void
    {
        // Re-assert the ini inside the test body: PHPUnit can reset
        // some ini entries between setUp and the test method when it
        // installs its own error handler, which silently swallows the
        // error_log() write even though the path is still set.
        ini_set('error_log', $this->logFile);
        ini_set('log_errors', '1');

        $method = new ReflectionMethod(TutorialHelper::class, 'logTelemetry');
        $method->setAccessible(true);
        $method->invoke(null, 'tutorial_session_stale', [
            'tutorial_player_id' => 42,
            'main_player_id'     => 7,
        ]);

        $log = (string) file_get_contents($this->logFile);

        $this->assertNotSame('', $log, 'logTelemetry must write to error_log');
        $this->assertStringContainsString('"event":"tutorial_session_stale"', $log);
        $this->assertStringContainsString('"tutorial_player_id":42', $log);
        $this->assertStringContainsString('"main_player_id":7', $log);
        $this->assertStringContainsString('"ts":', $log);
    }
}
