<?php

namespace App\View\Observe;

use App\Factory\PlayerFactory;
use Classes\Db;
use Classes\Player;
use Classes\Str;
use Classes\Ui;

/**
 * Carte d'un MUR de carte (map_walls) ou d'un AUTEL pour le panneau
 * d'observation — extrait tel quel d'observe.php (ménage n°2). Les
 * murs legacy gardent leur présentation mutualisée (Ui::get_card,
 * état brisé, statut destructible) jusqu'à la migration
 * murs→structures ; l'autel garde la priorité quand il pose sa carte.
 */
final class WallCardView
{
    /**
     * @param \mysqli_result $res lignes map_walls de la case
     * @param int|string     $x   coordonnées observées (script destroy)
     * @param int|string     $y
     *
     * @return string la carte à échoer en fin de panneau ('' si aucune)
     */
    public static function render(Player $player, \mysqli_result $res, $x, $y): string
    {
        $db = new Db();
        $card = '';
        $wallId = 0;

        while($row = $res->fetch_object()){


            $wallId = $row->id;

            /* Le bloc altar réassigne $row plus bas : figer les champs du
             * mur pour la carte mutualisée construite après le bloc. */
            $wallName = $row->name;
            $wallDamages = (int) $row->damages;


            echo '
            <div class="case-infos">
                <img src="img/walls/'. $row->name .'.png" title="#'. $row->id .'"/>

                <div class="text">
                    Structure non-passable.<br />
                    ';

                    if(!empty(WALLS_PV[$row->name]) && WALLS_PV[$row->name] > 0){

                        echo 'Destructible ('. Str::get_status($row->damages, WALLS_PV[$row->name]) .').';
                    }
                    else{

                        echo 'Indestructible.';
                    }

                    echo '<br />';

                    // Affichage si la ressource est épuisée ou non
                    if($row->damages == -1){
                        echo '<br /><span class="resource-status resource-harvestable" style="color:green;"><b>Récoltable.</b></span> <br />';
                    }
                    if($row->damages == -2){
                        echo '<br /><span class="resource-status resource-exhausted" style="color:red;"><b>Épuisée.</b></span> <br />';
                    }

                    // altar

                    $sql = 'SELECT * FROM map_triggers WHERE name = "altar" AND coords_id= ?';

                    $resAltar = $db->exe($sql, $row->coords_id);

                    if($resAltar->num_rows){

                        $row = $resAltar->fetch_object();

                        $god = PlayerFactory::legacy($row->params);

                        $god->get_data();

                        echo 'Altar du Dieu '. $god->data->name .'.';

                        $actions = '';

                        $dataText = "Vous vénérez déjà ce Dieu.";

                        if($god->id != $player->data->godId){

                            $actions = '
                            <button
                                class="action"
                                data-url="worship.php"
                                data-action="worship"
                                data-target-id="'. $row->id .'"
                            ><span class="ra ra-candle"></span>
                            <span class="action-name">Vénérer</span>
                            </button><br/>';

                            $dataText = "Vénérez ce Dieu pour pouvoir lui adresser vos prières.";
                        }

                        $dataName = '<a href="infos.php?targetId='. $god->id .'">Altar du Dieu '. $god->data->name .'</a>';

                        $data = (object) array(
                            'bg'=>$god->data->portrait,
                            'name'=>$dataName,
                            'img'=>$actions,
                            'type'=>'Altar',
                            'race'=>'dieu',
                            'text'=>$dataText
                        );

                        $card = Ui::get_card($data);
                    }

                    echo '
                </div>
            </div>
            ';

            /* Carte mutualisée (Ui::get_card — LE composant de la palissade
             * et de l'autel) : nom du catalogue, portrait avec voile de
             * dégâts, état brisé, description. L'autel garde la priorité
             * quand il a déjà posé sa carte. */
            if(empty($card)){

                $wallBaseName = str_replace('_broken', '', $wallName);
                $isBroken = strpos($wallName, '_broken') !== false;

                $wallLabel = ucfirst(str_replace('_', ' ', $wallBaseName));
                $wallText = '';
                $wallCatalogItem = \Classes\Item::get_item_by_name($wallBaseName);
                if($wallCatalogItem){

                    $wallCatalogItem->get_data();
                    $wallLabel = ucfirst(str_replace('_', ' ', $wallCatalogItem->data->name));
                    $wallText = (string) ($wallCatalogItem->data->text ?? '');
                }

                $wallPvMax = (!empty(WALLS_PV[$wallName]) && WALLS_PV[$wallName] > 0) ? (int) WALLS_PV[$wallName] : 0;

                $wallStatus = ($wallPvMax > 0)
                    ? 'Destructible ('. Str::get_status($wallDamages, $wallPvMax) .').'
                    : 'Indestructible.';

                $data = (object) array(
                    'bg' => 'img/walls/'. $wallName .'.png',
                    'name' => $wallLabel . ($isBroken ? ' — <font color="red">brisé</font>' : ''),
                    'img' => '',
                    'type' => 'Structure',
                    'race' => 'common',
                    'text' => $wallStatus . ($wallText !== '' ? '<br /><sup>'. $wallText .'</sup>' : ''),
                );

                if($wallPvMax > 0){

                    $data->pvPct = max(0, (int) floor(($wallPvMax - $wallDamages) / $wallPvMax * 100));
                }

                $card = Ui::get_card($data);
            }
        }


        // show destroy button
        echo '
        <script>
        var $wall = $(\'#walls'. $wallId .'\');
        var x = '. $x .';
        var y = '. $y .';
        </script>
        <script src="js/observe_destroy.js?v=20260715"></script>
        ';

        return $card;
    }
}
