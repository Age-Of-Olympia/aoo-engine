CREATE TABLE `altars` (

  `id` int(11) NOT NULL AUTO_INCREMENT,

  `coords_id` int(11) NOT NULL,

  `godId` int(11) NOT NULL DEFAULT 0,

  `plan` varchar(255) NOT NULL DEFAULT '',  

  PRIMARY KEY (`id`),

  KEY `coords_id` (`coords_id`),

  CONSTRAINT `fk_altars_coords` FOREIGN KEY (`coords_id`) REFERENCES `coords` (`id`)

) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;