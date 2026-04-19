<?php

namespace Tests\Tutorial;

use App\View\TutorialView;
use Classes\Player;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Regression test for the legacy tutorial UI.
 *
 * `TutorialView::renderTutorial()` drives the pre-refactor tutorial
 * (tooltip sequence + screen indicators). It's triggered by the
 * `?tutorial` URL param (from account.php's "Tutoriel" button when
 * `TutorialFeatureFlag` is OFF for the player).
 *
 * The rendered HTML must include a `<script src="js/tutorial.js">`
 * tag — that script binds the `#tooltip` div to a sequence of
 * highlighted UI targets (avatar, move tile, chessboard, key,
 * missive). Without it, the tooltip renders empty and no indicators
 * appear — exactly the symptom reported on this branch.
 *
 * The script tag was dropped in the Phase 0-4 refactor wave with a
 * comment claiming "scripts already loaded by Ui.php" — but Ui.php
 * loads the NEW tutorial scripts (`js/tutorial/TutorialUI.js` etc.),
 * never `js/tutorial.js`. This test pins the legacy loader so the
 * regression can't re-appear.
 */
class LegacyTutorialScriptLoadingTest extends TestCase
{
    #[Group('legacy-tutorial')]
    public function testRenderTutorialEmitsLegacyScriptTag(): void
    {
        $_SESSION['playerId'] = 1;
        $player = new LegacyTutorialPlayerStub();
        $player->id = 1;
        $player->coords = (object) ['x' => 0, 'y' => 0, 'z' => 0, 'plan' => 'gaia'];

        ob_start();
        TutorialView::renderTutorial($player);
        $html = ob_get_clean();

        $this->assertStringContainsString(
            'js/tutorial.js',
            $html,
            'TutorialView::renderTutorial must load js/tutorial.js — '
            . 'without it the tooltip div has no driver, so dialogs and '
            . 'screen indicators never appear (legacy tutorial regression).'
        );
        $this->assertStringContainsString(
            'id="tooltip"',
            $html,
            'The #tooltip anchor element must still be emitted.'
        );
    }
}

/**
 * Minimal stub that extends Classes\Player to satisfy the type hint
 * without touching the DB. The four refresh/getCoords calls in
 * renderTutorial() are side effects on $player->coords; the HTML
 * output depends only on $player->id and already-set $player->coords,
 * which the test populates directly.
 */
class LegacyTutorialPlayerStub extends Player
{
    public function __construct()
    {
        // intentionally skip parent — no DB, no services
    }

    public function refresh_view() {}
    public function refresh_data() {}
    public function refresh_caracs() {}

    public function getCoords(bool $refresh = true): object
    {
        return $this->coords;
    }
}
