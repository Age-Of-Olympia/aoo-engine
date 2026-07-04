<?php
use App\Factory\PlayerFactory;
use App\View\InfosSheetView;
use Classes\Ui;

require_once('config.php');


if(!isset($_GET['targetId']) || !is_numeric($_GET['targetId'])){

    exit('error target id');
}

$player = PlayerFactory::active();
$player->get_data();


$target = PlayerFactory::legacy($_GET['targetId']);
$target->get_data();


if(isset($_GET['reputation'])){

    include('scripts/infos/reputation.php');

    exit();
}
if(isset($_GET['rewards'])){

    include('scripts/infos/rewards.php');

    exit();
}


// Phase 4.3d — hydrate an entity alongside the legacy $target for
// read paths. The legacy object stays for anything downstream code
// or the included sub-scripts still rely on (->coords, ->get_caracs,
// Item::get_equiped_list). The entity powers every pure data read.
$targetEntity = PlayerFactory::entity((int) $target->id);
if ($targetEntity === null) {
    exit('error target id');
}

$ui = new Ui($targetEntity->getName());

InfosSheetView::render($player, $targetEntity);
