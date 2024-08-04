ALTER TABLE `players_logs`
ADD `hiddenText` text COLLATE 'utf8mb4_general_ci' NOT NULL DEFAULT '' AFTER `text`;
