 
ALTER TABLE `players`
ADD `lastLoginTime` int(11) NOT NULL DEFAULT '0' AFTER `lastActionTime`;

ALTER TABLE `players`
ADD `ip` varchar(255) COLLATE 'utf8mb4_general_ci' NOT NULL DEFAULT '' AFTER `mail`;
