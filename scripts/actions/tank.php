<?php

if($target->haveEffect('martyr')){


    echo '<div><font color="red">'. $target->data->name .' réduit d\'un tiers les dégâts de votre attaque grâce au sort Martyr.</font></div>';

    $totalDamages = floor($totalDamages * 2 / 3);

    $target->put_pf($totalDamages);
}

if($target->haveEffect('leurre')){


    if(
        $actionJson->type == 'sort'
    ){


        $target->endEffect('leurre');


        echo '<font color="red">'. $target->data->name .' leurre votre attaque grâce à un sort!</font>';

        $totalDamages = 0;
    }
}

if($target->haveEffect('dedoublement')){


    $target->endEffect('dedoublement');


    View::delete_double($target);


    echo '<font color="red">Vous avez attaqué un double de '. $target->data->name .'!</font>';

    $totalDamages = 0;


    include('scripts/actions/on_hide_reload_view.php');
}

if($target->haveEffect('cle_de_bras')){


    if(
        $player->emplacements->main1->data->subtype == 'melee'
        &&
        $target->emplacements->main1->data->name == 'Poing'
    ){


        $target->endEffect('cle_de_bras');


        $player->put_bonus(array('mvt'=>-$player->getRemaining('mvt')));

        echo '<font color="red">'. $target->data->name .' vous fait une clé de bras et vous immobilise!</font>';

        $totalDamages = 0;
    }
}

if($target->haveEffect('pas_de_cote')){


    if(
        (
            !empty($actionJson->type)
            ||
            $actionJson != 'sort'
        )
        &&
        $target->getRemaining('mvt') >= 1
    ){


        $target->endEffect('pas_de_cote');


        $goCoords = $target->coords;

        $coordsId = View::get_free_coords_id_arround($target->coords);

        $target->go($goCoords);


        echo '<font color="red">'. $target->data->name .' esquive votre attaque avec un pas de côté!</font>';

        $totalDamages = 0;

        View::refresh_players_svg($target->coords);


        include('scripts/actions/on_hide_reload_view.php');
    }
}

