<?php

use App\Service\RaceService;
use App\View\Classement\BourrinsView;
use App\View\Classement\PlayersTableView;
use App\View\Classement\FortunesView;
use App\View\Classement\ReputationsView;
use App\View\Classement\FoiView;
use Classes\Player;
use Classes\Str;

/*
 * Corps des classements, partagé entre la page complète
 * (classements.php, enveloppe Ui) et le panneau glissant du HUD
 * (load_classements.php). Les onglets restent des liens
 * classements.php : le routeur de panneaux (js/hud.js) les
 * réécrit en fragments, la page complète les suit tels quels.
 */


$playerList = Player::get_player_list()->list;

// enlever les pnj
foreach($playerList as $k=>$e){
    if($e->id <= 1 || $e->lastLoginTime < time() - INACTIVE_TIME)
        unset($playerList[$k]);
}



echo '<div><a href="index.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a><a href="classements.php"><button>Général</button></a><a href="classements.php?bourrins"><button>Bourrins</button></a><a href="classements.php?fortunes"><button>Fortunes</button></a><a href="classements.php?reputation"><button>Réputation</button></a><a href="classements.php?foi"><button>Foi</button></a></div>';


if(isset($_GET['bourrins'])){
    BourrinsView::renderBourrins($playerList);
    exit();
}

if(isset($_GET['fortunes'])){
    FortunesView::renderFortunes($playerList);
    exit();
}

if(isset($_GET['reputation'])){
    ReputationsView::renderReputations($playerList);
    exit();
}

if(isset($_GET['foi'])){
    FoiView::renderFoi();
    exit();
}


echo '<h1>Classement Général</h1>';


// Fonction de comparaison pour trier par "pr" (Power Rank)
function compareByXp($a, $b) {
    return $b->xp - $a->xp; // Tri décroissant
}

// Trier le tableau en utilisant la fonction de comparaison
usort($playerList, 'compareByXp');


$path = 'datas/public/classements/general.html';

if(file_exists($path) && CACHED_CLASSEMENTS){


    echo file_get_contents($path);
}

else{


    ob_start();

    PlayersTableView::render($playerList);

    $data = ob_get_clean();

    $myfile = fopen($path, "w") or die("Unable to open file!");
    fwrite($myfile, $data);
    fclose($myfile);

    echo $data;


    if (!empty($playerList)) {
        // Même règle que Player::refresh_list : les personnages d'une race
        // cachée ne fournissent pas le « premier joueur » affiché.
        $raceService = new RaceService();
        $first = null;
        foreach($playerList as $e){

            $race = $raceService->getRaceByName($e->race);
            if($race !== null && $race->getHidden()){
                continue;
            }

            $first = $e;
            break;
        }


        if ($first !== null) {
            $data = '
            ~'. count($playerList) .' joueurs actifs<br />
            <a href="infos.php?targetId='. $first->id .'">'. $first->name .'</a> domine le <a href="classements.php">classement</a>!
            ';

            $path = 'datas/public/classements/stats.html';

            $myfile = fopen($path, "w") or die("Unable to open file!");
            fwrite($myfile, $data);
            fclose($myfile);
        }
    }
}
