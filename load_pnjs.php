<?php

require_once('config.php');

/*
 * Fragment personnages secondaires pour le panneau glissant du HUD
 * (js/hud.js). Même corps que pnjs.php sans l'enveloppe Ui ; la
 * bascule de personnage (js/pnjs.js) poste toujours sur pnjs.php et
 * recharge la page — le panneau pnj est purgé de l'état persisté
 * (forgetPnjPanel) pour ne pas se rouvrir sur le nouveau personnage.
 */

include('scripts/pnjs/body.php');
