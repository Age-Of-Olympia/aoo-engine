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


/* Le blason SUIT le titre, sur sa ligne — en 5em au-dessus, il
 * mangeait un quart de l'écran avant le premier membre. */
echo '<h1>'. htmlspecialchars((string) $facJson->name, ENT_QUOTES, 'UTF-8')
    .' <span class="ra '. $facJson->raFont .'"></span></h1>';

if (!empty($facJson->text)) {
    echo '<p style="max-width: 640px; margin: 0 auto 1em;"><small>'
        . nl2br(htmlspecialchars((string) $facJson->text, ENT_QUOTES, 'UTF-8'))
        . '</small></p>';
}


$player = PlayerFactory::legacy($_SESSION['playerId']);
$player->get_data();


if(!empty($facJson->hidden) && !$player->have_option('isAdmin')){

    exit();
}


if(isset($facJson->secret)){

    if($player->data->secretFaction == $_GET['faction'] || $player->have_option('isAdmin')){
        $sql = 'SELECT players.id AS id,avatar,name,race,xp,secretFactionRole as factionRole,0 as factionRoleVariant,plan FROM players INNER JOIN coords ON coords_id = coords.id WHERE nextTurnTime > ? AND secretFaction = ? AND player_type = "real" ORDER BY factionRole DESC, name';

        $db = new Db();

        $timeLimit = time() - INACTIVE_TIME;

        $res = $db->exe($sql, array($timeLimit, $_GET['faction']));

        FactionView::renderFaction($player,$facJson,$res);

    }else{
        echo "<p>Cette faction est entourée d'un grand mystère, nul ne connait vraiment ses membres.</p>";
    }

}else{

    $sql = 'SELECT players.id AS id,avatar,name,race,xp,factionRole,factionRoleVariant,plan FROM players INNER JOIN coords ON coords_id = coords.id WHERE nextTurnTime > ? AND faction = ? AND player_type = "real" ORDER BY factionRole DESC, name';

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
 * la règle qui cache déjà le territoire des autres ; l'admin voit tout.
 * Le geste « prendre les commandes » n'appartient qu'aux membres — et
 * l'entité pilotée en ce moment porte le chemin du retour. */
if ($player->data->faction === ($_GET['faction'] ?? '') || $player->have_option('isAdmin')) {

    FactionView::renderBuildings(
        (new FactionService())->buildingsOf((string) $_GET['faction']),
        $player->data->faction === ($_GET['faction'] ?? ''),
        (int) $player->id
    );

    /* Ses coffres, au même titre que ses murs : le contenu pour les
     * yeux que le rang autorise, la serrure tournable d'ici. */
    FactionView::renderContainers(
        (new FactionService())->containersOf((string) $_GET['faction']),
        $player->data->faction === ($_GET['faction'] ?? ''),
        (int) $player->id
    );

    if ($player->data->faction === ($_GET['faction'] ?? '')) {
        FactionView::renderAssetsScript((string) $_GET['faction']);
    }
}
