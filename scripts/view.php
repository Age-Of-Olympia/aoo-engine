<?php

if(!empty($_SESSION['playerId'])){


    if(isset($_GET['tutorial'])){


        include('scripts/tutorial.php');
    }


    $msgUrl = 'datas/private/players/'. $_SESSION['playerId'] .'.msg.html';


    if(file_exists($msgUrl)){

        $data = file_get_contents($msgUrl);

        echo '<div id="view-landing-wrapper"><div id="view-landing-msg">'. $data .'<div id="seal"></div></div></div>';
    }


    $svgUrl = 'datas/private/players/'. $_SESSION['playerId'] .'.svg';


    if(!file_exists($svgUrl)){

        // coords
        $db = new Db();

        $player = new Player($_SESSION['playerId']);

        $coords = $player->get_coords();


        $caracsJson = json()->decode('players', $player->id .'.caracs');

        if(!$caracsJson){

            $player->get_caracs();

            $p = $player->caracs->p;
        }
        else{

            $p = $caracsJson->p;
        }


        $playerOptions = $player->get_options();

        $view = new View($coords, $p, $tiled=false, $playerOptions);

        $data = $view->get_view();

        $myfile = fopen($svgUrl, "w") or die("Unable to open file!");
        fwrite($myfile, $data);
        fclose($myfile);

        echo $data;

        echo '<!--sup>La vue a été rafraîchie!</sup-->';
    }

    else{

        echo file_get_contents($svgUrl);
    }

    echo '<div id="ajax-data"></div>';


    ?>
    <script src="js/view.js"></script>
    <?php
}
