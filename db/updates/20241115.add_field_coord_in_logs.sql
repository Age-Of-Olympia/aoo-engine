ALTER TABLE `players_logs`
ADD `coords_id` int(11) NULL DEFAULT '0';
ALTER TABLE `players_logs`
  ADD CONSTRAINT `players_logs_coords_fk_1` FOREIGN KEY (`coords_id`) REFERENCES `coords` (`id`);
COMMIT;

ALTER TABLE `players_logs_archives`
ADD `coords_id` int(11) NULL DEFAULT '0';
ALTER TABLE `players_logs_archives`
  ADD CONSTRAINT `players_logs_archives_coords_fk_1` FOREIGN KEY (`coords_id`) REFERENCES `coords` (`id`);
COMMIT;