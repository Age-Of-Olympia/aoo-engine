ALTER TABLE `players`
ADD `secretFaction` varchar(255) COLLATE 'utf8mb4_general_ci' NOT NULL DEFAULT '' AFTER `faction`;
