<?php
use App\Entity\Structure;
use App\Factory\PlayerFactory;
use App\View\InfosSheetView;
use App\View\StructureSheetView;
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

    $ui = new Ui($target->data->name .' (réputation)');

    include('scripts/infos/reputation.php');

    exit();
}
if(isset($_GET['rewards'])){

    $ui = new Ui($target->data->name .' (réputation)');

    include('scripts/infos/rewards.php');

    exit();
}


// Hydrate an entity alongside the legacy $target for
// read paths. The legacy object stays for anything downstream code
// or the included sub-scripts still rely on (->coords, ->get_caracs,
// Item::get_equiped_list). The entity powers every pure data read.
// Racine STI : la cible peut être un personnage OU une structure
// (bâtiment, objet unique) — chacun sa fiche.
$targetEntity = PlayerFactory::gameEntity((int) $target->id);
if ($targetEntity === null) {
    exit('error target id');
}

$ui = new Ui($targetEntity->getName());

if ($targetEntity instanceof Structure) {
    StructureSheetView::render($player, $targetEntity);
} else {
    InfosSheetView::render($player, $targetEntity);
}
