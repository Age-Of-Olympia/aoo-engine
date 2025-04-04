CREATE TABLE `map_routes` (
  `id` int(11) AUTO_INCREMENT NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `coords_id` int(11) DEFAULT NULL,
  `player_id` int(11) DEFAULT NULL,
   PRIMARY KEY (id),
   CONSTRAINT `players_map_routes_fk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`),
   CONSTRAINT `coords_map_routes_fk_2` FOREIGN KEY (`coords_id`) REFERENCES `coords` (`id`)
);