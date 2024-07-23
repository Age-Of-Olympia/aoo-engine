
DROP TABLE IF EXISTS `players_connections`;
CREATE TABLE `players_connections` (
                                      `id`  int(11) NOT NULL AUTO_INCREMENT,
                                      `player_id` int(11) NOT NULL,
                                      `ip` varchar(255) NOT NULL DEFAULT '',
                                      `time` int(11) NOT NULL DEFAULT 0,
                                      `footprint` varchar(255) NOT NULL DEFAULT '',
                                      PRIMARY KEY (`id`),
                                      KEY `player_id` (`player_id`),
                                      CONSTRAINT `players_connections_fk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `players`
    DROP column `ip`;