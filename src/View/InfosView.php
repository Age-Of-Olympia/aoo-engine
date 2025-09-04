<?php

namespace App\View;

use Classes\Player;
use Classes\Str;

class InfosView
{
    public static function renderInfos(): void
    {
        if (!empty($_SESSION['playerId'])) {


            if (!isset($player)) {

                if (isset($playerService)) {
                    $player = $playerService->GetPlayer($_SESSION['playerId']);
                } else {
                    $player = new Player($_SESSION['playerId']);
                }
                $player->get_data();
            }


            $raceJson = json()->decode('races', $player->data->race);


            $lastPostJson = json()->decode('forum', 'lastPosts');



            $lastPostTime = $lastPostJson->general->time;
            $lastPost = $lastPostJson->general->text;

            if (!empty($lastPostJson->{$player->data->faction})) {

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
            if ($player->id == $_SESSION['originalPlayerId']) {
                $_SESSION['nonewturn'] = false;
            } else if (isset($_SESSION['nonewturn']) && $_SESSION['nonewturn']) {
                $adminInfos = ' <a href="#" onclick="navigator.clipboard.writeText(\'session open ' . $player->id . ' -reactive\');" style="color: #e50000;" title="Nouveau Tour Désactivé (click to copy command)">⌀</a>';
            }

            echo '
    <table border="0" align="center">
        <tr>

            <td align="left" class="player-info">
                <a href="infos.php?targetId=' . $player->id . '">' . $player->data->name . '</a> (mat.' . $player->id . ')<br />
                <!--sup>' . $raceJson->name . ' Rang ' . $player->data->rank . '</sup-->
                <sup>Prochain tour à <a href="#" title="dans ' . $timeToNextTurn . '">' . date('H:i', $player->data->nextTurnTime) . "</a>" . $adminInfos . '</sup>
            </td>
            <td>
                <div id="player-avatar" data-id="' . $player->id . '">
                    <a href="pnjs.php"><img src="' . $player->data->avatar . '" /></a>
                </div>
            </td>

            <td align="left" class="player-info">
                <a href="forum.php?lastPosts">Derniers messages</a> du <a href="forum.php">Forum</a><br />
                <sup><i>' . $lastPost . '</i></sup>
            </td>
        </tr>
    </table>
    ';


            echo Str::minify(ob_get_clean());
        }
    }
}
