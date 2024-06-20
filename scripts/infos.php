<?php

if(!empty($_SESSION['playerId'])){


    $player = new Player($_SESSION['playerId']);
    $player->get_data();


    $raceJson = json()->decode('races', $player->data->race);


    $lastPostJson = json()->decode('forum', 'lastPosts');


    if(!empty($lastPostJson->{$player->data->faction})){

        $lastPost = ($lastPostJson->general->time > $lastPostJson->{$player->data->faction}->time) ? $lastPostJson->general->text : $lastPostJson->{$player->data->faction}->text;
    }

    else{

        $lastPost = $lastPostJson->general->text;
    }


    echo '
    <table border="0" align="center">
        <tr>
            <td>
                <div id="player-avatar">
                    <a href="pnjs.php"><img src="'. $player->data->avatar .'" /></a>
                </div>
            </td>
            <td align="left">
                '. $player->data->name .' (mat.'. $player->id .')<br />
                <sup>'. $raceJson->name .' Rang '. $player->data->rank .'</sup>
            </td>

            <td width="50"></td>

            <td align="left" style="font-size: 88%;">
                Dernier message du <a href="forum.php">Forum</a><br />
                <i>'. $lastPost .'</i>
            </td>
        </tr>
    </table>
    ';
}

else{


    echo "Vous n'êtes pas connecté.";
}
