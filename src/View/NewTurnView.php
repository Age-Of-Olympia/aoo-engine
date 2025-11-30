<?php

namespace App\View;

use App\Tutorial\TutorialHelper;
use Classes\Db;
use Classes\Player;
use Classes\View;

class NewTurnView
{
    public static function renderNewTurn(Player $player): void
    {
        // Skip new turn for tutorial players
        if (TutorialHelper::isInTutorial()) {
            return;
        }

        if ($_SESSION['playerId'] == $_SESSION['originalPlayerId']) {
            $_SESSION['nonewturn'] = false;
        }

        if (isset($_SESSION['nonewturn']) && $_SESSION['nonewturn']) {
            //do nothing, admin info is displayed in infos.php
        } else if (!empty($_SESSION['playerId'])) {


            $time = time();
            $player->get_data(false);


            if ($player->data->nextTurnTime <= $time) {


                $player->getCoords();

                // prevent new turn if dead
                if ($player->coords->plan != 'limbes') {


                    $db = new Db();


                    echo '<h1><font color="red">Nouveau Tour</font></h1>';

                    echo '<div style="text-align: center;">';
                    echo '<a href="index.php"><img class="box-shadow" src="img/ui/illustrations/sunset.webp" /></a>';
                    echo '</div>';

                    $player->get_caracs();


                    // player turn
                    $playerTurn = 86400 - (($player->caracs->spd - 10) * 3600);



                    // NO dlag
                    if (!$player->have_option('dlag')) {


                        $nextTurnTime = $player->data->nextTurnTime + $playerTurn;
                    }

                    // DLAG
                    else {


                        $nextTurnTime = $time + $playerTurn;
                    }


                    // adjust time
                    while ($nextTurnTime <= $time) {

                        $nextTurnTime += 86400 - (($player->caracs->spd - 10) * 3600);
                    }

                    echo '<br />Prochain Tour le ' . date('d/m/Y à H:i', $nextTurnTime) . '.';


                    // end effects
                    foreach (EFFECTS_HIDDEN as $e) {

                        $player->endEffect($e);
                    }


                    // special doubles
                    $url = 'img/foregrounds/doubles/' . $player->id . '.png';
                    if (file_exists($url)) {

                        View::delete_double($player);
                    }

                    $firstPlayerXP = 0;
                    $firstPlayerData = Player::get_player_list();
                    if (isset($firstPlayerData->first)) {
                        $firstPlayerXP = $firstPlayerData->first->xp;
                    }
                    function getTooltip($key)
                    {
                        return 'flow="right" tooltip="' . CARACS_TXT[$key] . '"';
                    }
                    echo '
            <table border="1" align="center" class="marbre">';


                    // gain xp
                    $gainXp = XP_PER_TURNS;

                    if ($player->data->xp + 250 <= $firstPlayerXP) {

                        $diff = $firstPlayerXP - ($player->data->xp + 250);
                        $gainXp += 1 + floor($diff / 50);
                        if ($player->id < 0 && $gainXp > 10)
                            $gainXp = 10;
                    }

                    $gainXpTxt = "";



                    if ($gainXp > 25) {
                        $gainXpTxt = " ( calculé:" . $gainXp . "xp)";
                        $gainXp = 25;
                    }

                    echo '<tr><td ' . getTooltip('xp') . '>Xp</td><td align="right">+' . $gainXp . $gainXpTxt . '</td></tr>';

                    echo '<tr><td ' . getTooltip(key: 'pi') . '>Pi</td><td align="right">+' . $gainXp . '</td></tr>';


                    // update malus
                    $recovMalus = min($player->data->malus, MALUS_PER_TURNS);

                    echo '<tr><td ' . getTooltip(key: 'malus') . '>Malus</td><td align="right">-' . $recovMalus . '</td></tr>';


                    // recover carac
                    foreach (CARACS_RECOVER as $k => $e) {


                        $val = $player->caracs->$e;

                        if ($k == 'pm' && $player->haveEffect('poison_magique')) {


                            $player->endEffect('poison_magique');


                            echo '<tr><td ' . getTooltip($k) . '>' . CARACS[$k] . '</td><td align="right">+0 (<span class="ra ' . EFFECTS_RA_FONT['poison_magique'] . '"></span> Poison Magique)</td></tr>';

                            continue;
                        } elseif ($k == 'pv' && $player->haveEffect('poison')) {


                            $player->endEffect('poison');


                            echo '<tr><td ' . getTooltip($k) . '>' . CARACS[$k] . '</td><td align="right">+ 0 (<span class="ra ' . EFFECTS_RA_FONT['poison'] . '"></span> Poison)</td></tr>';

                            continue;
                        } elseif ($k == 'pv' && $player->haveEffect('regeneration')) {


                            $player->endEffect('regeneration');


                            $val += $player->caracs->rm;

                            echo '<tr><td ' . getTooltip($k) . '>' . CARACS[$k] . '</td><td align="right">+' . $val . ' (<span class="ra ' . EFFECTS_RA_FONT['regeneration'] . '"></span> Régénération)</td></tr>';

                            continue;
                        } elseif ($k == 'a') {

                            $val = $player->caracs->a;

                            // Calcul de la valeur d'énergie
                            $recovEnergie = ENERGIE_CST - $val;

                            continue;
                        }

                        if (!in_array($k, array('ae', 'a', 'mvt'))) {

                            $player->putBonus(array($k => $val));
                        }

                        echo '<tr><td ' . getTooltip($k) . '>' . CARACS[$k] . '</td><td align="right">+' . $val . '</td></tr>';
                    }


                    // recover Ae, A, Mvt
                    $sql = '
                DELETE FROM
                players_bonus
                WHERE
                player_id = ?
                AND
                name IN("ae","a","mvt")
                ';

                    $db->exe($sql, $player->id);


                    // end effects
                    $sql = '
                SELECT COUNT(*) AS n
                FROM players_effects
                WHERE
                endTime <= ?
                AND
                endTime != 0
                AND
                player_id = ?
                ';


                    $res = $db->exe($sql, array($time, $player->id));

                    $row = $res->fetch_object();

                    if ($row->n) {

                        $player->purge_effects();

                        echo '<tr><td>Effets terminés</td><td align="right">' . $row->n . '</td></tr>';
                    }


                    echo '</table>';

                    echo '<br /><a href="index.php"><button>Jouer</button></a>';

                    // Only show email prompt for real players (positive IDs)
                    if ($player->id > 0 && empty($player->data->plain_mail) && !$player->data->email_bonus) {
                        echo ' <a href="account.php?changeMail"><button>Renseigner mon mail (+20 XP)</button></a>';
                    }

                    // anti berserk
                    $antiBerserkTime = $player->data->lastActionTime + (0.25 * $playerTurn);

                    // update
                    $sql = '
            UPDATE
            players
            SET
            nextTurnTime = ?,
            lastActionTime = 0,
            antiBerserkTime = ?,
            malus = malus - ?,
            energie = ?
            WHERE
            id = ?
            ';

                    $values = array(
                        $nextTurnTime,
                        $antiBerserkTime,
                        $recovMalus,
                        $recovEnergie,
                        $player->id
                    );

                    $db->exe($sql, $values);

                    // Ajout de l'xp de début de tour
                    $player->put_xp($gainXp);

                    $player->refresh_data();
                    $player->refresh_caracs();
                    $player->refresh_invent(); // for Ae

                    // Check if tutorial should auto-start (for brand new players)
                    // This must be done BEFORE exit() since NewTurn page blocks normal flow
                    // Only redirect if NOT already in tutorial mode (prevents loop)
                    if (isset($_SESSION['auto_start_tutorial']) && $_SESSION['auto_start_tutorial'] && !isset($_SESSION['in_tutorial'])) {
                        echo '<script>
                        console.log("[NewTurn] Auto-starting tutorial after new turn...");
                        $(document).ready(function() {
                            // Wait for tutorial scripts to load
                            var checkInterval = setInterval(function() {
                                if (typeof window.initTutorial === "function") {
                                    clearInterval(checkInterval);
                                    console.log("[NewTurn] Tutorial scripts loaded, redirecting...");
                                    // Redirect to index.php with tutorial=start parameter
                                    window.location.href = "index.php?tutorial=start";
                                }
                            }, 200);

                            // Timeout after 5 seconds
                            setTimeout(function() {
                                clearInterval(checkInterval);
                                if (typeof window.initTutorial !== "function") {
                                    console.error("[NewTurn] Tutorial scripts failed to load");
                                    // Just redirect to index anyway
                                    window.location.href = "index.php";
                                }
                            }, 5000);
                        });
                        </script>';
                    }

                    exit();
                }
            }
        }
    }
}
