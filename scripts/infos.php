<?php

if(!empty($_SESSION['playerId'])){


    if(!isset($player)){


        $player = new Player($_SESSION['playerId']);
        $player->get_data();
    }


    $raceJson = json()->decode('races', $player->data->race);


    $lastPostJson = json()->decode('forum', 'lastPosts');


    if(!empty($lastPostJson->{$player->data->faction})){

        $lastPost = ($lastPostJson->general->time > $lastPostJson->{$player->data->faction}->time) ? $lastPostJson->general->text : $lastPostJson->{$player->data->faction}->text;
    }

    else{

        $lastPost = $lastPostJson->general->text;
    }


    echo '
    <div id="top-menu-button"><a id="index-banner" href="index.php?menu"><img src="img/ui/bg/index.png" /></a></div>';


    echo '
    <table border="0" align="center">
        <tr>

            <td align="left" class="player-info">
                <a href="infos.php?targetId='. $player->id .'">'. $player->data->name .'</a> (mat.'. $player->id .')<br />
                <sup>'. $raceJson->name .' Rang '. $player->data->rank .'</sup>
            </td>
            <td>
                <div id="player-avatar">
                    <a href="pnjs.php"><img src="'. $player->data->avatar .'" /></a>
                </div>
            </td>

            <td align="left" class="player-info">
                Dernier message du <a href="forum.php">Forum</a><br />
                <sup><i>'. $lastPost .'</i></sup>
            </td>
        </tr>
    </table>
    ';
}
