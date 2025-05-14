<?php

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

define('DAYS_OF_WEEK', array(
    'Dimanche',
    'Lundi',
    'Mardi',
    'Mercredi',
    'Jeudi',
    'Vendredi',
    'Samedi'
));
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

    'styx'=>'ra-water-drop',     // mvt -1
    'sang'=>'ra-gloop',         // fm -1
    'lave'=>'ra-fire-bomb',     // a -1

    'regeneration'=>'ra-health-increase',
    'poison'=>'ra-bone-bite',
    'poison_magique'=>'ra-bone-bite',

    'parade'=>'ra-sword',
    'pas_de_cote'=>'ra-player-dodge',
    'cle_de_bras'=>'ra-bear-trap',
    'leurre'=>'ra-lava',
    'dedoublement'=>'ra-double-team',

    'armure_rayonnante'=>'ra-sunbeams',
    'berserker'=>'ra-monster-skull',
    'endiamante'=>'ra-diamond',
    'golconda'=>'ra-aware',
    'martyr'=>'ra-player-shot',

    'corruption_du_metal'=>'ra-biohazard',
    'corruption_du_bronze'=>'ra-biohazard',
    'corruption_du_bois'=>'ra-biohazard',
    'corruption_du_plantes'=>'ra-biohazard',
    'corruption_du_cuir'=>'ra-biohazard',

    'vol'=>'ra-feather-wing',

    'fatigue'=>'ra-player-pain',

    //non utilisé mais necceesaire
    'trace_pas'=> 'ra-footprint',
    'trace_pas_ne'=> 'ra-footprint',
    'trace_pas_n'=> 'ra-footprint',
    'trace_pas_no'=> 'ra-footprint',
    'trace_pas_e'=> 'ra-footprint',
    'trace_pas_o'=> 'ra-footprint',
    'trace_pas_se'=> 'ra-footprint',
    'trace_pas_s'=> 'ra-footprint',
    'trace_pas_so'=> 'ra-footprint'
));


define('EFFECTS_TXT', array(
    'adrenaline'=>"Adrénaline<br />Empêche d'intéragir avec un Marchand.",
    'eau'=>"Eau<br />Diminue les Mouvements de 1.",
    'ronce'=>"Ronce<br />Diminue l'Agilité de 1.",
    'boue'=>"Boue<br />Diminue la Force de 1.",
    'diamant'=>"Diamant<br />Diminue la Magie de 1.",
    'sang'=>"Sang<br />Diminue Force Mentale de 1.",
    'lave'=>"Lave<br />Diminue les Actions de 1.",

    'regeneration'=>"Regénération<br />Effet du sort Regénération.",
    'poison_magique'=>"Poison Magique<br />Empêche la récupération magique au prochain tour.",

    'corruption_du_metal'=>'Corruption du métal<br />Augmente le risque que le matériel contenant du métal (Bronze, Nickel) se casse.',
    'corruption_du_bronze'=>'Corruption du Bronze<br />Augmente le risque que le matériel contenant du Bronze se casse.',
    'corruption_du_bois'=>'Corruption du Bois<br />Augmente le risque que le matériel contenant du Bois (ou du Bois Pétrifié) se casse.',
    'corruption_du_plantes'=>'Corruption des plantes<br />Augmente le risque que le matériel contenant des plantes (Adonis) se casse.',
    'corruption_du_cuir'=>'Corruption du Cuir<br />Augmente le risque que le matériel contenant du Cuir se casse.',

    'vol'=>"Vol<br />Permet de se déplacer dans les airs."
));


define('EFFECTS_HIDDEN', array( // these effects will be ended at a new turn or when used
    'parade',
    'leurre',
    'dedoublement',
    'cle_de_bras',
    'pas_de_cote'
));


