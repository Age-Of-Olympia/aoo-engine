<?php

define('VERSION_JS_MAIN', filemtime('js/main.js'));
define('VERSION_CSS_MAIN', filemtime('css/main.css'));


/*
 * races
 */


define('RACES', array('nain','geant','olympien','hs','elfe'));

define('RACES_EXT', array('nain','geant','olympien','hs','elfe','lutin','humain','dieu'));


/*
 * caracs
 */

// CARACS
define('CARACS', array(
    'a'=>'A',
    'mvt'=>'Mvt',
    'p'=>'P',
    'pv'=>'PV',
    'cc'=>'CC',
    'ct'=>'CT',
    'f'=>'F',
    'e'=>'E',
    'agi'=>'Agi',
    'pm'=>'PM',
    'fm'=>'FM',
    'm'=>'M',
    'r'=>'R',
    'rm'=>'RM',
    'spd'=>'Spd',
    'ae'=>'Ae'
));


/*
 * effects, elements
 */

define('EFFECTS_RA_FONT', array(

    'adrenaline'=>'ra-horn-call',

    'feu'=>'ra-small-fire',
    'eau'=>'ra-water-drop',
    'ronce'=>'ra-vine-whip',
    'boue'=>'ra-shoe-prints',
    'diamant'=>'ra-sapphire',

    'sang'=>'ra-gloop',

    'regeneration'=>'ra-health-increase',
    'poison_magique'=>'ra-bone-bite',

    'fatigue'=>'ra-player-pain'
));

define('ELE_CONTROLS', array(
    'eau'=>'feu',
    'feu'=>'diamant',
    'diamant'=>'ronce',
    'ronce'=>'boue',
    'boue'=>'eau'
));

define('ELE_IS_CONTROLED', array(
    'feu'=>'eau',
    'diamant'=>'feu',
    'ronce'=>'diamant',
    'boue'=>'ronce',
    'eau'=>'boue'
));

define('ELE_PROD', array(
    'eau'=>'ronce',
    'feu'=>'boue',
    'diamant'=>'bois',
    'ronce'=>'feu',
    'boue'=>'diamant'
));


/*
 * time
 */

// ONE YEAR
define('ONE_YEAR', 31536000);
// ONE WEEK
define('ONE_WEEK', 604800);
// ONE DAY
define('ONE_DAY', 86400);
// ONE HOUR
define('ONE_HOUR', 3600);
// INACTIVE TIME
define('INACTIVE_TIME', ONE_WEEK);


/*
 * walls
 *
 */


// PV: if not defined, undestructible
define('WALLS_PV', array(
    'mur_pierre'=>100,
    'altar'=>100,

    'cocotier1'=>10,
    'cocotier2'=>10,
    'cocotier3'=>10
));


/*
 * items options & emplacements
 */


define('ITEM_LIMIT', 3);


define('ITEMS_OPT', array(
    'enchanted'=>'*',
    'vorpal'=>'~',
    'cursed'=>'',
    'element'=>'',
    'blessed_by_id'=>'+'

));


define('ITEM_EMPLACEMENT_FORMAT', array(
    'main1',
    'main2',
    'doigt',
    'tete',
    'bouche',
    'cou',
    'epaule',
    'tronc',
    'taille',
    'pieds',
    'munition',
    'trophee'
));


/*
 * costs
 */

define('TRAVEL_COST', 15); // travelling cost 15Po

define('FAT_EVERY', 6); // every 6 fat, -1 for all rolls

define('FAT_PER_ACTION', 1); // each Action add 1 fat

define('FAT_PER_REST', 6); // resting delete 6 fat

define('MALUS_PER_DAMAGES', 2); // when damages are done, add 2 malus


/*
 * errors
 */

define('ERROR_DISTANCE', "Vous n'êtes pas à bonne distance.");


/*
 * debug & test
 */

define('AUTO_GROW', true); // si true, les plantes poussent dès qu'on les met en terre
define('FISHING', false); // si true, les players pêchent constemment
define('CACHED_INVENT', false);
