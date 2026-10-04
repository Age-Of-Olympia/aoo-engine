<?php

namespace Tests\Support;

use App\Service\Map\BoardChanges;
use Classes\Player;
use Classes\View;

/** A board as MainView leaves it: the cached SVG plus the area it shows (board_views). */
final class CachedBoard
{
    /** @return string path of the cached board */
    public static function drawnFor(int $playerId, int $perception = 10, string $content = '<svg/>'): string
    {
        $player = new Player($playerId);
        BoardChanges::drawn($playerId, (new View($player->getCoords(), $perception, playerId: $playerId))->area());

        $file = Player::cachePath($playerId, '.svg');
        file_put_contents($file, $content);
        clearstatcache(true, $file);

        return $file;
    }
}
