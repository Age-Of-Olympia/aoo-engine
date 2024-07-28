<?php

if($target->have_effect('martyr')){


    echo '<div><font color="red">'. $target->data->name .' réduit d\'un tiers les dégâts de votre attaque grâce au sort Martyr.</font></div>';

    $totalDamages = floor($totalDamages * 2 / 3);

    $target->put_pf($totalDamages);
}

if($target->have_effect('leurre')){


    if(
        $actionJson->type == 'sort'
    ){


        $target->end_effect('leurre');


        echo '<font color="red">'. $target->data->name .' leurre votre attaque grâce à un sort!</font>';

        $totalDamages = 0;
    }
}

if($target->have_effect('dedoublement')){


    $target->end_effect('dedoublement');


    View::delete_double($target);


    echo '<font color="red">Vous avez attaqué un double de '. $target->data->name .'!</font>';

    $totalDamages = 0;


    include('scripts/actions/on_hide_reload_view.php');
}

if($target->have_effect('cle_de_bras')){


    if(
        $player->main1->data->subtype == 'melee'
        &&
        $target->main1->data->name == 'Poing'
    ){


        $target->end_effect('cle_de_bras');


        $player->put_bonus(array('mvt'=>-$player->get_left('mvt')));

        echo '<font color="red">'. $target->data->name .' vous fait une clé de bras et vous immobilise!</font>';

        $totalDamages = 0;
    }
}

if($target->have_effect('pas_de_cote')){


    if(
        (
            !empty($actionJson->type)
            ||
            $actionJson != 'sort'
        )
        &&
        $target->get_left('mvt') >= 1
    ){


        $target->end_effect('pas_de_cote');


        $goCoords = $target->coords;

        $coordsId = View::get_free_coords_id_arround($target->coords);

        $target->go($goCoords);


        echo '<font color="red">'. $target->data->name .' esquive votre attaque avec un pas de côté!</font>';

        $totalDamages = 0;

        View::refresh_players_svg($target->coords);


        include('scripts/actions/on_hide_reload_view.php');
    }
}

