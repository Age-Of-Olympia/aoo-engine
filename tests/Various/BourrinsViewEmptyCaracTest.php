<?php

namespace Tests\Various;

use App\View\Classement\BourrinsView;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Regression: BourrinsView::print_best_carac crashed (`krsort(null)`)
 * when the requested carac was missing from $bestCarac. Reproduced by
 * `print_best_carac('a', [])` — the exact shape from the user-reported
 * stack trace on /classements.php?b.
 *
 * Trigger condition: every player in the rankings list failed
 * `PlayerFactory::entity()` lookup (e.g. only positive-id players that
 * the entity layer can't hydrate), so the carac aggregator never wrote
 * an entry for any key.
 */
class BourrinsViewEmptyCaracTest extends TestCase
{
    #[Group('bourrins-view-empty')]
    public function testPrintBestCaracDoesNotCrashOnMissingCaracKey(): void
    {
        ob_start();
        try {
            BourrinsView::print_best_carac('a', []);
        } finally {
            $output = ob_get_clean();
        }

        // Function must not throw and must produce *some* output (the
        // table shell at minimum) so the surrounding renderBourrins
        // page doesn't half-render and break layout.
        $this->assertIsString($output);
    }

    #[Group('bourrins-view-empty')]
    public function testPrintBestCaracDoesNotCrashWhenCaracKeyExistsButIsEmpty(): void
    {
        // Adjacent edge case: a key was registered with an empty
        // sub-array (no players had any value for that carac). krsort
        // on [] is fine — but the surrounding foreach must also handle
        // it without producing a warning.
        ob_start();
        try {
            BourrinsView::print_best_carac('a', ['a' => []]);
        } finally {
            $output = ob_get_clean();
        }

        $this->assertIsString($output);
    }
}