define('ELE_DEBUFFS', array(
    'feu'=>'e',
    'eau'=>'mvt',
    'ronce'=>'agi',
    'boue'=>'f',
    'diamant'=>'m',

    'styx'=>'mvt',
    'sang'=>'fm',
    'lave'=>'a',
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
    'mur_pierre'=>150,
    'mur_pierre_broken'=>150,

    'mur_noir'=>120,
    'mur_noir_broken'=>120,
    
    'mur_bois'=>100,
    'mur_bois_petrifie'=>125,

    'coffre_metal'=>100,
    'coffre_bois'=>30,
    'coffre_bois_petrifie'=>40,

    'coffre_metal_broken'=>100,
    'coffre_bois_broken'=>30,
    'coffre_bois_petrifie_broken'=>40,

    'altar'=>50,
    'altar_broken'=>50,

    'pierre_precieuse'=>100,
    
    'piedestal'=>50,
    'piedestal_broken'=>50,
    'piedestal_pierre'=>25,
    'piedestal_pierre_broken'=>25,
    
    'table_bois'=>50,	
    
    'statues1'=>30,
    'statues2'=>30,
    'statue_heroique'=>30,
    'statues6'=>30,
    'statues4'=>30,
    'statues5'=>30,
    
    'cocotier1'=>1,
    'cocotier2'=>1,
    'cocotier3'=>1
));

/*
 * items options & emplacements
 */


define('DMG_CRIT', 5); // 5% de critique (+3 dégâts) sur un ennemi sans casque

define('ITEM_DROP', 10); // 10% de drop sur les désarmements et loots

define('ITEM_BREAK', 1); // 1% de break sur une attaque ou une défense

define('ITEM_LIMIT', 3);

define('ITEM_PLANTS', array(
    'adonis',
    'cafe',
    'astral',
    'houblon',
    'lichen_sacre',
    'lotus_noir',
    'menthe',
    'pavot'
));

define('ITEM_CORRUPTIONS', array(
    'corruption_du_metal'=>array('bronze','nickel'),
    'corruption_du_bronze'=>array('bronze'),
    'corruption_du_bois'=>array('bois','bois_petrifie'),
    'corruption_des_plantes'=>ITEM_PLANTS,
    'corruption_du_cuir'=>array('cuir')
));

define('ITEM_CORRUPT_BREAKCHANCES', array(
    'corruption_du_metal'=>15,
    'corruption_du_bronze'=>10,
    'corruption_du_bois'=>20,
    'corruption_des_plantes'=>15,
    'corruption_du_cuir'=>5
));

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
    'deuxmains',
    'doigt',
    'tete',
    'bouche',
    'cou',
    'epaule',
    'cape',
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

define('TRAVEL_COST', 15);      // travelling cost 15Po
define('FAT_EVERY', 6);         // every 6 fat, -1 for all rolls
define('FAT_PER_ACTION', 1);    // each Action add 1 fat
define('FAT_PER_REST', 4);      // resting delete 4 fat
define('FAT_PER_TURNS', 2);     // new turn delete 2 fat
define('FAT_PER_MINE', 3);      // fat when mining without pioche
define('MALUS_PER_DAMAGES', 2); // when damages are done, add 2 malus
define('MALUS_PER_TURNS', 9);   // recup 9 malus / turns
define('XP_PER_TURNS', 5);      // base 5 xp - rank / turns
define('XP_PER_MINE', 1);       // chaque case creusé rapporte 1xp
define('DEATH_XP', 10);       // chaque case creusé rapporte 1xp
define('ACTION_XP', 5);         // base action Xp
define('SEASON_XP', 7000);      // limit for the current season
define('BANK_PCT', 1);          // % gain par jour en banque sans adré

define('MIN_GOLD_STOLEN', 5);
define('MAX_XP_FOR_STEALING', 3);

/*
 * errors
 */

define('ERROR_DISTANCE', "Vous n'êtes pas à bonne distance.");


/*
 * debug & test
 */

define('AUTO_GROW', false); // si true, les plantes poussent dès qu'on les met en terre
define('FISHING', false); // si true, les players pêchent constemment
define('CACHED_INVENT', true); // si false, l'inventaire n'est pas cached
define('CACHED_KILLS', true); // si false, infos>kills n'est pas cached
define('CACHED_QUESTS', true); // si false, logs>quests n'est pas cached
define('CACHED_CLASSEMENTS', true); // si false, classemens.php n'est pas cached
define('AUTO_BREAK', false); // si true, l'équipement casse (100% de chance)
define('AUTO_FAIL', false); // si true, les attaques ratent forcément
