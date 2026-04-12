ALTER TABLE `items` DROP FOREIGN KEY items_ibfk_1;
ALTER TABLE `items` DROP INDEX `blessed_by_id`;
ALTER TABLE `items` DROP `blessed_by_id`;
ALTER TABLE `items` ADD `exotique` varchar(20) DEFAULT NULL AFTER `is_bankable`;