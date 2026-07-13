<?php

require_once('config.php');

/*
 * Fragment faction (?faction=) pour le panneau glissant du HUD
 * (js/hud.js). Même corps que faction.php sans l'enveloppe Ui.
 */

if(empty($_GET['faction'])){

    exit('error faction');
}

include('scripts/faction/body.php');
