ALTER TABLE `players_logs`
ADD `coords_id` int(11) NOT NULL DEFAULT '0';
ALTER TABLE `players_logs`
  ADD CONSTRAINT `players_logs_coords_fk_1` FOREIGN KEY (`coords_id`) REFERENCES `coords` (`id`);
COMMIT;