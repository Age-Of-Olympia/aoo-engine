ALTER TABLE `players` ADD `plain_mail` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' AFTER `mail`; 
ALTER TABLE `players` ADD COLUMN `email_bonus` BOOLEAN DEFAULT FALSE;
