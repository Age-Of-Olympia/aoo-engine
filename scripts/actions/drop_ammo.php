<?php

if(!empty($actionJson->useEmplacement)){


    if(!empty($munition) && $munition){


        $munition->get_data();

        $munition->add_item($player, -1);

        echo '<div>Perdu: '. $munition->data->name .'.</div>';
    }


    if($player->emplacements->{$emplacement}->data->subtype == 'jet'){


        if($distance > 2){


            $dropCoords = clone $target->coords;

            $coordsId = View::get_free_coords_id_arround($dropCoords, $p=1);

            $values = array(
            'item_id'=>$player->emplacements->{$emplacement}->id,
            'coords_id'=>$coordsId,
            'n'=>1
            );

            $db = new Db();

            $db->insert('map_items', $values);


            $player->emplacements->{$emplacement}->add_item($player, -1);


            Player::refresh_views_at_z($dropCoords->z);


            include('scripts/actions/on_hide_reload_view.php');

            echo '<div>Vous perdez '. $player->emplacements->{$emplacement}->data->name .'.</div>';
        }

        echo '<div>Vous gardez '. $player->emplacements->{$emplacement}->data->name .'.</div>';
    }
}
