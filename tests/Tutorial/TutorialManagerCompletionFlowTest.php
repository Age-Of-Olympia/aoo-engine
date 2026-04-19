<?php

namespace Tests\Tutorial;

use App\Tutorial\TutorialSessionManager;
use Classes\Db;
use PHPUnit\Framework\Attributes\Group;
use Tests\Tutorial\Mock\TutorialIntegrationTestCase;

/**
 * D4 Phase C — integration coverage for the tutorial completion flow.
 *
 * `TutorialManager::completeTutorial()` is private and orchestrates:
 *   - reward capture from TutorialContext
 *   - reward transfer via TutorialPlayerEntity
 *   - resource cleanup via TutorialResourceManager
 *   - session completion via TutorialSessionManager
 *   - replay-aware message building
 *
 * Exercising the full private method from a test would require
 * manufacturing a TutorialContext, TutorialPlayer, and a live tutorial
 * session — roughly 200 lines of scaffolding that the D4 Phase B tests
 * have already committed to deferring.
 *
 * This test covers the durable-state contract that
 * `completeTutorial()` relies on: `TutorialSessionManager::completeSession`
 * plus `hasCompletedBefore` together decide whether the next tutorial
 * attempt is a replay (no reward) or a first-time run (full reward). A
 * silent break in either of those — wrong discriminator column, missing
 * `completed=1` update, wrong `tutorial_mode` filter — would cause
 * players to double-earn XP or lose it entirely.
 *
 * All mutations wrapped in a transaction rolled back in tearDown.
 * Classes\Db is pointed at $this->conn via $GLOBALS['link'] so the
 * writes inside TutorialSessionManager share the test transaction.
 */
class TutorialManagerCompletionFlowTest extends TutorialIntegrationTestCase
{
    private int $realPlayerId = 0;
    private ?string $previousErrorLog = null;

    protected function setUp(): void
    {
        parent::setUp();

        // TutorialSessionManager instantiates Classes\Db which reads
        // from $GLOBALS['link'] via db(). Routing it to the transaction
        // connection keeps the writes rollback-able. db_constants.php
        // satisfies EntityManagerFactory if anything in the call chain
        // touches Doctrine metadata.
        require_once __DIR__ . '/../../config/db_constants.php';
        require_once __DIR__ . '/../../config/functions.php';
        $GLOBALS['link'] = $this->conn;

        // TutorialSessionManager logs to error_log on completion, which
        // PHPUnit's beStrictAboutOutputDuringTests flags as risky.
        // Redirect to a throwaway file + wrap in an output buffer.
        $this->previousErrorLog = ini_get('error_log') ?: '';
        ini_set('error_log', '/tmp/phpunit-completion-flow.log');
        ob_start();

        $this->realPlayerId = $this->seedRealPlayer();
    }

    protected function tearDown(): void
    {
        ob_end_clean();
        ini_set('error_log', $this->previousErrorLog ?? '');
        parent::tearDown();
    }

    #[Group('tutorial-completion-integration')]
    #[Group('d4-phase-c')]
    public function testHasCompletedBeforeReturnsFalseWhenNoProgressRows(): void
    {
        $sessionManager = new TutorialSessionManager();

        $this->assertFalse($sessionManager->hasCompletedBefore($this->realPlayerId));
    }

    #[Group('tutorial-completion-integration')]
    #[Group('d4-phase-c')]
    public function testCompleteSessionSetsCompletedFlagAndXp(): void
    {
        $sessionManager = new TutorialSessionManager();
        $sessionId = $this->seedTutorialProgress($this->realPlayerId, 'first_time');

        $sessionManager->completeSession($sessionId, 390);

        $row = $this->conn->fetchAssociative(
            'SELECT completed, xp_earned, completed_at FROM tutorial_progress WHERE tutorial_session_id = ?',
            [$sessionId]
        );
        $this->assertSame(1, (int) $row['completed']);
        $this->assertSame(390, (int) $row['xp_earned']);
        $this->assertNotNull($row['completed_at']);
    }

    #[Group('tutorial-completion-integration')]
    #[Group('d4-phase-c')]
    public function testHasCompletedBeforeReturnsTrueAfterFirstTimeCompletion(): void
    {
        // This is the contract TutorialManager::completeTutorial relies
        // on: second run sees isReplay=true, so the reward-transfer
        // block is skipped and the message swaps to the replay variant
        // (see TutorialManager.php:453-462).
        $sessionManager = new TutorialSessionManager();
        $sessionId = $this->seedTutorialProgress($this->realPlayerId, 'first_time');

        $sessionManager->completeSession($sessionId, 390);

        $this->assertTrue($sessionManager->hasCompletedBefore($this->realPlayerId));
    }

    #[Group('tutorial-completion-integration')]
    #[Group('d4-phase-c')]
    public function testHasCompletedBeforeIgnoresReplaySessions(): void
    {
        // Only `first_time` sessions gate the reward-transfer. A prior
        // `replay` completion should NOT make a subsequent first_time
        // run behave like a replay. This pins the WHERE clause filter
        // in hasCompletedBefore — changing it to `completed = 1` alone
        // would regress reward attribution on re-registered accounts.
        $sessionManager = new TutorialSessionManager();
        $replaySessionId = $this->seedTutorialProgress($this->realPlayerId, 'replay');

        $sessionManager->completeSession($replaySessionId, 50);

        $this->assertFalse($sessionManager->hasCompletedBefore($this->realPlayerId));
    }

    #[Group('tutorial-completion-integration')]
    #[Group('d4-phase-c')]
    public function testCompleteSessionIsIdempotentOnRepeatCalls(): void
    {
        // Double-clicking "Terminer" shouldn't corrupt state. Second
        // completeSession just re-stamps completed_at and overwrites
        // xp_earned with the same value. Guards against a regression
        // that added an INSERT-or-UPDATE disguised as a plain UPDATE.
        $sessionManager = new TutorialSessionManager();
        $sessionId = $this->seedTutorialProgress($this->realPlayerId, 'first_time');

        $sessionManager->completeSession($sessionId, 390);
        $sessionManager->completeSession($sessionId, 390);

        $count = (int) $this->conn->fetchOne(
            'SELECT COUNT(*) FROM tutorial_progress WHERE tutorial_session_id = ?',
            [$sessionId]
        );
        $this->assertSame(1, $count, 'completeSession must not duplicate the progress row');
    }

    private function seedRealPlayer(): int
    {
        $this->conn->insert('players', [
            'name'        => 'PhaseCComp_' . bin2hex(random_bytes(4)),
            'race'        => 'nain',
            'player_type' => 'real',
            'coords_id'   => (int) $this->conn->fetchOne('SELECT id FROM coords ORDER BY id ASC LIMIT 1'),
        ]);

        return (int) $this->conn->lastInsertId();
    }

    private function seedTutorialProgress(int $playerId, string $mode): string
    {
        $sessionId = 'sess-' . bin2hex(random_bytes(8));

        $this->conn->insert('tutorial_progress', [
            'player_id'           => $playerId,
            'tutorial_session_id' => $sessionId,
            'current_step'        => 'welcome',
            'tutorial_mode'       => $mode,
            'tutorial_version'    => '1.0.0',
            'data'                => json_encode([]),
        ]);

        return $sessionId;
    }
}
