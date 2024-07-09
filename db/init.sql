-- Adminer 4.8.1 MySQL 10.4.28-MariaDB dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

SET NAMES utf8mb4;

DROP TABLE IF EXISTS `altars`;
CREATE TABLE `altars` (
  `player_id` int(11) NOT NULL,
  `wall_id` int(11) NOT NULL,
  PRIMARY KEY (`player_id`,`wall_id`),
  KEY `wall_id` (`wall_id`),
  CONSTRAINT `altars_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`),
  CONSTRAINT `altars_ibfk_2` FOREIGN KEY (`wall_id`) REFERENCES `map_walls` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `coords`;
CREATE TABLE `coords` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `x` int(11) NOT NULL DEFAULT 0,
  `y` int(11) NOT NULL DEFAULT 0,
  `z` int(11) NOT NULL DEFAULT 0,
  `plan` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `forums_keywords`;
CREATE TABLE `forums_keywords` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `postName` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `items`;
CREATE TABLE `items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `private` int(11) NOT NULL DEFAULT 0,
  `enchanted` int(1) NOT NULL DEFAULT 0,
  `vorpal` int(1) NOT NULL DEFAULT 0,
  `cursed` int(1) NOT NULL DEFAULT 0,
  `element` varchar(255) NOT NULL DEFAULT '',
  `blessed_by_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `blessed_by_id` (`blessed_by_id`),
  CONSTRAINT `items_ibfk_1` FOREIGN KEY (`blessed_by_id`) REFERENCES `players` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `items` (`id`, `name`, `private`, `enchanted`, `vorpal`, `cursed`, `element`, `blessed_by_id`) VALUES
