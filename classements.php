<?php


define('NO_LOGIN', true);


require_once('config.php');


function print_players($list){

    echo '
    <table border="1" align="center" class="marbre" cellspacing="0">
    <tr>
        <th>#</th>
        <th>Nom</th>
        <th>Mat.</th>
        <th>Réputation</th>
        <th>Xp</th>
        <th>Rang</th>
        ';

        if(isset($list[0]->gold)){

            echo '<th>Or</th>';
        }
        elseif(isset($list[0]->showReput)){

            echo '<th>Pr</th>';
        }

        echo '
    </tr>
    ';

    $n = 1;

    foreach($list as $player){

        $raceJson = json()->decode('races', $player->race);

        $reput = Str::get_reput($player->pr);

        echo '
        <tr style="color: '. $raceJson->color .'; background: '. $raceJson->bgColor .'">
            <td align="center">'. $n .'</td>
            <td style="white-space: nowrap;">'. $player->name .'</td>
            <td align="center"><a href="infos.php?targetId='. $player->id .'">mat.'. $player->id .'</a></td>
            <td><a href="infos.php?targetId='. $player->id .'&reputation">'. $reput .'</a></td>
            <td align="center">'. $player->xp .'</td>
            <td align="center">'. $player->rank .'</td>
            ';

            if(isset($player->gold)){

                echo '<td align="center">'. $player->gold .'</td>';
            }
            elseif(isset($list[0]->showReput)){

                echo '<td align="center">'. $player->pr .'</td>';
            }

            echo '
        </tr>
        ';

        $n++;
    }

    echo '
    </table>
    ';
}


$list = json()->decode('players', 'list');

if(!$list){


    // refresh all classements (once per day, done with cron)

    Player::refresh_list();

    $list = json()->decode('players', 'list');


    @unlink('datas/public/classements/general.html');
    @unlink('datas/public/classements/bourrins.html');
    @unlink('datas/public/classements/reputation.html');
    @unlink('datas/public/classements/fortunes.html');
}

// enlever les pnj
foreach($list as $k=>$e){
    if($e->id < 0)
        unset($list[$k]);
    if($e->race == 'dieu')
        unset($list[$k]);
}

$ui = new Ui('Classements des joueurs');


echo '<div><a href="index.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a><a href="classements.php"><button>Général</button></a><a href="classements.php?bourrins"><button>Bourrins</button></a><a href="classements.php?fortunes"><button>Fortunes</button></a><a href="classements.php?reputation"><button>Réputation</button></a></div>';


if(isset($_GET['bourrins'])){

    include('scripts/classements/bourrins.php');

    exit();
}

if(isset($_GET['fortunes'])){

    include('scripts/classements/fortunes.php');

    exit();
}

if(isset($_GET['reputation'])){

    include('scripts/classements/reputation.php');

    exit();
}


echo '<h1>Classement Général</h1>';


// Fonction de comparaison pour trier par "pr" (Power Rank)
function compareByXp($a, $b) {
    return $b->xp - $a->xp; // Tri décroissant
}

// Trier le tableau en utilisant la fonction de comparaison
usort($list, 'compareByXp');


$path = 'datas/public/classements/general.html';

if(file_exists($path) && CACHED_CLASSEMENTS){


    echo file_get_contents($path);
}

else{


    ob_start();

    print_players($list);

    $data = ob_get_clean();

    $myfile = fopen($path, "w") or die("Unable to open file!");
    fwrite($myfile, $data);
    fclose($myfile);

    echo $data;


    foreach($list as $e){

        $first = $e;
        break;
    }


    $data = '
    ~'. count($list) .' joueurs actifs<br />
    <a href="infos.php?targetId='. $first->id .'">'. $first->name .'</a> domine le <a href="classements.php">classement</a>!
    ';

    $path = 'datas/public/classements/stats.html';

    $myfile = fopen($path, "w") or die("Unable to open file!");
    fwrite($myfile, $data);
    fclose($myfile);
}
