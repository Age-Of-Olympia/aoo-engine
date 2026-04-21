<?php

namespace Tests\Tutorial;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Low-priority polish leftovers from the original four-agent review.
 * Each pins a specific drift or flake; none are critical.
 */
class TutorialPolishLeftoversTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    /**
     * AbstractStep used to hardcode `$raceMaxMvt = 4` as its own
     * fallback — shadowing the same default inside
     * RaceService::getRaceMaxMvt(). If either default is bumped, the
     * two silently disagree. Route the fallback through the service
     * so there is one source of truth.
     */
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

    /**
     * Cypress tutorial-resume-persistence picks a random Greek letter
     * from a list of 8 as its player name. Against a non-reset DB this
     * is ~12.5% collision on every run; under CI parallelism, higher.
     * The fix injects a timestamp or random-hex suffix for uniqueness.
     */
    #[Group('tutorial-polish-leftovers')]
    public function testResumePersistenceSpecNameIsCollisionResistant(): void
    {
        $source = (string) file_get_contents(self::ROOT . '/cypress/e2e/tutorial-resume-persistence.cy.js');

        // The fix either drops the 8-letter random list entirely, or
        // combines it with `timestamp` / `Math.random` / hex bytes so
        // the resulting name is (effectively) unique across runs.
        //
        // We check the template-string that builds the player name —
        // it must reference one of: timestamp, Date.now, random, hex
        // bytes. The bare `ResumeTest${randomName}` (a lone pick from
        // an 8-element array) is rejected.
        // Require the `name: …` template to contain a `${timestamp}`
        // or `${…Date.now…}` or a distinct hex/random suffix. The
        // existing `${randomName}` (lone pick from an 8-element array)
        // matches any substring regex too leniently (the word
        // "random" is inside the variable name), so we check for a
        // real call producing a fresh value.
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

    /**
     * CLAUDE.md listed `tutorial_players.real_player_id` in the table
     * schema two places (main doc + key tables section). That column
     * was dropped in Phase 4.5 (Version20260419200000), with the link
     * moving to `players.real_player_id_ref`. Keeping the stale docs
     * misleads anyone who greps CLAUDE.md to understand the schema.
     */
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
