<?php

define('VERSION_JS_MAIN', filemtime('js/main.js'));
define('VERSION_CSS_MAIN', filemtime('css/main.css'));


define('DOMAIN', 'http://localhost/www/aoo4/');


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


define('CARACS_RECOVER', array(
    'pv'=>'r',
    'pm'=>'rm',
    'a'=>'a',
    'mvt'=>'mvt'
));


/*
 * time
 */

// ONE YEAR
define('ONE_YEAR', 31536000);
// ONE WEEK
define('ONE_WEEK', 604800);
// THREE DAYS
define('THREE_DAYS', 259200);
// ONE DAY
define('ONE_DAY', 86400);
// ONE HOUR
define('ONE_HOUR', 3600);
// INACTIVE TIME
define('INACTIVE_TIME', ONE_WEEK);


/*
 * effects, elements
 */

define('EFFECTS_RA_FONT', array(

    'adrenaline'=>'ra-horn-call',

    'feu'=>'ra-small-fire',     // e - 1
    'eau'=>'ra-water-drop',     // mvt -1
    'ronce'=>'ra-vine-whip',    // agi -1
    'boue'=>'ra-shoe-prints',   // f -1
    'diamant'=>'ra-sapphire',   // m -1

    'sang'=>'ra-gloop',         // fm -1

    'regeneration'=>'ra-health-increase',
    'poison_magique'=>'ra-bone-bite',

    'vol'=>'ra-feather-wing',

    'fatigue'=>'ra-player-pain'
));

define('ELE_DEBUFFS', array(
    'feu'=>'e',
    'eau'=>'mvt',
    'ronce'=>'agi',
    'boue'=>'f',
    'diamant'=>'fm',
    'sang'=>'fm'
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
 * walls
 *
 */


// PV: if not defined, undestructible
define('WALLS_PV', array(
    'mur_pierre'=>100,
    'mur_pierre_broken'=>100,

    'altar'=>50,
    'altar_broken'=>50,

    'cocotier1'=>1,
    'cocotier2'=>1,
    'cocotier3'=>1
));


/*
 * items options & emplacements
 */


define('DMG_CRIT', 10); // 10% de critique (x2 dégâts) sur un ennemi sans casque


define('ITEM_DROP', 10); // 10% de drop sur les désarmements et loots


define('ITEM_LIMIT', 3);


define('ITEMS_OPT', array(
    'enchanted'=>'*',
    'vorpal'=>'~',
    'cursed'=>'',
    'element'=>'',
    'blessed_by_id'=>'+',
    'spell'=>'§'
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


define('LOOT_CHANCE_DEFAULT', 20);


define('LOOT_CHANCE', array(
));


/*
 * costs & recups
 */

define('TRAVEL_COST', 15); // travelling cost 15Po

define('FAT_EVERY', 6); // every 6 fat, -1 for all rolls

define('FAT_PER_ACTION', 1); // each Action add 1 fat

define('FAT_PER_REST', 4); // resting delete 4 fat

define('FAT_PER_TURNS', 2); // new turn delete 2 fat

define('MALUS_PER_DAMAGES', 2); // when damages are done, add 2 malus

define('MALUS_PER_TURNS', 9); // recup 9 malus / turns

define('XP_PER_TURNS', 6); // base 6 xp - rank / turns


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
