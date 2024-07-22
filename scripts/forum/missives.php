<?php

$destTbl = Forum::get_top_dest($topJson);

if (!in_array($player->id, $destTbl)) {
    exit('Accès refusé');
}

if (!empty($_POST['addDest'])) {
    if (strpos($_POST['addDest'], 'all_faction_') === 0) {
        $faction = substr($_POST['addDest'], strlen('all_faction_'));
        if ($player->data->faction == $faction || $player->data->secretFaction == $faction){
            $sql = 'SELECT id FROM players where ( faction = ? or secretFaction = ?) ORDER BY name';

            $db = new Db();

            $timeLimit =time() - INACTIVE_TIME;

            $res = $db->exe($sql, array($faction, $faction));

            while($row = $res->fetch_object()){
                Forum::add_dest($row->id,  $topJson, $destTbl)  ;
            }
        }
    }else{
      Forum::add_dest($_POST['addDest'], $topJson, $destTbl)  ;
    }

    exit();
}

if (!empty($_POST['removeDest'])) {
    Forum::remove_dest($_POST['removeDest'], $topJson, $destTbl);
    exit();
}

echo '
<div class="dest-container">
    <div id="dest">
        Destinataires:
        ';

        foreach ($destTbl as $e) {


            $dest = new Player($e);
            $dest->get_data();

            $raceJson = json()->decode('races', $dest->data->race);

            echo '
            <span
                data-id="' . $dest->id . '"
                class="cartouche dest"
                style="background: ' . $raceJson->bgColor . '; color: ' . $raceJson->color . ';"
                >
                ' . $dest->data->name . '
            </span>
            ';
        }

        $blink = (count($destTbl) == 1) ? 'blink' : '';

        echo '
        <span
        id="add-dest"
        class="cartouche ' . $blink . '"
        style="background: #aaa;"
        >
            +Ajouter
        </span>
    </div>';


    $playersJson = json()->decode('players', 'list');


    if (!$playersJson) {

        Player::refresh_list();
        $playersJson = json()->decode('players', 'list');
    }

    echo '<select id="dest-list">
        <option disabled selected>Sélectionnez un personnage:</option>';


    $secretFaction = array();
    $faction = array();


    foreach ($playersJson as $e) {

        if($e->secretFaction == $player->data->secretFaction){

            $secretFaction[] = $e;

        }elseif($e->faction == $player->data->faction){

            $faction[] = $e;

        }else{
            $raceJson = json()->decode('races', $e->race);

            echo '<option value="'. $e->id .'">- '. $e->name .' '. $raceJson->name .'</option>';
        }
    }

    $factionJson = json()->decode('factions', $player->data->faction);

    echo '<option value="all_faction_'.$player->data->faction.'">'. $factionJson->name .' (tous les membres)</option>';

    foreach($faction as $e){


        $raceJson = json()->decode('races', $e->race);

        echo '<option value="'. $e->id .'">- '. $e->name .' '. $raceJson->name .'</option>';
    }


    $secretJson = json()->decode('factions', $player->data->secretFaction);

    echo '<option value="all_faction_'.$player->data->secretFaction.'">'. $secretJson->name .' (tous les membres)</option>';

    foreach($secretFaction as $e){


        $raceJson = json()->decode('races', $e->race);

        echo '<option value="'. $e->id .'">- '. $e->name .' '. $raceJson->name .'</option>';
    }


    echo '<option disabled>Animateurs:</option>';

    foreach(RACES_EXT as $e){


        $raceJson = json()->decode('races', $e);

        echo '<option value="'. $raceJson->animateur .'">- Animateur: '. $raceJson->name .'</option>';
    }


    echo '</select>';

echo '</div>';


if(count($destTbl) == 1){


    echo '<div style="color: blue; text-align: left; font-size: 88%; margin: 10px;">Vous pouvez maintenant sélectionner un ou plusieurs destinataires de votre faction/peuple.<br />Pour envoyer un message à un personnage d\'un autre peuple, sélectionnez l\'animateur de son peuple, tout en bas de la liste.<br />Ce dernier invitera ce personnage dans la discussion.<br />
    <font color="red">Les Missives sans destinataires sont automatiquement supprimées.</font></div>';
}


?>

<script>
$(document).ready(function() {


    $('.post-rewards').addClass('desaturate');


    $('#add-dest').click(function(e) {


        $('#dest').hide();
        $('#dest-list').show();
    });


    $('#dest-list').on('change', function(e) {


        var dest = $(this).val();

        $.ajax({
            type: "POST",
            url: 'forum.php?topic=<?php echo $topJson->name ?>',
            data: {'addDest': dest},
            success: function(data) {
              //debugger;
              document.location.reload();
            }
        });
    });


    $('.dest').click(function(e) {


        var dest = $(this).data('id');

        if (!confirm('Supprimer ce personnage de la conversation?')) {
            return false;
        }

        $.ajax({
            type: "POST",
            url: 'forum.php?topic=<?php echo $topJson->name ?>',
            data: {'removeDest': dest},
            success: function(data) {
                document.location.reload();
            }
        });
    });
});
</script>
