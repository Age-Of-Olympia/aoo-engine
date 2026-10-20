<?php

namespace App\View\Hud;

use App\Factory\PlayerFactory;
use App\Service\TurnProcessingService;
use App\View\MainView;
use Classes\Log;

/**
 * Everything the HUD shows after a step, in go.php's own response: the
 * top bar, the minimap and the board, as index.php renders them, instead
 * of reloading the page. The new cell's observation and the events feed
 * stay out: the page asks observe.php and load_events.php for them on its
 * own, in parallel, so the board does not wait for them. js/hud.js
 * hudApplyMove() places the parts.
 */
final class MoveResponseView
{
    /**
     * $feedLogId: the log the page's events feed is current up to (0: unknown).
     * feedChanged: something newer may show in it.
     *
     * @return array{board: string, feedChanged: bool}
     */
    public static function render(int $feedLogId = 0): array
    {
        // A fresh read of the character: the step has just moved it
        $player = PlayerFactory::active();
        $player->get_data();

        // As index.php does before drawing: a turn that fell due is processed
        (new TurnProcessingService())->processIfDue($player);

        ob_start();
        TopBarView::render($player);
        MinimapView::render($player);
        MainView::render($player);
        $board = (string) ob_get_clean();

        return [
            'board' => $board,
            'feedChanged' => $feedLogId === 0 || Log::hasFeedEventsAfter($feedLogId),
        ];
    }
}
