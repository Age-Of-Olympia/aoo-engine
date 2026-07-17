<?php

namespace App\View\Observe;

use Classes\Player;
use Classes\Ui;

/**
 * Dialogue de CASE (déclencheurs map_dialogs posés par l'éditeur
 * Tiled) — extrait tel quel d'observe.php (ménage n°2). Distinct des
 * dialogues portés par un bâtiment (buildings.dialog, rendus dans la
 * fiche) : ici le lien est collé à la case, params CSV
 * « nom,avatar,dialogue » ou alerte brute entre guillemets.
 */
final class TileDialogView
{
    /** @param \mysqli_result $res lignes map_dialogs de la case */
    public static function render(Player $player, \mysqli_result $res): void
    {
        if(!$res->num_rows){

            return;
        }

        $row = $res->fetch_object();


        if($row->params[0] == '"'){


            $alert = str_replace('"', '', $row->params);

            echo '<script>alert("'. $alert .'");</script>';
        }

        else{


            $paramsTbl = explode(',', $row->params);


            if(count($paramsTbl) == 1){

                $paramsTbl[] = $paramsTbl[0];
                $paramsTbl[] = $paramsTbl[0];
                $paramsTbl[] = $paramsTbl[0];
            }


            $options = array(
            'name'=>$paramsTbl[0],
            'avatar'=>'img/dialogs/bg/'. $paramsTbl[1] .'.webp',
            'dialog'=>$paramsTbl[2],
            'text'=>''
            );

            echo '<div class="view-dialog">'. Ui::get_dialog($player, $options) .'</div>';
        }
    }
}
