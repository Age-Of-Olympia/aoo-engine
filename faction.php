<?php


require_once('config.php');


if(!empty($_GET['faction'])){

    $facJson = json()->decode('factions', $_GET['faction']);


    if(!$facJson){

        exit('error faction');
    }


    $ui = new Ui('Faction: '. $facJson->name);

    echo '<div><a href="index.php"><button><span class="ra ra-sideswipe"> Retour</button></a></div>';


    echo '<h1>'. $facJson->name .'</h1>';

    echo '<div style="font-size: 5em;"><span class="ra '. $facJson->raFont .'"></span></div>';


    if(!empty($facJson->hidden)){

        exit();
    }


    if(isset($facJson->secret)){
        $player = new Player($_SESSION['playerId']);
        $player->get_data();
        if($player->data->secretFaction == $_GET['faction'] || $player->have_option('isAdmin')){
            $sql = 'SELECT players.id AS id,avatar,name,race,xp,secretFactionRole as factionRole,plan FROM players INNER JOIN coords ON coords_id = coords.id WHERE nextTurnTime > ? AND secretFaction = ? ORDER BY name';

            $db = new Db();

            $timeLimit =time() - INACTIVE_TIME;

            $res = $db->exe($sql, array($timeLimit, $_GET['faction']));

            include('scripts/faction_list.php');

        }else{
            echo "<p>Cette faction est entourée d'un grand mystère, nul ne connait vraiment ses membres.</p>";
        }

    }else{

        $sql = 'SELECT players.id AS id,avatar,name,race,xp,factionRole,plan FROM players INNER JOIN coords ON coords_id = coords.id WHERE nextTurnTime > ? AND faction = ? ORDER BY name';

        $db = new Db();

        $timeLimit =time() - INACTIVE_TIME;

        $res = $db->exe($sql, array($timeLimit, $_GET['faction']));

        include('scripts/faction_list.php');
    }


}
else{

    $ui = new Ui('Factions');
}





