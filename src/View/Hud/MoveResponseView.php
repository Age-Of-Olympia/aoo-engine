<?php

namespace App\View\Hud;

use App\Factory\PlayerFactory;
use App\Service\TurnProcessingService;
use App\View\MainView;

/**
 * Everything the HUD shows after a step, in go.php's own response: the
 * top bar, the minimap and the board (as index.php renders them), the
 * events feed and the observation of the new cell. It replaces the three
 * requests the page made after the step (index.php, then observe.php and
 * load_events.php), each of which started PHP, the session, Doctrine and
 * reloaded the character again. js/hud.js hudApplyMove() places the parts.
 */
final class MoveResponseView
{
    /** @return array{board: string, events: string, observe: string, coords: string} */
    public static function render(): array
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
            'events' => FeedRenderer::renderEvents($player),
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
