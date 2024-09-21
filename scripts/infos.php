<?php

if(!empty($_SESSION['playerId'])){


    if(!isset($player)){


        $player = new Player($_SESSION['playerId']);
        $player->get_data();
    }


    $raceJson = json()->decode('races', $player->data->race);


    $lastPostJson = json()->decode('forum', 'lastPosts');



    $lastPostTime = $lastPostJson->general->time;
    $lastPost = $lastPostJson->general->text;

    if(!empty($lastPostJson->{$player->data->faction})){

        if ($lastPostJson->{$player->data->faction}->time > $lastPostTime) {
            $lastPostTime = $lastPostJson->{$player->data->faction}->time;
            $lastPost = $lastPostJson->{$player->data->faction}->text;
        }
    }

    if (!empty($player->data->secretFaction) && !empty($lastPostJson->{$player->data->secretFaction})) {
        if ($lastPostJson->{$player->data->secretFaction}->time > $lastPostTime) {
            $lastPostTime = $lastPostJson->{$player->data->secretFaction}->time;
            $lastPost = $lastPostJson->{$player->data->secretFaction}->text;
        }
    }


    ob_start();


    echo '
    <div id="top-menu-button"><a id="index-banner" href="index.php?menu"><img src="img/ui/bg/index.webp" /></a></div>';


    $timeToNextTurn = Str::convert_time($player->data->nextTurnTime - time());
    $adminInfos = '';
    if(isset($_SESSION['nonewturn']) && $_SESSION['nonewturn'] == true){
        $adminInfos = ' Nouveau Tour Désactivé (use -reactive to enable it on session open : ex "session open Orcrist -reactive" )';
    }

    echo '
    <table border="0" align="center">
        <tr>

            <td align="left" class="player-info">
                <a href="infos.php?targetId='. $player->id .'">'. $player->data->name .'</a> (mat.'. $player->id .')<br />
                <!--sup>'. $raceJson->name .' Rang '. $player->data->rank .'</sup-->
                <sup>Prochain tour à <a href="#" title="dans '. $timeToNextTurn .'">'. date('H:i', $player->data->nextTurnTime). $adminInfos .'</a></sup>
            </td>
            <td>
                <div id="player-avatar">
                    <a href="pnjs.php"><img src="'. $player->data->avatar .'" /></a>
                </div>
            </td>

            <td align="left" class="player-info">
                <a href="forum.php?lastPosts">Derniers messages</a> du <a href="forum.php">Forum</a><br />
                <sup><i>'. $lastPost .'</i></sup>
            </td>
        </tr>
    </table>
    ';


    echo Str::minify(ob_get_clean());
}

