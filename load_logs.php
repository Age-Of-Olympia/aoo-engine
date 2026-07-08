<?php

require_once('config.php');

/*
 * Fragment évènements pour le panneau glissant du HUD (js/hud.js).
 * Même corps que logs.php sans l'enveloppe Ui ; les onglets
 * (Perception, Du personnage, Messages du jour, Quêtes…) restent des
 * liens logs.php que le routeur de panneaux réécrit vers ce fragment.
 */

include('scripts/logs/body.php');
