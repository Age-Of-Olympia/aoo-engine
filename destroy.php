<?php
use App\Factory\PlayerFactory;
use Classes\Db;
use Classes\View;
use Classes\Log;

require_once('config.php');

$player = PlayerFactory::active();


if(!isset($_POST['wallId'])){

    exit('error wall id');
}

$wallId = preg_replace('/[^0-9]/', '', $_POST['wallId']);


if($player->getRemaining('a') < 1){

    exit('Pas assez d\'Actions.');
}


$sql = '
SELECT
*,
map_resources.id AS id
FROM
map_resources
INNER JOIN
coords
ON
coords.id = map_resources.coords_id
WHERE
map_resources.id = ?
';

$db = new Db();

$res = $db->exe($sql, $wallId);


if(!$res->num_rows){

    exit('error wall');
}

$row = $res->fetch_object();


$wallCoords = (object) array(
    'x'=>$row->x,
    'y'=>$row->y,
    'z'=>$row->z,
    'plan'=>$row->plan
);


$distance = View::get_distance($player->getCoords(), $wallCoords);


if($distance > 1){

    exit('error distance');
}


$pvMax = App\Service\ResourceTypeService::pv($row->name);

if($pvMax === null){

    exit('Cet objet est indestructible!');
}

// Si les PV sont inférieurs à 0, il s'agit d'une ressource indestructible)
if($pvMax < 0){

    exit('Cet objet est indestructible!');
}


$player->get_caracs();

$main1 = $player->emplacements->main1;


if($main1->data->name == 'poings'){

    exit('Impossible de détruire un objet avec les Poings.');
}

if($main1->data->subtype != 'melee'){

    exit('Il faut une arme de mêlée pour détruire cet objet.');
}


$damages = $player->caracs->f;

if(!empty($main1->data->demolition)){

    $damages += $main1->data->demolition;
}


$name = $row->name;

/* Bascule visuelle « brisé » (capacité restaurée — la condition était
 * inversée et testait x_broken_broken.png, jamais vrai) : passé la
 * moitié de ses PV, la structure affiche son image _broken quand elle
 * existe. Double repli : pas d'image _broken OU pas d'entrée au catalogue
 * resource_types pour le nom _broken (la garde du prochain coup en a
 * besoin) → elle garde son image et son nom d'origine. */
if(strpos($row->name, '_broken') === false
    && ($row->damages + $damages) >= ceil($pvMax / 2)
    && App\Service\ResourceTypeService::pv($row->name .'_broken') !== null
    && file_exists('img/walls/'. $row->name .'_broken.png')){

    $name = $row->name .'_broken';

    $refresh = true;
}

$sql = 'UPDATE map_resources SET name = ?, damages = damages + ? WHERE id = ?';

$db->exe($sql, array($name, $damages, $row->id));

$itemJson = json()->decode('items', $row->name);
if($itemJson)
{
    $text = $player->data->name .' a attaqué '.$itemJson->name;
}
else
{
    $text = $player->data->name .' a attaqué une structure';
}


if($row->damages + $damages >= $pvMax){


    $db->delete('map_resources', array('id'=>$row->id));

    $refresh = true;

    $text .= ' et l\'a détruite';
}

$text .='.'; 



if(!empty($refresh) && $refresh){


    // refresh_view
    View::refresh_players_svg($player->coords);
}


$player->putBonus($bonus=array('a'=>-1));

$player->put_xp(1);


Log::put($player, $player, $text, type:"destroy");


echo 'Vous infligez '. $damages .' dégâts (+1Xp).';
