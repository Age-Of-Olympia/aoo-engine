ALTER TABLE `players_logs`
ADD `hiddenText` varchar(255) COLLATE 'utf8mb4_general_ci' NOT NULL DEFAULT '' AFTER `text`;
ALTER TABLE `players_logs`
CHANGE `hiddenText` `hiddenText` text COLLATE 'utf8mb4_general_ci' NOT NULL DEFAULT '' AFTER `text`;
