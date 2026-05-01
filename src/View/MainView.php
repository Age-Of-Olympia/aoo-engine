<?php

namespace App\View;

use Classes\Player;
use Classes\View;



class MainView
{
    public static function render(Player $player): void
    {


        if (!empty($_SESSION['playerId'])) {


            if (isset($_GET['tutorial'])) {
                TutorialView::renderTutorial($player);
            }


            $msgUrl = 'datas/private/players/' . $player->id . '.msg.html';

            if (file_exists($msgUrl)) {

                $data = file_get_contents($msgUrl);

                echo '<div id="view-landing-wrapper"><div id="view-landing-msg">' . $data . '<div id="seal"></div></div></div>';
            }


            $svgUrl = 'datas/private/players/' . $player->id . '.svg';

            if (!file_exists($svgUrl)) {

                $coords = $player->getCoords();


                $caracsJson = json()->decode('players', $player->id . '.caracs');

                if (!$caracsJson) {

                    $player->get_caracs();

                    $p = $player->caracs->p;
                } else {

                    $p = $caracsJson->p;
                }


                $playerOptions = $player->get_options();

                $view = new View($coords, $p, tiled: false, options: $playerOptions, playerId: $player->id);

                $data = $view->get_view();

                /* Defensive cache guard: only persist the SVG when it
                 * actually contains map cells. View::get_view() returns
                 * null when its inSightId guard fires (e.g. transient
                 * coords/state issue on a brand-new player's first
                 * arrival), and writing that null payload caches a
                 * permanently-gray map for the player until the
                 * "Rafraichir la Vue" account option is used.
                 *
                 * Heuristic: the rendered SVG always contains
                 * `class="case"` attributes when at least one tile is
                 * in sight. Missing the substring → empty / degenerate
                 * render → skip the cache write. The page still echoes
                 * what we got; the next request retries the render. */
                $svgIsRenderable = is_string($data) && strpos($data, 'class="case"') !== false;

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
            <script src="js/admin-tools.js?v=20260413"></script>
            <script src="js/blocked-tiles.js?v=20260501c"></script>
            <script src="js/view.js?v=20260430d"></script>
<?php
        }
    }
}
