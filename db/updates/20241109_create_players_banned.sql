DROP TABLE IF EXISTS `players_banned`;
CREATE TABLE `players_banned` (
  `player_id` int(11) NOT NULL,
  `ips` text NOT NULL,
  `text` varchar(255) NOT NULL,
  KEY `player_id` (`player_id`),
  CONSTRAINT `players_banned_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
