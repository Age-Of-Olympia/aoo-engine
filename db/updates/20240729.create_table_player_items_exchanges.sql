DROP TABLE IF EXISTS `players_items_exchanges`;
CREATE TABLE `players_items_exchanges` (
                              `exchange_id` int(11) NOT NULL,
                              `item_id` int(11) NOT NULL,
                              `n` int(11) NOT NULL default 1,
                              `player_id` int(11) NOT NULL,
                              `target_id` int(11) NOT NULL,
                              KEY `exchange_id` (`exchange_id`),
                              KEY `item_id` (`item_id`),
                              KEY `player_id` (`player_id`),
                              KEY `target_id` (`target_id`),
                              CONSTRAINT `players_items_exchanges_fk_1` FOREIGN KEY (`exchange_id`) REFERENCES `items_exchanges` (`id`),
                              CONSTRAINT `players_items_exchanges_fk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`),
                              CONSTRAINT `players_items_exchanges_fk_3` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`),
                              CONSTRAINT `players_items_exchanges_fk_4` FOREIGN KEY (`target_id`) REFERENCES `players` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
