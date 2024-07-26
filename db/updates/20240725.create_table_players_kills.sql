CREATE TABLE `players_kills` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `player_id` int(11) NOT NULL,
  `target_id` int(11) NOT NULL,
  `player_rank` int NOT NULL DEFAULT '1',
  `target_rank` int NOT NULL DEFAULT '1',
  `xp` int NOT NULL DEFAULT '0',
  `assist` int NOT NULL DEFAULT '0',
  `time` int NOT NULL DEFAULT '0',
  `plan` varchar(255) NOT NULL DEFAULT '',
  FOREIGN KEY (`player_id`) REFERENCES `players` (`id`),
  FOREIGN KEY (`target_id`) REFERENCES `players` (`id`)
);