(1,	'or',	0,	0,	0,	0,	'',	NULL),
(2,	'alcool_tourbe',	0,	0,	0,	0,	'',	NULL),
(3,	'altar',	0,	0,	0,	0,	'',	NULL),
(4,	'arbalete_poing',	0,	0,	0,	0,	'',	NULL),
(5,	'arc',	0,	0,	0,	0,	'',	NULL),
(6,	'armure_boue',	0,	0,	0,	0,	'',	NULL),
(7,	'armure_matelassee',	0,	0,	0,	0,	'',	NULL),
(8,	'baton_marche',	0,	0,	0,	0,	'',	NULL),
(9,	'bottes_marche',	0,	0,	0,	0,	'',	NULL),
(10,	'bouclier_parma',	0,	0,	0,	0,	'',	NULL),
(11,	'canne_a_peche',	0,	0,	0,	0,	'',	NULL),
(12,	'carreau',	0,	0,	0,	0,	'',	NULL),
(13,	'casque_illyrien',	0,	0,	0,	0,	'',	NULL),
(14,	'coffre_bois',	0,	0,	0,	0,	'',	NULL),
(15,	'encre',	0,	0,	0,	0,	'',	NULL),
(16,	'fleche',	0,	0,	0,	0,	'',	NULL),
(17,	'fustibale',	0,	0,	0,	0,	'',	NULL),
(18,	'gladius_entrainement',	0,	0,	0,	0,	'',	NULL),
(19,	'gladius',	0,	0,	0,	0,	'',	NULL),
(20,	'sceptre',	0,	0,	0,	0,	'',	NULL),
(21,	'hache_entrainement',	0,	0,	0,	0,	'',	NULL),
(22,	'lance',	0,	0,	0,	0,	'',	NULL),
(23,	'mur_bois',	0,	0,	0,	0,	'',	NULL),
(24,	'mur_bois_petrifie',	0,	0,	0,	0,	'',	NULL),
(25,	'mur_pierre',	0,	0,	0,	0,	'',	NULL),
(26,	'parchemin_sort',	0,	0,	0,	0,	'',	NULL),
(27,	'parchemin',	0,	0,	0,	0,	'',	NULL),
(28,	'piedestal_pierre',	0,	0,	0,	0,	'',	NULL),
(29,	'javelot_entrainement',	0,	0,	0,	0,	'',	NULL),
(30,	'pioche',	0,	0,	0,	0,	'',	NULL),
(31,	'projectile_magique',	0,	0,	0,	0,	'',	NULL),
(32,	'route',	0,	0,	0,	0,	'',	NULL),
(33,	'pugio',	0,	0,	0,	0,	'',	NULL),
(34,	'savon',	0,	0,	0,	0,	'',	NULL),
(35,	'table_bois',	0,	0,	0,	0,	'',	NULL),
(36,	'torche',	0,	0,	0,	0,	'',	NULL),
(37,	'anneau_horizon',	0,	0,	0,	0,	'',	NULL),
(38,	'anneau_caprice',	0,	0,	0,	0,	'',	NULL),
(39,	'anneau_puissance',	0,	0,	0,	0,	'',	NULL),
(40,	'armure_boue',	0,	0,	0,	1,	'',	NULL),
(41,	'bottes_sept_lieux',	0,	0,	0,	0,	'',	NULL),
(42,	'obole_sacree',	0,	0,	0,	0,	'',	NULL),
(43,	'armure_ecailles',	0,	0,	0,	0,	'',	NULL),
(44,	'belier',	0,	0,	0,	0,	'',	NULL),
(45,	'bouclier_clipeus',	0,	0,	0,	0,	'',	NULL),
(46,	'carnyx',	0,	0,	0,	0,	'',	NULL),
(47,	'javelot',	0,	0,	0,	0,	'',	NULL),
(48,	'aulos',	0,	0,	0,	0,	'',	NULL),
(49,	'baton_pellerin',	0,	0,	0,	0,	'',	NULL),
(50,	'bottes_talroval',	0,	0,	0,	0,	'',	NULL),
(51,	'coffre_bois_petrifie',	0,	0,	0,	0,	'',	NULL),
(52,	'cuirasse',	0,	0,	0,	0,	'',	NULL),
(53,	'flagrum',	0,	0,	0,	0,	'',	NULL),
(54,	'statue_ailee',	0,	0,	0,	0,	'',	NULL),
(55,	'targe',	0,	0,	0,	0,	'',	NULL),
(56,	'boleadoras',	0,	0,	0,	0,	'',	NULL),
(57,	'casse_tete',	0,	0,	0,	0,	'',	NULL),
(58,	'encre_tatouage',	0,	0,	0,	0,	'',	NULL),
(59,	'ikula_ceremoniel',	0,	0,	0,	0,	'',	NULL),
(60,	'manteau_feuillage',	0,	0,	0,	0,	'',	NULL),
(61,	'marque_main_blanche',	0,	0,	0,	0,	'',	NULL),
(62,	'robe_mage',	0,	0,	0,	0,	'',	NULL),
(63,	'cymbale',	0,	0,	0,	0,	'',	NULL),
(64,	'armure_hoplitique',	0,	0,	0,	0,	'',	NULL),
(65,	'bouclier_ancile',	0,	0,	0,	0,	'',	NULL),
(66,	'diademe',	0,	0,	0,	0,	'',	NULL),
(67,	'gastraphete',	0,	0,	0,	0,	'',	NULL),
(68,	'lame_benie',	0,	0,	0,	0,	'',	NULL),
(69,	'phorminx',	0,	0,	0,	0,	'',	NULL),
(70,	'piedestal',	0,	0,	0,	0,	'',	NULL),
(71,	'pilum',	0,	0,	0,	0,	'',	NULL),
(72,	'statue_gisant',	0,	0,	0,	0,	'',	NULL),
(73,	'statue_heroique',	0,	0,	0,	0,	'',	NULL),
(74,	'statue_monstrueuse',	0,	0,	0,	0,	'',	NULL),
(75,	'casque_phrygien',	0,	0,	0,	0,	'',	NULL),
(76,	'coffre_metal',	0,	0,	0,	0,	'',	NULL),
(77,	'cotte_mailles',	0,	0,	0,	0,	'',	NULL),
(78,	'grenade',	0,	0,	0,	0,	'',	NULL),
(79,	'labrys',	0,	0,	0,	0,	'',	NULL),
(80,	'marteau_guerre',	0,	0,	0,	0,	'',	NULL),
(81,	'biere_redoraane',	0,	0,	0,	0,	'',	NULL),
(82,	'conque',	0,	0,	0,	0,	'',	NULL),
(83,	'armet_incruste',	0,	0,	0,	0,	'',	NULL),
(84,	'trident',	0,	0,	0,	0,	'',	NULL),
(85,	'adonis',	0,	0,	0,	0,	'',	NULL),
(86,	'pierre',	0,	0,	0,	0,	'',	NULL),
(87,	'cendre',	0,	0,	0,	0,	'',	NULL),
(88,	'tourbe',	0,	0,	0,	0,	'',	NULL),
(89,	'bois',	0,	0,	0,	0,	'',	NULL),
(90,	'bronze',	0,	0,	0,	0,	'',	NULL),
(91,	'salpetre',	0,	0,	0,	0,	'',	NULL),
(92,	'nickel',	0,	0,	0,	0,	'',	NULL),
(93,	'cuir',	0,	0,	0,	0,	'',	NULL),
(94,	'bois_petrifie',	0,	0,	0,	0,	'',	NULL),
(95,	'pierre_mana',	0,	0,	0,	0,	'',	NULL),
(96,	'nara',	0,	0,	0,	0,	'',	NULL),
(97,	'ivoire',	0,	0,	0,	0,	'',	NULL),
(98,	'lotus_noir',	0,	0,	0,	0,	'',	NULL),
(99,	'houblon',	0,	0,	0,	0,	'',	NULL),
(100,	'lichen_sacre',	0,	0,	0,	0,	'',	NULL),
(101,	'coco',	0,	0,	0,	0,	'',	NULL),
(102,	'astral',	0,	0,	0,	0,	'',	NULL),
(103,	'cornemuse',	0,	0,	0,	0,	'',	NULL),
(104,	'baton_marche',	0,	1,	0,	0,	'',	NULL),
(105,	'armure_boue',	0,	1,	0,	0,	'',	NULL),
(106,	'baton_marche',	0,	0,	1,	0,	'',	NULL),
(107,	'baton_marche',	0,	0,	0,	1,	'',	NULL),
(109,	'poing',	0,	0,	0,	0,	'',	NULL);

