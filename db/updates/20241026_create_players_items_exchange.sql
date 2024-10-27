DROP TABLE IF EXISTS `items_exchanges`;
CREATE TABLE `items_exchanges` (
                                   `id` int(11) NOT NULL AUTO_INCREMENT,
                                   `player_id` int(11) NOT NULL,
                                   `target_id` int(11) NOT NULL,
                                   `player_ok` tinyint(1) NOT NULL DEFAULT 0,
                                   `target_ok` tinyint(1) NOT NULL DEFAULT 0,
                                   `update_time` int(11) NOT NULL,
                                   PRIMARY KEY (`id`),
                                   KEY `items_exchanges_fk_1` (`player_id`),
                                   KEY `items_exchanges_fk_2` (`target_id`),
                                   CONSTRAINT `items_exchanges_fk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`),
                                   CONSTRAINT `items_exchanges_fk_2` FOREIGN KEY (`target_id`) REFERENCES `players` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `players_items_exchanges`;
CREATE TABLE `players_items_exchanges` (
      `exchange_id` int(11) NOT NULL,
      `item_id` int(11) NOT NULL,
      `n` int(11) NOT NULL,
      `player_id` int(11) NOT NULL,
      `target_id` int(11) NOT NULL,
      CONSTRAINT `players_items_exchanges_fk_1` FOREIGN KEY (`exchange_id`) REFERENCES `items_exchanges` (`id`),
      CONSTRAINT `players_items_exchanges_fk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`),
      CONSTRAINT `players_items_exchanges_fk_3` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`),
      CONSTRAINT `players_items_exchanges_fk_4` FOREIGN KEY (`target_id`) REFERENCES `players` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

