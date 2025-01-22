ALTER TABLE `coords` ADD COLUMN `mask` varchar(35) DEFAULT NULL; 

ALTER TABLE `coords` ADD COLUMN `scrollingMask` float(11); 

ALTER TABLE `coords` ADD COLUMN `verticalScrolling` int(11); 
