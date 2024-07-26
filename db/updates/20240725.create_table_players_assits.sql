CREATE TABLE `players_assists` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `player_id` int(11) NOT NULL,
  `target_id` int(11) NOT NULL,
  `player_rank` int(11) NOT NULL,
  `damages` int NOT NULL DEFAULT '1',
  `time` int NOT NULL DEFAULT '0',
  FOREIGN KEY (`player_id`) REFERENCES `players` (`id`),
  FOREIGN KEY (`target_id`) REFERENCES `players` (`id`)
);
