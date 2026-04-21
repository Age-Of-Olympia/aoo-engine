<?php

namespace Tests\Tutorial;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Regression guard: `api/tutorial/complete.php` must reuse the same
 * cleanup primitive as `api/tutorial/cancel.php`.
 *
 * Before Phase 4.x, the two endpoints diverged:
 *
 *   cancel.php    — fetches the TutorialPlayer entity and calls
 *                   TutorialResourceManager::deleteTutorialPlayerAsEntity(),
 *                   which removes (1) the enemy NPC, (2) the tutorial
 *                   players row + FK cascade, (3) the map instance
 *                   (coords).
 *
 *   complete.php  — only ran `UPDATE tutorial_players SET is_active=0`.
 *                   Every completion left behind an enemy `players` row,
 *                   orphaned `coords`, a tutorial `players` row, and
 *                   the tutorial_map_instances entry. N players × N
 *                   completions × 3-5 orphan rows → accumulating
 *                   pollution that has no cleanup job.
 *
 * The two endpoints are independent code paths, so a dev touching one
 * can easily forget to touch the other. This test catches the drift
 * by pinning both files on the same cleanup primitive.
 *
 * The cleanup correctness itself is already covered by
 * TutorialPlayerCleanupIntegrationTest. This test pins the
 * *coordination* contract.
 */
class TutorialCompleteCleanupParityTest extends TestCase
{
    private const CLEANUP_METHOD = 'deleteTutorialPlayerAsEntity';
    private const CANCEL_PATH    = __DIR__ . '/../../api/tutorial/cancel.php';
    private const COMPLETE_PATH  = __DIR__ . '/../../api/tutorial/complete.php';

    #[Group('tutorial-complete-parity')]
    public function testCancelEndpointStillInvokesCleanupPrimitive(): void
    {
        // Guard against us accidentally renaming/removing the cleanup
        // call in cancel.php and the parity test still passing because
        // complete.php also no longer calls it. Anchor both ends.
        $source = (string) file_get_contents(self::CANCEL_PATH);

        $this->assertStringContainsString(
            self::CLEANUP_METHOD,
            $source,
            'cancel.php must keep calling ' . self::CLEANUP_METHOD
            . ' — it is the canonical tutorial cleanup primitive.'
        );
    }

    #[Group('tutorial-complete-parity')]
    public function testCompleteEndpointInvokesSameCleanupPrimitiveAsCancel(): void
    {
        $source = (string) file_get_contents(self::COMPLETE_PATH);

        $this->assertStringContainsString(
            self::CLEANUP_METHOD,
            $source,
            'complete.php must call ' . self::CLEANUP_METHOD . ' (same '
            . 'as cancel.php) so tutorial enemies, coords and the '
            . 'players row are removed. A bare is_active=0 UPDATE '
            . 'leaves orphans — one per successful completion.'
        );
    }

    #[Group('tutorial-complete-parity')]
    public function testCompleteEndpointNoLongerRelyingOnBareIsActiveUpdate(): void
    {
        // The pre-fix shape was:
        //   UPDATE tutorial_players tp JOIN players p … SET tp.is_active = 0
        // Catching this regex specifically (not the broader "is_active = 0"
        // string, which legitimately appears in soft-delete paths called
        // FROM the cleanup primitive) pins that complete.php doesn't keep
        // the raw UPDATE around as a "safety belt".
        $source = (string) file_get_contents(self::COMPLETE_PATH);

        $this->assertDoesNotMatchRegularExpression(
            '/UPDATE\s+tutorial_players\s+tp\s+JOIN\s+players\s+p/i',
            $source,
            'complete.php should no longer contain the raw UPDATE tutorial_players JOIN '
            . 'players cleanup. Route cleanup through TutorialResourceManager instead.'
        );
    }
}
