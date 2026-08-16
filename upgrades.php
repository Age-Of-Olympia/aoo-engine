<?php
use App\Factory\PlayerFactory;
use App\Service\PlayerCaracsService;
use App\View\UpgradesView;
use Classes\Ui;
use Classes\Str;

require_once('config.php');

ob_start();


$player = PlayerFactory::legacy($_SESSION['playerId']);

$player->get_data();

$player->get_row();

$player->get_caracs();


if(!empty($_POST['carac'])){

    include('scripts/upgrades/carac.php');
    exit();
}


if( !empty($_GET['caracTables']) ){


    $caracsService = new PlayerCaracsService();


    foreach( CARACS as $e=>$k ){


        $progress = $caracsService->getUpgradeProgress($e);


        if($progress === null){

            continue;
        }


        echo '
        ==== '. $k .' ====
        <br />
        ';


        echo '
        ^    ^ '. implode('/', $progress) .' ^^<br />
        ^ Augm. ^ Coût ^ Coût total ^<br />
        ';


        $total = 0;


        for( $i=1; $i<=12; $i++ ){

            $n = $i - 1;

            $cost = $caracsService->returnCost($e, $n);
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


UpgradesView::render($player);

echo Str::minify(ob_get_clean());
