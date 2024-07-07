ALTER TABLE `players_forum_rewards`
ADD `topName` varchar(255) COLLATE 'utf8mb4_general_ci' NOT NULL DEFAULT '' AFTER `postName`;
