<?php


define('CARACS_BOURRINS', array(
    'pv'=>"Increvables! Si si je vous jure!",
    'cc'=>"Les meilleurs maîtres lames.",
    'ct'=>"Les meilleurs tireurs.",
    'f'=>"Ils ont des bras comme vos jambes!",
    'e'=>"Ils sont plus solides que le roc!",
    'agi'=>"Rapides comme l'éclair!",

    'a'=>"Les plus grands moulins à baffes!",
    'mvt'=>"Ils volent plus qu'ils ne marchent...",
    'p'=>"Rien n'échappe à leur regard!",

    'pm'=>"Leur magie est inépuisable!",
    'fm'=>"Leur esprit est une forteresse!",
    'm'=>"Les plus puissants magiciens!",

    'r'=>"Les plus résistants!",
    'rm'=>"Des mineurs de mana",
    'spd'=>"Les meilleurs marathoniens."
));


function print_best_carac($carac, $bestCarac){


    echo '
    <table border="1" align="center" class="dialog-table">
        <tr>
            <th>#</th>
            <th>Nom</th>
            <th>Mat.</th>
            <th>Réputation</th>
            <th>Xp</th>
            <th>Rang</th>
        </tr>
        ';

    $tbl = $bestCarac[$carac];

    krsort($tbl);


    $n = 1;

    $i = 1;


    foreach($tbl as $k=>$e){


        $playerTbl = $e;


        foreach($playerTbl as $e){


            $raceJson = json()->decode('races', $e->data->race);


            echo '
            <tr style="background: '. $raceJson->bgColor .'; color: '. $raceJson->color .'">
                <td>'. $n .'</td>
                <td>
                    '. $e->data->name .'
                </td>
                <td align="center" valign="top"><a href="infos.php?targetId='. $e->id .'">mat.'. $e->id .'</a></td>
                <td align="center" valign="top">'. Str::get_reput($e->data->pr) .'</td>
                <td align="center" valign="top">'. $e->data->xp .'</td>
                <td align="center" valign="top">'. $e->data->rank .'</td>
            </tr>
            ';


            // max 5
            if($i >= 5)
                break;

            $i++;
        }


        // max 5
        if($i >= 5)
            break;


        // stop when 3 best
        if($n >= 3)
            break;

        $n++;
    }

    echo '
    </table>
    ';
}


echo '<h1>Classement des Bourrins</h1>';


$path = 'datas/public/classements/bourrins.html';

if(file_exists($path)){


    echo file_get_contents($path);
}

else{


    $bestCarac = array();


    foreach($list as $player){


        $player = new Player($player->id);

        $player->get_data();

        $player->get_caracs($nude=true);


        foreach(CARACS as $k=>$e){

            // first entry
            if(!isset($bestCarac[$k])){


                $bestCarac[$k] = array(
                    $player->caracs->$k => array($player)
                );

                continue;
            }


            // add entry
            $bestCarac[$k][$player->caracs->$k][] = $player;
        }
    }


    ob_start();


    foreach(CARACS as $k=>$e){


        if(!isset(CARACS_BOURRINS[$k])){

            continue;
        }

        echo '<h2>'. CARACS_BOURRINS[$k] .' ('. $e .')</h2>';

        print_best_carac($k, $bestCarac);
    }


    $data = ob_get_clean();

    $myfile = fopen($path, "w") or die("Unable to open file!");
    fwrite($myfile, $data);
    fclose($myfile);

    echo $data;
}
