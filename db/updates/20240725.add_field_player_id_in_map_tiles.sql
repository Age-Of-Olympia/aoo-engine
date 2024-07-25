ALTER TABLE `map_tiles`
ADD `player_id` int(11) NULL,
ADD FOREIGN KEY (`player_id`) REFERENCES `players` (`id`);
