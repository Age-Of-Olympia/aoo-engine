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
                <a href="infos.php?targetId='. $player->id .'">'. $player->data->name .'</a> (mat.'. $player->id .')<br />
                <sup>'. $raceJson->name .' Rang '. $player->data->rank .'</sup>
            </td>

            <td><a id="index-banner" href="index.php?menu"><img src="img/ui/bg/banner.png" height="45" /></a></td>

            <td align="left" style="font-size: 88%;">
                Dernier message du <a href="forum.php">Forum</a><br />
                <i>'. $lastPost .'</i>
            </td>
        </tr>
    </table>
    ';
}
