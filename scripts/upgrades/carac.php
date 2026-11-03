<?php
use App\Service\PlayerCaracsService;
use App\Service\ProgressionService;
if(!isset(CARACS[$_POST['carac']])){

    exit('error carac');
}


$k = $_POST['carac'];


if($k == 'spd'){

    exit('error carac: spd');
}

// Only caracs with a cost grid are bought (Ae, Ac, Sac are not).
$caracsService = new PlayerCaracsService();
if($caracsService->getUpgradeProgress($k) === null){

    exit('error carac: '. $k);
}


$cost = $caracsService->returnCost($k, $player->upgrades->$k);


// The balance is checked by the debit itself: two requests arriving together
// must not both take the last Pi.
if(!(new ProgressionService())->spendPi((int) $player->id, $cost)){

    exit('Pas assez de Pi.');
}


$player->put_upgrade($k,$cost);


exit('Vous avez augmenté '. CARACS[$k] .' pour '. $cost .'Pi.');
