<?php

define('DOMAIN', 'http://localhost/www/aoo4/');

/* Artisanat en sommeil : les entrées d'interface (rail HUD, onglet
 * Inventaire, boutons de ligne, bouton d'aperçu) sont masquées tant que
 * la refonte portée par un bâtiment dédié n'est pas prête. La route
 * inventory.php?craft et CraftView restent fonctionnelles. */
define('CRAFT_ENABLED', false);


/*
 * Entity ID Ranges
 * Separate ID ranges for different entity types to ensure real players have sequential IDs
 */
define('ENTITY_ID_RANGES', [
    'real' => ['start' => 1, 'end' => 9999999],
    'tutorial' => ['start' => 10000000, 'end' => 19999999],
    'building' => ['start' => 20000000, 'end' => 29999999],
    'unique' => ['start' => 30000000, 'end' => 39999999],
    'scenery' => ['start' => 40000000, 'end' => 49999999],
    'resource' => ['start' => 50000000, 'end' => 59999999],
    'plant' => ['start' => 60000000, 'end' => 69999999],
    'npc' => ['start' => PHP_INT_MIN, 'end' => -1],
]);


/*
 * races : définies en base (table races), voir App\Service\RaceService.
 * getPlayableRaceNames() remplace l'ancien RACES, getAllRaceNames()
 * l'ancien RACES_EXT.
 */


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
 *
 * Le catalogue des effets (icônes, libellés, effets cachés, buffs/debuffs,
 * cycle élémentaire, corruptions et matériaux corruptibles) vit en base :
 * tables `effects` et `effect_corruption_materials`, servies par
 * App\Service\EffectService et éditées via admin/effects.php.
 */


/*
 * Ressources de carte (map_resources, ex-map_walls).
 *
 * Le catalogue des types vit en base, dans `races` (kind = structure),
 * servi par App\Service\Map\StructureTypeService : la NATURE dit ce qu'un
 * type est — « ressource » se récolte — et `pv` ne dit plus que la vie.
 */

/*
 * items options & emplacements
 */


define('DMG_CRIT', 5); // 5% de critique (+3 dégâts) sur un ennemi sans casque

define('ITEM_DROP', 10); // 10% de drop sur les désarmements et loots

define('ITEM_BREAK', 0); // 1% de break sur une attaque ou une défense

define('ITEM_LIMIT', 3);

/* ITEM_PLANTS, ITEM_CORRUPTIONS et ITEM_CORRUPT_BREAKCHANCES : en base (effects.corruption_break_chance, effect_corruption_materials). */

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


/* LOOT_CHANCE : en base (items.lootChance), lu par Player::death(). */

/* GROW_RATE : en base (items.grow_rate), lu par PlantsService::growSeed(). */


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

define('NUMBER_MAX_COMP', 15);

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

/*
 * Tutorial System
 */
// Tutorial whitelist is now managed via the admin panel at /admin/tutorial-settings.php
// No config override needed - use the database for easy editing

/* Tutorial rewards - loaded from database (tutorial_settings table)
 * Editable via /admin/tutorial-settings.php
 * Use getTutorialRewards() function to access values (lazy-loaded)
 */

/**
 * Get tutorial rewards from database
 * Lazy-loaded to avoid class loading issues in constants.php
 *
 * @return array Returns array with 'skip' and 'completion' keys, each containing 'xp' and 'pi'
 */
function getTutorialRewards(): array {
    static $rewards = null;

    if ($rewards !== null) {
        return $rewards;
    }

    /* Default values as fallback */
    $rewards = [
        'skip' => ['xp' => 50, 'pi' => 50],
        'completion' => ['xp' => 390, 'pi' => 390]
    ];

    /* Try to load from database if TutorialFeatureFlag class is available */
    if (class_exists('\App\Tutorial\TutorialFeatureFlag')) {
        try {
            $settings = \App\Tutorial\TutorialFeatureFlag::getSettings();
            $rewards['skip']['xp'] = (int)($settings['skip_reward_xp'] ?? 50);
            $rewards['skip']['pi'] = (int)($settings['skip_reward_pi'] ?? 50);
            $rewards['completion']['xp'] = (int)($settings['completion_reward_xp'] ?? 390);
            $rewards['completion']['pi'] = (int)($settings['completion_reward_pi'] ?? 390);
        } catch (\Exception $e) {
            /* If database load fails, use defaults */
            error_log('[Tutorial Rewards] Failed to load from database: ' . $e->getMessage());
        }
    }

    return $rewards;
}

/* Define constants using lazy-loaded values */
if (!defined('TUTORIAL_SKIP_REWARD')) {
    $rewards = getTutorialRewards();
    define('TUTORIAL_SKIP_REWARD', $rewards['skip']);
    define('TUTORIAL_COMPLETION_REWARD', $rewards['completion']);
}
