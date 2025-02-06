<?php


if(!$player->have_spell($_POST['spell'])){

    exit('error have spell');
}


$player->end_spell($_POST['spell']);
