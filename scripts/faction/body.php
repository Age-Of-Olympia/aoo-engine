<?php

use App\Factory\PlayerFactory;
use App\Service\FactionService;
use App\View\FactionView;
use Classes\Db;

/*
 * Corps de la page de faction (?faction=), partagé entre la page
 * complète (faction.php, enveloppe Ui) et le panneau glissant du HUD
 * (load_faction.php).
 */

$facJson = (new FactionService())->getFactionData($_GET['faction'] ?? '');

if(!$facJson){

    exit('error faction');
}


echo '<h1>'. $facJson->name .'</h1>';

echo '<div style="font-size: 5em;"><span class="ra '. $facJson->raFont .'"></span></div>';


$player = PlayerFactory::legacy($_SESSION['playerId']);
$player->get_data();


if(!empty($facJson->hidden) && !$player->have_option('isAdmin')){

    exit();
}


if(isset($facJson->secret)){

    if($player->data->secretFaction == $_GET['faction'] || $player->have_option('isAdmin')){
        $sql = 'SELECT players.id AS id,avatar,name,race,xp,secretFactionRole as factionRole,plan FROM players INNER JOIN coords ON coords_id = coords.id WHERE nextTurnTime > ? AND secretFaction = ? AND player_type = "real" ORDER BY name';

        $db = new Db();

        $timeLimit = time() - INACTIVE_TIME;

        $res = $db->exe($sql, array($timeLimit, $_GET['faction']));

        FactionView::renderFaction($player,$facJson,$res);

    }else{
        echo "<p>Cette faction est entourée d'un grand mystère, nul ne connait vraiment ses membres.</p>";
    }

}else{

    $sql = 'SELECT players.id AS id,avatar,name,race,xp,factionRole,plan FROM players INNER JOIN coords ON coords_id = coords.id WHERE nextTurnTime > ? AND faction = ? AND player_type = "real" ORDER BY name';

    $db = new Db();

    $timeLimit = time() - INACTIVE_TIME;

    $res = $db->exe($sql, array($timeLimit, $_GET['faction']));

    /* Les gestes de gestion suivent le RANG du visiteur (faction_roles) :
     * la vue ne montre que ce que son drapeau permet, l'endpoint revérifie. */
    $manage = ($player->data->faction === ($_GET['faction'] ?? ''))
        ? (new FactionService())->roleOf((int) $player->id)
        : null;

    FactionView::renderFaction($player,$facJson,$res,$manage);
}

/* Les bâtiments de la faction — ses murs. Réservés à ses MEMBRES, par
 * la règle qui cache déjà le territoire des autres ; l'admin voit tout. */
if ($player->data->faction === ($_GET['faction'] ?? '') || $player->have_option('isAdmin')) {

    FactionView::renderBuildings((new FactionService())->buildingsOf((string) $_GET['faction']));
}
