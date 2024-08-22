<?php

require_once('config.php');

ob_start();

// caracs trio
$trio['pv'] = array(4,2,1);
$trio['ct'] = array(110,50,30);
$trio['f'] = array(120,55,30);
$trio['agi'] = array(95,45,25);
$trio['e'] = array(120,55,30);
$trio['pm'] = array(5,3,1);
$trio['fm'] = array(100,50,30);
$trio['m'] = array(110,55,35);
$trio['a'] = array(800,200,100);
$trio['mvt'] = array(100,50,30);
$trio['r'] = array(40,30,15);
$trio['rm'] = array(50,40,20);
$trio['cc'] = array(100,50,30);
$trio['p'] = array(110,85,78);
$trio['spd'] = array(400,100,50);


// return cost
function return_cost( $progress, $upgraded ){

    $next = $upgraded + 1;

    $total = $progress[0];

    for( $i = 1; $i < $next; $i++ ){

        if( $i < 3 ){

            $total = $total + $progress[1];
        }
        elseif( $i >= 3 ){

            $total = $total + $progress[2];
        }
    }

    return $total;
}


$player = new Player($_SESSION['playerId']);

$player->get_data();

$player->get_row();

$player->get_caracs();


if(!empty($_POST['carac'])){

    include('scripts/upgrades/carac.php');
    exit();
}


if( !empty($_GET['caracTables']) ){


    foreach( CARACS as $e=>$k ){


        if(!isset($trio[$e])){

            continue;
        }


        echo '
        ==== '. $k .' ====
        <br />
        ';


        echo '
        ^    ^ '. implode('/', $trio[ $e ]) .' ^^<br />
        ^ Augm. ^ Coût ^ Coût total ^<br />
        ';


        $total = 0;


        for( $i=1; $i<=12; $i++ ){

            $n = $i - 1;

            $cost = return_cost( $trio[ $e ], $n );
            $total += $cost;

            echo '
            | +'. $i .' | '. $cost .' | '. $total .' |<br />
            ';
        }


        echo '
        <br />
        <br />
        ';
    }



    exit();
}


$ui = new Ui('Améliorations');



echo '
<div><a href="index.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a><a href="upgrades.php"><button>Caractéristiques</button></a><a href="upgrades.php?spells"><button>Sorts</button></a></div>
';


// spells
if(isset($_GET['spells'])){

    include('scripts/upgrades/spells.php');
    exit();
}


echo '
<table class="box-shadow marbre" border="1" align="center">';


echo '<tr><th>Carac.</th><th>Valeur</th><th>Équipé</th><th>Reste</th><th>Coût</th><th><span class="ra ra-archery-target"></span></th></tr>';


foreach(CARACS as $k=>$e){


    if($k == 'ae'){

        continue;
    }


    $cost = return_cost($trio[$k], $player->upgrades->$k);


    $color = 'green';
    $disabled = '';


    if($cost > $player->row->pi){

        $color =  'red';
        $disabled = 'disabled';
    }


    $carac = '';

    if($player->caracs->$k > $player->nude->$k){


        $carac = '<font color="blue">'. $player->caracs->$k .'</font>';
    }
    elseif($player->caracs->$k < $player->nude->$k){


        $carac = '<font color="red">'. $player->caracs->$k .'</font>';
    }


    $turn = '';

    if(isset($player->turn->$k)){

        $turn =  $player->turn->$k;
    }

    if(is_numeric($turn) && $turn < 1){

        $turn = '<font color="red">'. $turn .'</font>';
    }
    elseif(is_numeric($turn) && $turn == $carac){

        $turn = '<font color="blue">'. $turn .'</font>';
    }


    $debuff = '';

    if(!empty($player->debuffs->$k)){

        $debuff = '<span class="ra '. EFFECTS_RA_FONT[$player->debuffs->$k] .'"></span>';
    }


    echo '
    <tr>
        <th>
            '. $e .'
        </th>
        <td>
            '. $player->nude->$k .'
        </td>
        <td>
            '. $carac . $debuff .'
        </td>
        <td>
            '. $turn .'
        </td>
        <td>
            <font color="'. $color .'">'. $cost .'Pi</font>
        </td>
        <td>
            ';

            echo '
            <button
                data-carac="'. $k .'"
                data-carac-name="'. CARACS[$k] .'"
                '. $disabled .'
                class="upgrade"
                >
                +1
            </button>
            ';

            echo '
        </td>
    </tr>
    ';
}

echo '
</table>
';


echo $player->row->pi .' Points d\'investissement (Pi)';


?>
<script src="js/upgrades.js"></script>
<?php


echo Str::minify(ob_get_clean());
