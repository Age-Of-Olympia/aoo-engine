<?php

namespace Tests\Tutorial;

use App\Tutorial\TutorialSessionManager;
use Classes\Db;
use PHPUnit\Framework\Attributes\Group;
use Tests\Tutorial\Mock\TutorialIntegrationTestCase;

/**
 * Regression guard against an IDOR in the tutorial API layer.
 *
 * Current state: `api/tutorial/advance.php`, `api/tutorial/cancel.php`,
 * and `api/tutorial/jump-to-step.php` accept a `session_id` from the
 * request body and pass it straight to `TutorialManager::resumeTutorial`
 * / `cancelSession` with no check that the session belongs to the caller.
 * A logged-in player who observes another player's session_id (e.g. via
 * the admin sessions list, or leaked through a shared page) can
 * advance / cancel / jump that other player's tutorial.
 *
 * Fix: `TutorialSessionManager::playerOwnsSession()` is the single
 * canonical ownership probe the three endpoints gate on. Tests here
 * pin the three contract shapes:
 *
 *   1. caller owns the session          → true
 *   2. caller does NOT own the session  → false
 *   3. session_id does not exist at all → false
 */
class TutorialSessionOwnershipTest extends TutorialIntegrationTestCase
{
    /** @var mixed original $GLOBALS['link'] before we overrode it */
    private $previousLink = null;

    protected function setUp(): void
    {
        parent::setUp();

        // TutorialSessionManager uses Classes\Db, which reads
        // $GLOBALS['link']. Point it at our transactional test
        // connection so the seeded rows rollback at tearDown.
        $this->previousLink = $GLOBALS['link'] ?? null;
        $GLOBALS['link'] = $this->conn;
    }

    protected function tearDown(): void
    {
        $GLOBALS['link'] = $this->previousLink;
        parent::tearDown();
    }

    #[Group('tutorial-session-ownership')]
    public function testPlayerOwnsSessionReturnsTrueForCallerOwnedSession(): void
    {
        $ownerId = $this->seedPlayer();
        $sessionId = $this->seedProgressFor($ownerId);

        $this->assertTrue(
            (new TutorialSessionManager(new Db()))
                ->playerOwnsSession($sessionId, $ownerId)
        );
    }

    #[Group('tutorial-session-ownership')]
    public function testPlayerOwnsSessionReturnsFalseForCrossPlayerAccess(): void
    {
        $ownerId = $this->seedPlayer();
        $attackerId = $this->seedPlayer();
        $sessionId = $this->seedProgressFor($ownerId);

        // The IDOR shape: attacker knows the session_id of another
        // player and attempts to operate on it. The contract MUST
        // reject this even though the session itself is valid.
        $this->assertFalse(
            (new TutorialSessionManager(new Db()))
                ->playerOwnsSession($sessionId, $attackerId)
        );
    }

    #[Group('tutorial-session-ownership')]
    public function testPlayerOwnsSessionReturnsFalseForUnknownSessionId(): void
    {
        $playerId = $this->seedPlayer();
        $bogusSessionId = '00000000-0000-4000-8000-000000000000';

        $this->assertFalse(
            (new TutorialSessionManager(new Db()))
                ->playerOwnsSession($bogusSessionId, $playerId)
        );
    }

    private function seedPlayer(): int
    {
        $this->conn->insert('coords', [
            'x'    => 0,
            'y'    => 0,
            'z'    => 0,
            'plan' => 'test-ownership',
        ]);
        $coordsId = (int) $this->conn->lastInsertId();

        $this->conn->insert('players', [
            'name'        => 'OwnershipTest_' . bin2hex(random_bytes(4)),
            'race'        => 'nain',
            'player_type' => 'real',
            'coords_id'   => $coordsId,
        ]);

        return (int) $this->conn->lastInsertId();
    }

    private function seedProgressFor(int $playerId): string
    {
        $sessionId = sprintf(
            '%08x-%04x-4%03x-%04x-%012x',
            random_int(0, 0xffffffff),
            random_int(0, 0xffff),
            random_int(0, 0xfff),
            random_int(0x8000, 0xbfff),
            random_int(0, 0xffffffffffff),
        );

        $this->conn->insert('tutorial_progress', [
            'player_id'           => $playerId,
            'tutorial_session_id' => $sessionId,
            'current_step'        => 'welcome',
            'tutorial_mode'       => 'first_time',
            'tutorial_version'    => '1.0.0',
            'data'                => '{}',
        ]);

        return $sessionId;
    }
}
