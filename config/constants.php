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

define('CARACS_TXT', array(
    'a'=>'Action',
    'mvt'=>'Mouvements',
    'p'=>'Perception',
    'pv'=>'Points de Vie',
    'cc'=>'Capacité de Combat',
    'ct'=>'Capacité de Tir',
    'f'=>'Force',
    'e'=>'Endurance',
    'agi'=>'Agilité',
    'pm'=>'Points de Magie',
    'fm'=>'Force Mentale',
    'm'=>'Puissance Magique',
    'r'=>'Récupération',
    'rm'=>'Récupération Magique',
    'ae'=> 'Action d\'Equipement',
    'foi'=>'Nombre de points de Foi',
    'xp'=>'Points d\'Expérience',
    'pi'=>'Points d\'Investissement',
    'malus'=>'Malus',
));

define('CARACS_TXT_LONG', array(
    'a'=>'Les Action servent à interagir avec le monde, à attaquer, récolter, s\'entrainer',
    'mvt'=>'Les Mouvements sont votre nombre de déplacements possibles par tour',
    'p'=>'La Perception correspond au nombre de cases que vous pouvez voir autour de vous',
    'pv'=>'Points de Vie, ils sont réduits par les dégâts subis. Si vos PV tombent à 0, vous mourrez.',
    'cc'=>'Capacité de Combat, correspond au nombre de dés que vous lancez pour attaquer au corps à corps et pour vous défendre des attaques au corps à corps et à distance',
    'ct'=>'Capacité de Tir, correspond au nombre de dés que vous lancez pour attaquer à distance',
    'f'=>'Force, est utilisé pour calculer les dégâts au corps à corps et à distance',
    'e'=>'Endurance, permet de résister aux dégâts physiques',
    'agi'=>'Agilité, utilisée pour esquiver les attaques physiques et au vol',
    'pm'=>'Points de Magie, votre réserve de magie, utilisée pour lancer des sorts',
    'fm'=>'Force Mentale, correspond au nombre de dés que vous lancez pour attaquer/défendre des attaques magiques',
    'm'=>'Puissance Magique, utilisée pour calculer les dégâts magiques',
    'r'=>'Récupération, nombre de points de vie récupérés par tour',
    'rm'=>'Récupération Magique, nombre de points de magie récupérés par tour',
    'ae'=> 'Action d\'Equipement, permet d\'équiper ou de déséquiper un objet',
    'foi'=>'Nombre de points de Foi, gagné en priant votre dieu',
    'xp'=>'Points d\'Expérience',
    'pi'=>'Points d\'Investissement, permet d\'améliorer vos caractéristiques',
    'malus'=>'Malus vos malus réduisent vos jets de défense, ils sont récupérés au fil du temps',
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
// Nombre de jours pendant lesquels c'est autorisé de donner un cookie sur un post
define('MAX_DAYS_COOKIE_FORUM', 14);
// nombre de PR par post (1PR=10)
define('PR_PER_POST', 10);
// nombre de PR par post (1PR=10)
define('PR_PER_COOKIE', 3);
// nombre de PR par post (1PR=10)
define('PR_PER_COOKIE_SAME_FACTION', 1);
//active les cookie dans les missives
define('ENABLE_NO_PR_COOKIES_IN_MISSIVES', TRUE);


// Coefficient de division en base pour affichage
define('COEFFICIENT_PR', 10);

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

    //nouveaux effets
    'maladresse' => 'ra-cut-palm',
    'dexterite' => 'ra-plain-dagger',
    'vulnerabilite' => 'ra-broken-shield',
    'protection' => 'ra-shield',
    'aveuglement' => 'ra-bleeding-eye',
    'acuite_visuelle' => 'ra-eyeball',
    'ralentissement' => 'ra-snail',
    'furtif' => 'ra-player',

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
    'aveuglement' => 'p',

    'styx'=>'mvt',
    'sang'=>'fm',
    'lave'=>'a',
));

