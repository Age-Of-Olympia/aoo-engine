<?php

require_once('config.php');

$ui = new Ui('Personnages secondaires');


echo '<div><a href="index.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a></div>';


$db = new Db();

$res = $db->get_single_player_id('players_pnjs', $_SESSION['mainPlayerId']);

if(!$res->num_rows){

    // exit('Vous n\'avez pas de personnage secondaire.<br />Vous pouvez en faire la demande auprès d\'un Animateur.');
}


$main = new Player($_SESSION['mainPlayerId']);


$playersTbl = array(
    $main->id=>$main
);


while($row = $res->fetch_object()){


    $playersTbl[$row->pnj_id] = new Player($row->pnj_id);
}


if(!empty($_POST['switch'])){


    if(!isset($playersTbl[$_POST['switch']])){

        exit('error pnj');
    }


    $_SESSION['playerId'] = $_POST['switch'];
    exit();
}


echo '
<table border="1" align="center" class="marbre">
<tr>
';

foreach($playersTbl as $pnj){


    $pnj->get_data();


    $effectsTbl = array();

    foreach($pnj->get_effects() as $e){

        $effectsTbl[] = '<span class="ra '. EFFECTS_RA_FONT[$e] .'"></span>';
    }


    $raceJson = json()->decode('races', $pnj->data->race);


    echo '
    <td align="center" class="pnj" data-id="'. $pnj->id .'"><div style="position: relative; cursor: pointer;"><div style="position: absolute; top: 0; right: 0;">'. implode('<br />', $effectsTbl) .'</div><img class="portrait" src="'. $pnj->data->portrait .'" width="150" /><br />'. $pnj->data->name .'<br /><span style="font-size: 88%;">mat.'. $pnj->id .'<br />'. $raceJson->name .' Rang '. $pnj->data->rank .'</span></div></td>
    ';
}


echo '
</tr>
</table>
';


?>
<script>
$(document).ready(function(){

    $('.pnj').click(function(e){


        $.ajax({
            type: "POST",
            url: 'pnjs.php',
            data: {'switch':$(this).data('id')}, // serializes the form's elements.
            success: function(data)
            {
                document.location = 'index.php';
            }
        });
    });
});
</script>
