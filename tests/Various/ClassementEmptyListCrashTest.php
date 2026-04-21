<?php

namespace Tests\Various;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Sibling-bug audit follow-up to MR !435 (BourrinsView empty-carac
 * crash). Two more places on the rankings page derefed a structure
 * that becomes empty when every active player is filtered out (id ≤
 * 1 or lastLoginTime < INACTIVE_TIME):
 *
 *   - ReputationsView::renderReputations() : `$playerList[0]->showReput = 1;`
 *     fatals on TypeError ("access property on null") for empty input.
 *
 *   - classements.php (no class — inline at lines 153-162):
 *     a foreach picks the first player into `$first`, then derefs
 *     `$first->id` / `$first->name` to write a "X domine le classement"
 *     banner. If the foreach has no iterations, $first is undefined →
 *     PHP 8 fatal.
 */
class ClassementEmptyListCrashTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    #[Group('classement-empty-list')]
    public function testReputationsViewGuardsShowReputAssignment(): void
    {
        // ReputationsView calls the top-level print_players() helper
        // defined in classements.php, which makes a clean unit-render
        // test infeasible without bootstrapping the whole script. Pin
        // the fix shape via source inspection instead: the
        // `$playerList[0]->showReput = 1` assignment must sit inside
        // an `!empty($playerList)` guard.
        $source = (string) file_get_contents(self::ROOT . '/src/View/Classement/ReputationsView.php');

        $assignPos = strpos($source, '$playerList[0]->showReput');
        $this->assertNotFalse(
            $assignPos,
            'ReputationsView should still mark the first player with showReput.'
        );

        $window = substr($source, max(0, $assignPos - 200), 200);
        $this->assertMatchesRegularExpression(
            '/!\s*empty\s*\(\s*\$playerList\s*\)/',
            $window,
            'ReputationsView::renderReputations must guard '
            . '$playerList[0]->showReput against an empty list — when '
            . 'every player is filtered out (PNJ / inactive), the '
            . 'unguarded deref produces a TypeError on PHP 8.'
        );
    }

    #[Group('classement-empty-list')]
    public function testClassementsScriptGuardsFirstPlayerAccess(): void
    {
        // The inline "$first->id / $first->name" banner block in
        // classements.php fatals when $playerList is empty (the
        // foreach never assigns $first). The fix shape is to wrap
        // the banner generation in an `if (!empty($playerList))`.
        // We pin the wrapper rather than trying to bootstrap the
        // whole script in PHPUnit.
        $source = (string) file_get_contents(self::ROOT . '/classements.php');

        $bannerPos = strpos($source, '$first->id');
        $this->assertNotFalse(
            $bannerPos,
            'classements.php should still render the "X domine le classement" banner.'
        );

        // Look back a few lines for an `!empty($playerList)` guard
        // OR an `isset($first)` guard between the foreach and the
        // banner output.
        $window = substr($source, max(0, $bannerPos - 600), 600);

        $hasGuard =
               (bool) preg_match('/!\s*empty\s*\(\s*\$playerList\s*\)/', $window)
            || (bool) preg_match('/isset\s*\(\s*\$first\s*\)/', $window)
            || (bool) preg_match('/\$first\s*===?\s*null/', $window)
            || (bool) preg_match('/\$first\s*=\s*null/', $window); /* explicit init + later guard */

        $this->assertTrue(
            $hasGuard,
            'classements.php must guard the $first->id / $first->name banner '
            . 'block — when every player is filtered out (only PNJs / inactive '
            . 'accounts), the foreach never runs and $first is undefined → '
            . 'PHP 8 fatal.'
        );
    }
}
