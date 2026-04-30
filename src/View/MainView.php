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

                $myfile = fopen($svgUrl, "w") or die("Unable to open file!");
                fwrite($myfile, $data);
                fclose($myfile);

                echo '<div id="game-map" data-map-hash="' . md5($data) . '">' . $data . '</div>';
            } else {

                $svgContent = file_get_contents($svgUrl);
                echo '<div id="game-map" data-map-hash="' . md5($svgContent) . '">' . $svgContent . '</div>';
            }

            echo '<div id="ajax-data"></div>';
            echo '<div id="admin-coords"></div>';

            // Pass admin status to JavaScript for coordinate tool
            $isAdmin = $player->have_option('isAdmin') ? 'true' : 'false';
            echo '<script>window.isAdmin = ' . $isAdmin . ';</script>';

?>
            <script src="js/admin-tools.js?v=20260413"></script>
            <script src="js/view.js?v=20260430"></script>
<?php
        }
    }
}