define('ELE_BUFFS', array(
    'acuite_visuelle' => 'p',
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

    //murs
    'mur_pierre'=>150,
    'mur_pierre_broken'=>150,
    'mur_pierre_bleue'=>150,
    'mur_pierre_bleue_broken'=>150,
    'mur_noir'=>120,
    'mur_noir_broken'=>120,
    'mur_bois'=>100,
    'mur_bois_broken'=>100,
    'mur_bois_petrifie'=>120,
    'mur_bois_petrifie_broken'=>120,
    'mur_vegetal'=>120,
    'mur_vegetal_broken'=>120,
    'mur_fer'=>180,
    'mur_fer_broken'=>180,
    'mur_crepusculaire'=>120,
    'mur_crepusculaire_broken'=>120,
    'mur_blanc'=>180,
    'mur_blanc_broken'=>180,
    'muret'=>40,
    'barricade'=>40,

    //coffres
    'coffre_metal'=>1,
    'coffre_bois'=>1,
    'coffre_bois_petrifie'=>1,
    'coffre_metal_broken'=>1,
    'coffre_bois_broken'=>1,
    'coffre_bois_petrifie_broken'=>1,

    'pierre_precieuse'=>500,

    //décos
    'altar'=>25,
    'altar_broken'=>25,
    
    'piedestal'=>15,
    'piedestal_broken'=>15,
    'piedestal_pierre'=>10,
    'piedestal_pierre_broken'=>10,
    
    'table_bois'=>5,
    'table_bois_broken'=>5,	
    'tonneau'=>5,
    'tonneau_broken'=>5,
    'torche_sol'=>10,
    'torche_sol_broken'=>10,
    'trone'=>25,
    'trone_broken'=>25,			
    'tombe2'=>10,
    'statue_monstrueuse'=>10,
    'statue_ailee'=>10,
    'statue_heroique'=>10,
    'statue_gisant'=>10,
    'statue_forestiere'=>10,
    'roue_a_aubes'=>10,
    'lanternesurpied_geant'=>10,
    'monolithe_flamboyant'=>10,
    'statue_colosses'=>10,
    'totem_crane'=>10,
    'statue_garde'=>10,
    'statue_servant'=>10,
    'totem_sauvage'=>10,
    'totem_magique'=>10,
    'pilier_nain'=>10,
    'pilier'=>10,
    'statue_noble'=>10,
    'flamme_bleue'=>10,
    'sarcophage'=>50,
    'statue_kraken'=>30,
    'tombe'=>30,
    'tombe_detruite'=>10,

    //cocotiers
    
    'cocotier1'=>1,
    'cocotier2'=>1,
    'cocotier3'=>1,

//ressources : mettre les damages à -1 pour qu'ils apparaissent comme "récoltable", -2 pour "épuisé"

    'arbre1'=>-1,
    'arbre2'=>-1,
    'arbre3'=>-1,
    'arbre4'=>-1,
    'arbre5'=>-1,
    'arbre6'=>-1,

    'arbre_petrifie1'=>-1,
    'arbre_petrifie2'=>-1,
    'arbre_petrifie3'=>-1,
    'arbre_petrifie4'=>-1,
    'arbre_petrifie5'=>-1,
    'arbre_petrifie6'=>-1,

    'cendre'=>-1,
    'cuir'=>-1,
    'cuivre'=>-1,
    'etain'=>-1,
    'fer'=>-1,
    'nickel'=>-1,
    'salpetre'=>-1,
    'tourbe'=>-1,
    'mana'=>-1,
    'bronze'=>-1,

    'herbe1'=>-1,
    'herbe2'=>-1,
    'herbe3'=>-1,

    'jungle1'=>-1,
    'jungle2'=>-1,
    'jungle3'=>-1,  

    'pierre1'=>-1,
    'pierre2'=>-1,
    'pierre3'=>-1,     
    
    'pierre_noire1'=>-1,
    'pierre_noire2'=>-1,
    'pierre_noire3'=>-1,   
        
    'rocher_desert1'=>-1,
    'rocher_desert2'=>-1,
    'rocher_desert3'=>-1 


));

/*
 * items options & emplacements
 */


define('DMG_CRIT', 5); // 5% de critique (+3 dégâts) sur un ennemi sans casque

define('ITEM_DROP', 10); // 10% de drop sur les désarmements et loots

define('ITEM_BREAK', 0); // 1% de break sur une attaque ou une défense

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
    'corruption_du_metal'=>0,
    'corruption_du_bronze'=>0,
    'corruption_du_bois'=>0,
    'corruption_des_plantes'=>0,
    'corruption_du_cuir'=>0
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


define('LOOT_CHANCE_DEFAULT', 30);


define('LOOT_CHANCE', array(
    'or'=>200,
    'anneau_caprice'=>200,
    'anneau_ferocite'=>200,
    'anneau_finesse'=>200,
    'anneau_horizon'=>200,
    'anneau_pretention'=>200,
    'anneau_puissance'=>200,
    'anneau_souplesse'=>200,
    'anneau_tenacite'=>200,
    'bois_petrifie'=>50,
    'cuivre'=>50,
    'cendre'=>50,
    'fer'=>50,
    'tourbe'=>50,
    'cuir'=>80,
    'etain'=>80,
    'nickel'=>80,
    'pierre_mana'=>80,
    'salpetre'=>80,
    'emeraude'=>100,
    'lapis_lazuli'=>100,
    'opale'=>100,
    'rubis'=>100,
    'plume_doree'=>100,
	'plume_irisee'=>100,
	'plume_ebenne'=>100
));

/*
    taux de réapparition des plantes par trigger
    7 => 1 chance sur 7
*/
define('GROW_RATE', array(
    'adonis'=>2,
    'astral'=>10,
    'cafe'=>3,
    'houblon'=>3,
    'lichen_sacre'=>7,
    'lotus_noir'=>20,
    'menthe'=>7,
    'pavot'=>7
));


/*
 * costs & recups
 */

define('TRAVEL_COST', 15);      // travelling cost 15Po
define('ENERGIE_CST', 7);        // valeur de la constante d'énergie
define('MALUS_PER_REST', 4);      // resting delete 4 malus
define('MALUS_PER_MINE', 20);      // malus quand on creuse sans pioche
define('MALUS_PER_TURNS', 9);   // recup 9 malus / turns
define('XP_PER_TURNS', 5);      // base 5 xp - rank / turns
define('XP_PER_MINE', 1);       // chaque case creusé rapporte 1xp
define('DEATH_XP', 10);       // chaque case creusé rapporte 1xp
define('ACTION_XP', 5);         // base action Xp
define('SEASON_XP', 10000);      // passage du limite de saison à 10000
define('BANK_PCT', 1);          // % gain par jour en banque sans adré

define('MIN_GOLD_STOLEN', 5);
define('MAX_XP_FOR_STEALING', 3);

/*
 * errors
 */

define('ERROR_DISTANCE', "Vous n'êtes pas à bonne distance.");

define('consoleEnvKey', 'consoleENV');

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

/*
 * affichage map
 */

define('DIST_MAP_MAX', 15); // La race des cases au delà d'une distance de 15 apparaît en noir sur la map globale