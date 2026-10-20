<?php

namespace App\View\Hud;

use App\Factory\PlayerFactory;
use App\Service\TurnProcessingService;
use App\View\MainView;
use Classes\Log;

/**
 * Everything the HUD shows after a step, in go.php's own response: the
 * top bar, the minimap and the board (as index.php renders them) and the
 * observation of the new cell. It replaces the requests the page made
 * after the step (index.php, then observe.php), each of which started PHP,
 * the session, Doctrine and reloaded the character again. The events feed
 * stays out: the page asks load_events.php for it on its own, in parallel,
 * and only when something new may show in it. js/hud.js hudApplyMove()
 * places the parts.
 */
final class MoveResponseView
{
    /**
     * $feedLogId: the log the page's events feed is current up to (0: unknown).
     * feedChanged: something newer may show in it.
     *
     * @return array{board: string, feedChanged: bool, observe: string, coords: string}
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

        $coords = $player->getCoords();
        $cell = $coords->x . ',' . $coords->y;

        return [
            'board' => $board,
            'feedChanged' => $feedLogId === 0 || Log::hasFeedEventsAfter($feedLogId),
            'observe' => self::observe($cell),
            'coords' => $cell,
        ];
    }

    /** observe.php's panel for a cell, rendered in a scope of its own (the script works on globals). */
    private static function observe(string $cell): string
    {
        $_POST = ['coords' => $cell];
        ob_start();
        include dirname(__DIR__, 3) . '/observe.php';

        return (string) ob_get_clean();
    }
}
