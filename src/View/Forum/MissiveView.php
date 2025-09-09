<?php

namespace App\View\Forum;

use App\Service\PlayerService;
use Classes\Db;
use Classes\Forum;
use Classes\Player;

class MissiveView
{
    public static function renderMissive($topJson, Player $player, PlayerService $playerService): void
    {
        $player->get_data(false);
        $destTbl = Forum::get_top_dest($topJson);

        if (!in_array($player->id, $destTbl) || (($player->id > 0) && ($player->id != $_SESSION['originalPlayerId']))) {
            exit('Accès refusé');
        }

        if (!empty($_POST['addDest'])) {
            if (strpos($_POST['addDest'], 'all_faction_') === 0) {
                $faction = substr($_POST['addDest'], strlen('all_faction_'));
                if (
                    $player->data->faction == $faction ||
                    ($player->data->secretFaction != '' && $player->data->secretFaction == $faction)
                ) {
                    $sql = 'SELECT id FROM players where ( faction = ? or secretFaction = ?) ORDER BY name';

                    $db = new Db();

                    $timeLimit = time() - INACTIVE_TIME;

                    $res = $db->exe($sql, array($faction, $faction));

                    while ($row = $res->fetch_object()) {
                        Forum::add_dest($player, $row->id,  $topJson, $destTbl);
                    }
                }
            } else {

                if (is_numeric($_POST['addDest'])) {
                    $desti = $playerService->GetPlayer($_POST['addDest']);
                } else {
                    $desti = Player::get_player_by_name($_POST['addDest']);
                }
                $desti->get_data(false);
                Forum::add_dest($player, $desti, $topJson, $destTbl);
                //Ajouter l'animateur si la faction est différente
                if (
                    $player->data->faction != $desti->data->faction
                    &&
                    (($player->data->secretFaction == "") ||
                        ($player->data->secretFaction != "" && $player->data->secretFaction != $desti->data->secretFaction))
                ) {
                    $raceJson = json()->decode('races', $desti->data->race);
                    Forum::add_dest($player, $raceJson->animateur, $topJson, $destTbl);
                }
            }

            exit();
        }

        if (!empty($_POST['removeDest'])) {
            //player can only remove self, admin car remove anyone
            if (($_POST['removeDest'] == $player->id) || $player->have_option('isAdmin')) {
                Forum::remove_dest($_POST['removeDest'], $topJson, $destTbl);
            } else {
                exit('<div id="error">Impossible de supprimer ce destinataire. Vous pouvez uniquement supprimer votre propre personnage des destinataires de la missive.</div>');
            }
            exit();
        }

        echo '
<div class="dest-container">
    <div id="dest">
        Destinataires:
        ';

        foreach ($destTbl as $e) {


            $dest = $playerService->GetPlayer($e);
            $dest->get_data(false);

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


        $playersJson = Player::get_player_list()->list;

        echo '<select id="dest-list">
        <option disabled selected>Sélectionnez un personnage:</option>';

        $secretFaction = array();
        $faction = array();


        foreach ($playersJson as $e) {

            if ($e->secretFaction != '' && $e->secretFaction == $player->data->secretFaction) {

                $secretFaction[] = $e;
            } elseif ($e->faction == $player->data->faction) {

                $faction[] = $e;
            } else {
                // $raceJson = json()->decode('races', $e->race);
                //
                // echo '<option value="'. $e->id .'">- '. $e->name .' '. $raceJson->name .'</option>';
                continue;
            }
        }

        $factionJson = json()->decode('factions', $player->data->faction);

        echo '<option value="all_faction_' . $player->data->faction . '">' . $factionJson->name . ' (tous les membres)</option>';

        foreach ($faction as $e) {


            $raceJson = json()->decode('races', $e->race);

            echo '<option value="' . $e->id . '">- ' . $e->name . ' (' . $raceJson->name . ')</option>';
        }


        if ($player->data->secretFaction != '') {


            $secretJson = json()->decode('factions', $player->data->secretFaction);

            echo '<option value="all_faction_' . $player->data->secretFaction . '">' . $secretJson->name . ' (tous les membres)</option>';

            foreach ($secretFaction as $e) {


                $raceJson = json()->decode('races', $e->race);

                echo '<option value="' . $e->id . '">- ' . $e->name . ' (' . $raceJson->name . ')</option>';
            }
        }


        echo '<option disabled>Animateurs:</option>';

        foreach (RACES_EXT as $e) {


            $raceJson = json()->decode('races', $e);

            echo '<option value="' . $raceJson->animateur . '">- Animateur: ' . $raceJson->name . '</option>';
        }


        echo '</select>';

        echo '<input id="autocomplete" type="text" placeholder="Rechercher" style="display:none; margin-left:20px" />';

        echo '</div>';


        if (count($destTbl) == 1) {


            echo '<div style="color: blue; text-align: left; font-size: 88%; margin: 10px;">Vous pouvez maintenant sélectionner un ou plusieurs destinataires de votre faction/peuple.<br />Pour envoyer un message à un personnage d\'un autre peuple, sélectionnez l\'animateur de son peuple, tout en bas de la liste.<br />Ce dernier invitera ce personnage dans la discussion.<br />
    <font color="red">Les Missives sans destinataires sont automatiquement supprimées.</font></div>';
        }


?>
        <script>
            window.topName = "<?php echo $topJson->name ?>";
        </script>
        <script src="js/forum_missives.js"></script>
        <script src="js/autocomplete.js"></script>
        <script>
            $(function() {
                bindAutocomplete(
                    function(event, ui) {

                        $.ajax({
                            type: "POST",
                            url: 'forum.php?topic=' + window.topName,
                            data: {
                                'addDest': ui.item.label
                            },
                            success: function(data) {
                                document.location.reload();
                            }
                        });

                    }
                );

            });
        </script>
<?php
    }
}
