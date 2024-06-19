<?php

if(!empty($_SESSION['playerId'])){


    $player = new Player($_SESSION['playerId']);
    $player->get_data();


    $raceJson = json()->decode('races', $player->data->race);


    $lastPostJson = json()->decode('forum', 'lastPosts');


    if(!empty($lastPostJson->{$player->data->race})){

        $lastPost = ($lastPostJson->general->time > $lastPostJson->{$player->data->race}->time) ? $lastPostJson->general->text : $lastPostJson->{$player->data->race}->text;
    }

    else{

        $lastPost = $lastPostJson->general->text;
    }


    echo '
    <table border="0" align="center">
        <tr>
            <td>
                <a href="pnjs.php"><img src="'. $player->data->avatar .'" /></a>
            </td>
            <td align="left">
                '. $player->data->name .' (mat.'. $player->id .')<br />
                <sup>'. $raceJson->name .' Rang '. $player->data->rank .'</sup>
            </td>

            <td width="100" align="right">
                <a href="forum.php"><button style="width: 45px; height: 45px; font-size: 120%;"><span class="ra ra-quill-ink"></span></button></a>
            </td>

            <td align="left" style="font-size: 88%;">
                Dernier message du Forum<br />
                <i>'. $lastPost .'</i>
            </td>
        </tr>
    </table>
    ';
}

else{


    echo "Vous n'êtes pas connecté.";
}