DROP TABLE IF EXISTS `items_asks`;
CREATE TABLE `items_asks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `price` int(11) NOT NULL,
  `n` int(11) NOT NULL,
  `stock` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `item_id` (`item_id`),
  KEY `player_id` (`player_id`),
  CONSTRAINT `items_asks_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`),
  CONSTRAINT `items_asks_ibfk_2` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `items_bids`;
CREATE TABLE `items_bids` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `price` int(11) NOT NULL,
  `n` int(11) NOT NULL,
  `stock` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `item_id` (`item_id`),
  KEY `player_id` (`player_id`),
  CONSTRAINT `items_bids_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`),
  CONSTRAINT `items_bids_ibfk_2` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `map_dialogs`;
CREATE TABLE `map_dialogs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `coords_id` int(11) NOT NULL,
  `params` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `coords_id` (`coords_id`),
  CONSTRAINT `map_dialogs_ibfk_1` FOREIGN KEY (`coords_id`) REFERENCES `coords` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `map_elements`;
CREATE TABLE `map_elements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `coords_id` int(11) NOT NULL,
  `endTime` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `coords_id` (`coords_id`),
  CONSTRAINT `map_elements_ibfk_1` FOREIGN KEY (`coords_id`) REFERENCES `coords` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `map_foregrounds`;
CREATE TABLE `map_foregrounds` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `coords_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `coords_id` (`coords_id`),
  CONSTRAINT `map_foregrounds_ibfk_1` FOREIGN KEY (`coords_id`) REFERENCES `coords` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `map_items`;
