<?php

namespace Tests\Various;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Regression guard: ResetPasswordView must STI-narrow its id lookup to
 * RealPlayer.
 *
 * The view accepts either a numeric matricule or a player name on the
 * POST body and resolves it to a `PlayerEntity`. The non-numeric branch
 * has always used `PlayerFactory::entityByName()` which returns
 * `?RealPlayer` (STI-narrowed). The numeric branch used to call
 * `PlayerFactory::entity()`, which returns `?PlayerEntity` — the
 * abstract base — and would therefore hydrate an `NonPlayerCharacter`
 * for a negative id or a `TutorialPlayer` for any positive tutorial id.
 *
 * Both of those subclasses (a) are not meant to be password-reset
 * targets and (b) have `mail=''` in the schema; reaching them through
 * this flow at minimum leaks that "an entity exists with id X" (via
 * the "aucun personnage" vs "mail n'est pas la bonne" timing/text
 * branches) and at worst widens the surface for a later attack.
 *
 * Fix: use `PlayerFactory::realPlayerById()`, symmetric with
 * `entityByName`. This test pins both directions:
 *   - the view calls `realPlayerById`
 *   - the view no longer calls `PlayerFactory::entity(` on the numeric
 *     branch
 */
class ResetPasswordViewStiNarrowingTest extends TestCase
{
    private const VIEW_PATH = __DIR__ . '/../../src/View/ResetPasswordView.php';

    #[Group('reset-password-sti-narrowing')]
    public function testResetPasswordViewUsesRealPlayerByIdForNumericLookup(): void
    {
        $source = (string) file_get_contents(self::VIEW_PATH);

        $this->assertStringContainsString(
            'PlayerFactory::realPlayerById(',
            $source,
            'ResetPasswordView must use PlayerFactory::realPlayerById() '
            . '(STI-narrowed to RealPlayer) for the numeric-id branch '
            . 'so NPC/TutorialPlayer ids cannot reach the reset flow.'
        );
    }

    #[Group('reset-password-sti-narrowing')]
    public function testResetPasswordViewNoLongerUsesBroadEntityLookup(): void
    {
        $source = (string) file_get_contents(self::VIEW_PATH);

        // `PlayerFactory::entity(` (note the open-paren — we are
        // excluding `entityByName`, which is the correct call for the
        // non-numeric branch). Any hit here means the broad lookup
        // crept back in.
        $this->assertStringNotContainsString(
            'PlayerFactory::entity(',
            $source,
            'ResetPasswordView must not call the broad PlayerFactory::entity() — '
            . 'use realPlayerById() instead for STI narrowing.'
        );
    }
}
