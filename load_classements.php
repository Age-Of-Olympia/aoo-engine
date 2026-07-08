<?php

require_once('config.php');

/*
 * Fragment classements pour le panneau glissant du HUD (js/hud.js).
 * Même corps que classements.php sans l'enveloppe Ui ; les onglets
 * (Général, Bourrins, Fortunes…) restent des liens classements.php
 * que le routeur de panneaux réécrit vers ce fragment.
 */

include('scripts/classements/body.php');
