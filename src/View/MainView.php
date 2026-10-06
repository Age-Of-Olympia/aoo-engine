<?php

namespace App\View;

use Classes\Player;
use Classes\View;



class MainView
{
    /**
     * Does this render hold map cells? The grid writes `class="case "` or
     * `class="case go"` (View::get_view), never a bare `class="case"`.
     */
    public static function isRenderable(?string $svg): bool
    {
        return $svg !== null && preg_match('/class="case[ "]/', $svg) === 1;
    }

    public static function render(Player $player): void
    {


        if (!empty($_SESSION['playerId'])) {


            $msgUrl = 'datas/private/players/' . $player->id . '.msg.html';

            if (file_exists($msgUrl)) {

                $data = file_get_contents($msgUrl);

                echo '<div id="view-landing-wrapper"><div id="view-landing-msg">' . $data . '<div id="seal"></div></div></div>';
            }


            $svgUrl = 'datas/private/players/' . $player->id . '.svg';

            /* Redrawn when the renderer has changed since this board was
             * cached. The cache has no expiry, so a player who does not move
             * would otherwise keep a board drawn by code that is gone. */
            if (\App\Service\Map\BoardRenderStamp::isStale($svgUrl)) {

                $coords = $player->getCoords();

                $player->get_caracs();

                $p = $player->caracs->p;


                $playerOptions = $player->get_options();

                $view = new View($coords, $p, tiled: false, options: $playerOptions, playerId: $player->id);

                $data = $view->get_view();

                /* Recorded even when the cache write below is skipped: the
                 * area is what change notices are matched against. */
                if (is_string($data)) {
                    \App\Service\Map\BoardChanges::drawn((int) $player->id, $view->area());
                }

                /* Only a render with map cells is cached: View::get_view()
                 * returns null or a degenerate board on a transient state
                 * (a brand-new player's first arrival), which would stay
                 * cached as a grey map. */
                $svgIsRenderable = self::isRenderable($data);

                if ($svgIsRenderable) {
                    $myfile = fopen($svgUrl, "w") or die("Unable to open file!");
                    fwrite($myfile, $data);
                    fclose($myfile);
                } else {
                    error_log(sprintf(
                        '[MainView] Skipping empty SVG cache write for player %d at coords (%s,%s,%s) on plan %s',
                        $player->id,
                        $coords->x ?? '?',
                        $coords->y ?? '?',
                        $coords->z ?? '?',
                        $coords->plan ?? '?'
                    ));
                }

                echo '<div id="game-map" data-map-hash="' . md5((string) $data) . '">' . $data . '</div>';
            } else {

                $svgContent = file_get_contents($svgUrl);
                echo '<div id="game-map" data-map-hash="' . md5($svgContent) . '">' . $svgContent . '</div>';
            }

            echo '<div id="ajax-data"></div>';
            echo '<div id="admin-coords"></div>';

            // Pass admin status to JavaScript for coordinate tool
            $isAdmin = $player->have_option('isAdmin') ? 'true' : 'false';
            echo '<script>window.isAdmin = ' . $isAdmin . ';</script>';

            // Player display option: red × on blocked tiles. Tutorial
            // does its own scoped rendering, so suppress the global
            // option while the tutorial session is active to avoid
            // double-marking.
            $showBlockedTiles = $player->have_option('showBlockedTiles')
                && empty($_SESSION['in_tutorial'])
                ? 'true' : 'false';
            echo '<script>window.showBlockedTiles = ' . $showBlockedTiles . ';</script>';

?>
            <script src="js/admin-tools.js?v=20260715"></script>
            <script src="js/blocked-tiles.js?v=20260727"></script>
            <script src="js/view.js?v=20260927c"></script>
<?php
        }
    }
}