CREATE TABLE `map_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `coords_id` int(11) NOT NULL,
  `n` int(11) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `item_id` (`item_id`),
  KEY `coords_id` (`coords_id`),
  CONSTRAINT `map_items_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`),
  CONSTRAINT `map_items_ibfk_2` FOREIGN KEY (`coords_id`) REFERENCES `coords` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `map_plants`;
CREATE TABLE `map_plants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `coords_id` int(11) NOT NULL,
  `params` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `coords_id` (`coords_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `map_tiles`;
CREATE TABLE `map_tiles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `coords_id` int(11) NOT NULL,
  `foreground` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `coords_id` (`coords_id`),
  CONSTRAINT `map_tiles_ibfk_1` FOREIGN KEY (`coords_id`) REFERENCES `coords` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `map_triggers`;
CREATE TABLE `map_triggers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `coords_id` int(11) NOT NULL,
  `params` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `coords_id` (`coords_id`),
  CONSTRAINT `map_triggers_ibfk_2` FOREIGN KEY (`coords_id`) REFERENCES `coords` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `map_walls`;
CREATE TABLE `map_walls` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `player_id` int(11) DEFAULT NULL,
  `coords_id` int(11) NOT NULL,
  `damages` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `player_id` (`player_id`),
  KEY `coords_id` (`coords_id`),
  CONSTRAINT `map_walls_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`),
  CONSTRAINT `map_walls_ibfk_2` FOREIGN KEY (`coords_id`) REFERENCES `coords` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `players`;
CREATE TABLE `players` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `psw` varchar(255) NOT NULL DEFAULT '',
  `mail` varchar(255) NOT NULL DEFAULT '',
  `ip` varchar(255) NOT NULL DEFAULT '',
  `coords_id` int(11) NOT NULL DEFAULT 0,
  `race` varchar(255) NOT NULL DEFAULT '',
  `xp` int(11) NOT NULL DEFAULT 0,
  `pi` int(11) NOT NULL DEFAULT 0,
  `pr` int(11) NOT NULL DEFAULT 0,
  `malus` int(11) NOT NULL DEFAULT 0,
  `fatigue` int(11) NOT NULL DEFAULT 0,
  `godId` int(11) NOT NULL DEFAULT 0,
  `pf` int(11) NOT NULL DEFAULT 0,
  `rank` int(11) NOT NULL DEFAULT 0,
  `avatar` varchar(255) NOT NULL DEFAULT '',
  `portrait` varchar(255) NOT NULL DEFAULT '',
  `text` text NOT NULL DEFAULT 'Je suis nouveau, frappez-moi!',
  `story` text NOT NULL DEFAULT 'Je préfère garder cela pour moi.',
  `quest` varchar(255) DEFAULT 'gaia',
  `faction` varchar(255) NOT NULL DEFAULT '',
  `nextTurnTime` int(11) NOT NULL DEFAULT 0,
  `lastActionTime` int(11) NOT NULL DEFAULT 0,
  `lastLoginTime` int(11) NOT NULL DEFAULT 0,
  `antiBerserkTime` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `coords_id` (`coords_id`),
  CONSTRAINT `players_ibfk_1` FOREIGN KEY (`coords_id`) REFERENCES `coords` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `players_actions`;
CREATE TABLE `players_actions` (
  `player_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `type` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`player_id`,`name`),
  CONSTRAINT `players_actions_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `players_bonus`;
CREATE TABLE `players_bonus` (
  `player_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `n` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`player_id`,`name`),
  CONSTRAINT `players_bonus_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `players_effects`;
CREATE TABLE `players_effects` (
  `player_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `endTime` int(11) DEFAULT NULL,
  PRIMARY KEY (`player_id`,`name`),
  CONSTRAINT `players_effects_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `players_followers`;
CREATE TABLE `players_followers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `player_id` int(11) NOT NULL,
  `foreground_id` int(11) NOT NULL,
  `params` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `player_id` (`player_id`),
  KEY `foreground_id` (`foreground_id`),
  CONSTRAINT `players_followers_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`),
  CONSTRAINT `players_followers_ibfk_2` FOREIGN KEY (`foreground_id`) REFERENCES `map_foregrounds` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `players_forum_missives`;
