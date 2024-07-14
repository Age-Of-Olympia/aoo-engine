



DROP TABLE IF EXISTS `players_quests`;
CREATE TABLE `players_quests` (
  `player_id` int(11) NOT NULL,
  `quest_id` int(11) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `startTime` int(11) NOT NULL DEFAULT 0,
  `endTime` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`player_id`,`quest_id`),
  KEY `quest_id` (`quest_id`),
  CONSTRAINT `players_quests_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`),
  CONSTRAINT `players_quests_ibfk_2` FOREIGN KEY (`quest_id`) REFERENCES `quests` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

