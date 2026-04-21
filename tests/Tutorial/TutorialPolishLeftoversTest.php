<?php

namespace Tests\Tutorial;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Low-priority polish leftovers from the original four-agent review.
 */
class TutorialPolishLeftoversTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    #[Group('tutorial-polish-leftovers')]
    public function testAbstractStepDoesNotHardcodeRaceMaxMvtFallback(): void
    {
        $source = (string) file_get_contents(self::ROOT . '/src/Tutorial/Steps/AbstractStep.php');

        $this->assertDoesNotMatchRegularExpression(
            '/\$raceMaxMvt\s*=\s*4\s*;/',
            $source,
            'AbstractStep still hardcodes `$raceMaxMvt = 4` as its own '
            . 'fallback. Delegate the default to RaceService::getRaceMaxMvt '
            . '(which already returns 4 on miss) so there is only one '
            . 'source of truth.'
        );
    }

    #[Group('tutorial-polish-leftovers')]
    public function testResumePersistenceSpecNameIsCollisionResistant(): void
    {
        $source = (string) file_get_contents(self::ROOT . '/cypress/e2e/tutorial-resume-persistence.cy.js');

        $this->assertMatchesRegularExpression(
            '/name:\s*`ResumeTest[^`]*\$\{\s*(timestamp|Date\.now\(\)|Math\.random\(\)|[a-zA-Z_]*[Hh]ex)/',
            $source,
            'Cypress resume-persistence spec\'s TEST_ACCOUNT.name is '
            . 'collision-prone (random pick from 8 Greek letters). Add '
            . 'a `${timestamp}` or `${…Date.now()…}` suffix to the name '
            . 'template so parallel / repeated runs against the same DB '
            . 'do not collide on player names.'
        );
    }

    #[Group('tutorial-polish-leftovers')]
    public function testClaudeMdDoesNotListDroppedRealPlayerIdColumn(): void
    {
        $source = (string) file_get_contents(self::ROOT . '/CLAUDE.md');

        $this->assertDoesNotMatchRegularExpression(
            '/tutorial_players[^\n]*\breal_player_id\b[^_]/',
            $source,
            'CLAUDE.md still lists `real_player_id` in the '
            . 'tutorial_players table schema. That column was dropped '
            . 'in Phase 4.5 (Version20260419200000). The link now lives '
            . 'on players.real_player_id_ref — remove the mention.'
        );
    }
}
