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

INSERT INTO `coords` (`id`, `x`, `y`, `z`, `plan`) VALUES
(1,	0,	0,	0,	'init');

DROP TABLE IF EXISTS `items`;
CREATE TABLE `items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `private` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `items` (`id`, `name`, `private`) VALUES
(1,	'or',	0),
(2,	'pierre',	0),
(3,	'bois',	0),
(4,	'adonis',	0),
(5,	'tourbe',	0),
(6,	'cendre',	0);

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


DROP TABLE IF EXISTS `map_tiles`;
CREATE TABLE `map_tiles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `coords_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `coords_id` (`coords_id`),
  CONSTRAINT `map_tiles_ibfk_1` FOREIGN KEY (`coords_id`) REFERENCES `coords` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `map_triggers`;
CREATE TABLE `map_triggers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `coords_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `player_id` (`player_id`),
  KEY `coords_id` (`coords_id`),
  CONSTRAINT `map_triggers_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`),
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
  `coords_id` int(11) NOT NULL DEFAULT 0,
  `race` varchar(255) NOT NULL DEFAULT '',
  `xp` int(11) NOT NULL DEFAULT 0,
  `pi` int(11) NOT NULL DEFAULT 0,
  `godId` int(11) NOT NULL DEFAULT 0,
  `pf` int(11) NOT NULL DEFAULT 0,
  `rank` int(11) NOT NULL DEFAULT 0,
  `avatar` varchar(255) NOT NULL DEFAULT '',
  `portrait` varchar(255) NOT NULL DEFAULT '',
  `text` text NOT NULL DEFAULT 'Je suis nouveau, frappez-moi!',
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


DROP TABLE IF EXISTS `players_effects`;
CREATE TABLE `players_effects` (
  `player_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `endTime` int(11) DEFAULT NULL,
  PRIMARY KEY (`player_id`,`name`),
  CONSTRAINT `players_effects_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `players_items`;
CREATE TABLE `players_items` (
  `player_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `n` int(11) NOT NULL DEFAULT 0,
  `equiped` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`player_id`,`item_id`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `players_items_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`),
  CONSTRAINT `players_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
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


-- 2024-06-12 16:26:29