CREATE TABLE `players_forum_missives` (
  `player_id` int(11) NOT NULL,
  `name` int(11) NOT NULL,
  `viewed` int(1) NOT NULL DEFAULT 0,
  KEY `player_id` (`player_id`),
  CONSTRAINT `players_forum_missives_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `players_forum_rewards`;
CREATE TABLE `players_forum_rewards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `from_player_id` int(11) NOT NULL,
  `to_player_id` int(11) NOT NULL,
  `postName` varchar(255) NOT NULL DEFAULT '',
  `img` varchar(255) NOT NULL DEFAULT '',
  `pr` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `from_player_id` (`from_player_id`),
  KEY `to_player_id` (`to_player_id`),
  CONSTRAINT `players_forum_rewards_ibfk_1` FOREIGN KEY (`from_player_id`) REFERENCES `players` (`id`),
  CONSTRAINT `players_forum_rewards_ibfk_2` FOREIGN KEY (`to_player_id`) REFERENCES `players` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `players_ips`;
CREATE TABLE `players_ips` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip` varchar(255) NOT NULL DEFAULT '',
  `expTime` int(11) NOT NULL DEFAULT 0,
  `failed` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `players_items`;
CREATE TABLE `players_items` (
  `player_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `n` int(11) NOT NULL DEFAULT 0,
  `equiped` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`player_id`,`item_id`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `players_items_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`),
  CONSTRAINT `players_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `players_items_bank`;
CREATE TABLE `players_items_bank` (
  `player_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `n` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`player_id`,`item_id`),
  KEY `item_id` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `players_logs`;
CREATE TABLE `players_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `player_id` int(11) NOT NULL,
  `target_id` int(11) NOT NULL,
  `text` varchar(255) NOT NULL DEFAULT '',
  `type` varchar(255) NOT NULL DEFAULT '',
  `plan` varchar(255) NOT NULL DEFAULT '',
  `time` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `player_id` (`player_id`),
  KEY `target_id` (`target_id`),
  CONSTRAINT `players_logs_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`),
  CONSTRAINT `players_logs_ibfk_2` FOREIGN KEY (`target_id`) REFERENCES `players` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `players_options`;
CREATE TABLE `players_options` (
  `player_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  KEY `player_id` (`player_id`),
  CONSTRAINT `players_options_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `players_options` (`player_id`, `name`) VALUES
(1,	'isAdmin');

DROP TABLE IF EXISTS `players_pnjs`;
CREATE TABLE `players_pnjs` (
  `player_id` int(11) NOT NULL,
  `pnj_id` int(11) NOT NULL,
  KEY `player_id` (`player_id`),
  KEY `pnj_id` (`pnj_id`),
  CONSTRAINT `players_pnjs_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`),
  CONSTRAINT `players_pnjs_ibfk_2` FOREIGN KEY (`pnj_id`) REFERENCES `players` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `players_psw`;
CREATE TABLE `players_psw` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `player_id` int(11) NOT NULL DEFAULT 0,
  `uniqid` varchar(255) NOT NULL DEFAULT '',
  `sentTime` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;


DROP TABLE IF EXISTS `players_quests`;
CREATE TABLE `players_quests` (
  `player_id` int(11) NOT NULL,
  `quest` varchar(255) NOT NULL DEFAULT '',
  `step` int(11) NOT NULL DEFAULT 0,
  KEY `player_id` (`player_id`),
  CONSTRAINT `players_quests_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `players_upgrades`;
CREATE TABLE `players_upgrades` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `player_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `cost` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `player_id` (`player_id`),
  CONSTRAINT `players_upgrades_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- 2024-07-09 11:50:21

