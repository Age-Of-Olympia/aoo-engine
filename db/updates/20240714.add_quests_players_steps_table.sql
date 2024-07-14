

-- 2024-07-14 16:44:58

DROP TABLE IF EXISTS `players_quests_steps`;
CREATE TABLE `players_quests_steps` (
  `player_id` int(11) NOT NULL,
  `quest_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `endTime` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`player_id`,`quest_id`,`name`),
  KEY `quest_id` (`quest_id`),
  CONSTRAINT `players_quests_steps_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`),
  CONSTRAINT `players_quests_steps_ibfk_2` FOREIGN KEY (`quest_id`) REFERENCES `quests` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
