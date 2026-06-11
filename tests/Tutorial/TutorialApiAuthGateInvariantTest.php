<?php

namespace Tests\Tutorial;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Removed debug/orphan endpoints under api/tutorial/ leaked session
 * state to unauthenticated callers; they must not reappear via
 * copy-paste of older branches.
 */
class TutorialApiAuthGateInvariantTest extends TestCase
{
    private const API_DIR = __DIR__ . '/../../api/tutorial';

    #[Group('tutorial-api-auth-gate')]
    public function testDebugStubsAreRemoved(): void
    {
        // Anchor the specific files the audit called out. Keeps
        // them from being reintroduced via copy-paste of older
        // branches.
        $this->assertFileDoesNotExist(
            self::API_DIR . '/test.php',
            'api/tutorial/test.php was an unauthenticated debug endpoint — '
            . 'it must not reappear. Use scripts/ for debug utilities.'
        );
        $this->assertFileDoesNotExist(
            self::API_DIR . '/check_session.php',
            'api/tutorial/check_session.php was an unauthenticated debug endpoint — '
            . 'it must not reappear. Use scripts/ for debug utilities.'
        );
        $this->assertFileDoesNotExist(
            self::API_DIR . '/exit_tutorial_mode.php',
            'api/tutorial/exit_tutorial_mode.php was an orphan, bootstrap-'
            . 'bypassing session-rewrite endpoint. It must not reappear — '
            . 'use TutorialHelper::exitTutorialMode() from an authenticated '
            . 'endpoint instead.'
        );
        $this->assertFileDoesNotExist(
            self::API_DIR . '/check_tutorial_character.php',
            'api/tutorial/check_tutorial_character.php was an orphan '
            . 'bootstrap-bypassing endpoint. It must not reappear — if a '
            . 'future UI needs this check, expose it from an authenticated '
            . 'endpoint that loads config.php.'
        );
    }
}
