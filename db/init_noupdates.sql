/*M!999999\- enable the sandbox mode */ 

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `action_conditions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `conditionType` varchar(100) NOT NULL,
  `parameters` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`parameters`)),
  `action_id` int(11) NOT NULL,
  `execution_order` int(11) DEFAULT NULL,
  `blocking` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `IDX_97C463639D32F035` (`action_id`),
  CONSTRAINT `FK_97C463639D32F035` FOREIGN KEY (`action_id`) REFERENCES `actions` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=288 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `action_conditions` DISABLE KEYS */;
INSERT INTO `action_conditions` VALUES (1,'RequiresDistance','{\"max\":1}',1,0,1);
INSERT INTO `action_conditions` VALUES (2,'MeleeCompute','{\"actorRollType\":\"cc\", \"targetRollType\": \"cc/agi\"}',1,10,0);
INSERT INTO `action_conditions` VALUES (3,'RequiresTraitValue','{ \"a\": 1 }',1,7,1);
INSERT INTO `action_conditions` VALUES (5,'RequiresWeaponType','{\"type\": [\"melee\"]}',1,1,1);
INSERT INTO `action_conditions` VALUES (6,'RequiresDistance','{\"min\":2}',2,0,1);
INSERT INTO `action_conditions` VALUES (7,'DistanceCompute','{\"actorRollType\":\"ct\", \"targetRollType\": \"cc/agi\"}',2,10,0);
INSERT INTO `action_conditions` VALUES (8,'RequiresTraitValue','{ \"a\": 1 }',2,3,1);
INSERT INTO `action_conditions` VALUES (10,'RequiresWeaponType','{\"type\": [\"tir\",\"jet\"]}',2,1,1);
INSERT INTO `action_conditions` VALUES (11,'RequiresAmmo','{}',2,4,1);
INSERT INTO `action_conditions` VALUES (12,'RequiresDistance','{\"min\":2}',3,0,1);
INSERT INTO `action_conditions` VALUES (13,'RequiresTraitValue','{ \"a\": 1, \"pm\": 4 }',3,3,1);
INSERT INTO `action_conditions` VALUES (14,'SpellCompute','{\"actorRollType\":\"fm\", \"targetRollType\": \"fm\"}',3,10,0);
INSERT INTO `action_conditions` VALUES (15,'RequiresDistance','{\"max\":1}',4,0,1);
INSERT INTO `action_conditions` VALUES (16,'RequiresTraitValue','{ \"a\": 1, \"pm\": 8 }',4,3,1);
INSERT INTO `action_conditions` VALUES (17,'SpellCompute','{\"actorRollType\":\"fm\", \"targetRollType\": \"fm\"}',4,10,0);
INSERT INTO `action_conditions` VALUES (18,'RequiresDistance','{\"max\":1}',5,0,1);
INSERT INTO `action_conditions` VALUES (19,'RequiresTraitValue','{ \"a\": 1, \"pm\": 8 }',5,3,1);
INSERT INTO `action_conditions` VALUES (20,'RequiresDistance','{\"min\":2}',6,0,1);
INSERT INTO `action_conditions` VALUES (21,'RequiresTraitValue','{ \"a\": 1, \"pm\": 12 }',6,7,1);
INSERT INTO `action_conditions` VALUES (22,'MeleeCompute','{\"actorRollType\":\"cc\", \"targetRollType\": \"cc/agi\"}',6,10,0);
INSERT INTO `action_conditions` VALUES (24,'RequiresWeaponType','{\"type\": [\"melee\"]}',6,1,1);
INSERT INTO `action_conditions` VALUES (25,'RequiresDistance','{\"max\":1}',7,0,1);
INSERT INTO `action_conditions` VALUES (26,'RequiresTraitValue','{ \"a\": 1 }',7,5,1);
INSERT INTO `action_conditions` VALUES (27,'Compute','{\"actorRollType\":\"agi\", \"targetRollType\": \"agi\"}',7,10,0);
INSERT INTO `action_conditions` VALUES (28,'ForbidIfHasEffect','{ \"actorEffect\": \"adrenaline\", \"targetEffect\" : \"adrenaline\" }',7,6,1);
INSERT INTO `action_conditions` VALUES (29,'RequiresDistance','{\"max\":0}',8,0,1);
INSERT INTO `action_conditions` VALUES (31,'RequiresTraitValue','{ \"a\": 1 }',9,1,1);
INSERT INTO `action_conditions` VALUES (33,'RequiresTraitValue','{ \"a\": 1, \"pm\": 7 }',10,3,1);
INSERT INTO `action_conditions` VALUES (34,'ForbidIfHasEffect','{ \"actorEffect\": \"cle_de_bras\" }',10,6,1);
INSERT INTO `action_conditions` VALUES (35,'RequiresTraitValue','{ \"a\": 1 }',11,2,1);
INSERT INTO `action_conditions` VALUES (36,'RequiresDistance','{\"max\":0}',11,0,1);
INSERT INTO `action_conditions` VALUES (37,'RequiresGodAffiliation',NULL,11,1,1);
INSERT INTO `action_conditions` VALUES (38,'RequiresTraitValue','{ \"a\": 1 }',12,2,1);
INSERT INTO `action_conditions` VALUES (39,'RequiresDistance','{\"max\":0}',12,0,1);
INSERT INTO `action_conditions` VALUES (40,'RequiresResource',NULL,12,1,1);
INSERT INTO `action_conditions` VALUES (41,'RequiresTraitValue','{ \"energie\": \"both\" }',13,1,1);
INSERT INTO `action_conditions` VALUES (42,'Option','{\"option\": \"noTrain\"}',13,0,1);
INSERT INTO `action_conditions` VALUES (43,'RequiresTraitValue','{ \"a\": 1 }',13,2,1);
INSERT INTO `action_conditions` VALUES (44,'RequiresDistance','{\"max\":1}',13,0,1);
INSERT INTO `action_conditions` VALUES (45,'RequiresDistance','{\"max\":1}',14,0,1);
INSERT INTO `action_conditions` VALUES (46,'RequiresDistance','{\"max\":1}',15,0,1);
INSERT INTO `action_conditions` VALUES (47,'RequiresTraitValue','{ \"a\": 1, \"pm\": 7 }',15,3,1);
INSERT INTO `action_conditions` VALUES (48,'TechniqueCompute','{\"actorRollType\":\"cc\", \"targetRollType\": \"cc/agi\"}',15,10,0);
INSERT INTO `action_conditions` VALUES (49,'ForbidIfHasEffect','{ \"targetEffects\" : [\"corruption_du_metal\",\"corruption_du_bronze\",\"corruption_du_cuir\",\"corruption_des_plantes\"] }',16,6,1);
INSERT INTO `action_conditions` VALUES (50,'RequiresTraitValue','{ \"a\": 1, \"pm\": 7 }',16,3,1);
INSERT INTO `action_conditions` VALUES (51,'SpellCompute','{\"actorRollType\":\"fm\", \"targetRollType\": \"fm\"}',16,10,0);
/*!40000 ALTER TABLE `action_conditions` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `action_outcomes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `apply_to_self` tinyint(1) NOT NULL DEFAULT 0,
  `name` varchar(100) DEFAULT NULL,
  `on_success` tinyint(1) NOT NULL DEFAULT 1,
  `action_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_92A9B44B9D32F035` (`action_id`),
  CONSTRAINT `FK_92A9B44B9D32F035` FOREIGN KEY (`action_id`) REFERENCES `actions` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=102 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `action_outcomes` DISABLE KEYS */;
INSERT INTO `action_outcomes` VALUES (1,0,'melee_damage',1,1);
INSERT INTO `action_outcomes` VALUES (3,0,'distance_damage',1,2);
INSERT INTO `action_outcomes` VALUES (4,0,'spell_damage',1,3);
INSERT INTO `action_outcomes` VALUES (7,0,'spell_damage',1,4);
INSERT INTO `action_outcomes` VALUES (9,0,'technique_healing',1,5);
INSERT INTO `action_outcomes` VALUES (10,0,'melee_damage',1,6);
INSERT INTO `action_outcomes` VALUES (11,0,'steal_effect',1,7);
INSERT INTO `action_outcomes` VALUES (12,0,'steal_effect',0,7);
INSERT INTO `action_outcomes` VALUES (13,1,'rest_effect',1,8);
INSERT INTO `action_outcomes` VALUES (14,0,'run_effect',1,9);
INSERT INTO `action_outcomes` VALUES (15,1,'dodge_effect',1,10);
INSERT INTO `action_outcomes` VALUES (16,1,'pray_effect',1,11);
INSERT INTO `action_outcomes` VALUES (17,1,'search_effect',1,12);
INSERT INTO `action_outcomes` VALUES (18,0,'train_effect',1,13);
INSERT INTO `action_outcomes` VALUES (19,0,'tuto_attack_effect',1,14);
INSERT INTO `action_outcomes` VALUES (20,0,'technique_damage',1,15);
INSERT INTO `action_outcomes` VALUES (21,0,'spell_corrupt',1,16);
/*!40000 ALTER TABLE `action_outcomes` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `action_passives` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `traits` longtext DEFAULT NULL,
  `type` varchar(255) DEFAULT NULL,
  `carac` varchar(255) DEFAULT NULL,
  `value` decimal(3,2) DEFAULT NULL,
  `conditions` longtext DEFAULT NULL,
  `level` int(11) NOT NULL,
  `race` varchar(255) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `prerequisites` varchar(50) DEFAULT NULL,
  `display_name` varchar(255) DEFAULT NULL,
  `text` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `action_passives` DISABLE KEYS */;
/*!40000 ALTER TABLE `action_passives` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `actions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) DEFAULT NULL,
  `icon` varchar(50) NOT NULL,
  `type` varchar(255) NOT NULL,
  `display_name` varchar(255) DEFAULT NULL,
  `text` text DEFAULT NULL,
  `level` int(11) NOT NULL DEFAULT 1,
  `race` varchar(255) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `cost` varchar(255) DEFAULT NULL,
  `prerequisites` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=92 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `actions` DISABLE KEYS */;
INSERT INTO `actions` VALUES (1,'melee','ra-crossed-swords','melee','Corps à corps',NULL,1,NULL,NULL,NULL,NULL);
INSERT INTO `actions` VALUES (2,'distance','ra-arrow-cluster','distance','Tirer',NULL,1,NULL,NULL,NULL,NULL);
INSERT INTO `actions` VALUES (3,'dmg1/pic_de_pierre','ra-drill','spell','Pic de Pierre','Projette un pic de pierre sur l\'adversaire.',1,NULL,NULL,NULL,NULL);
INSERT INTO `actions` VALUES (4,'dps/poings_pierre','ra-barbed-arrow','spell','Poings de Pierre','Vos poings deviennent durs comme de la roche millénaire, que vous abattez sur vos ennemis.',1,NULL,NULL,NULL,NULL);
INSERT INTO `actions` VALUES (5,'soins/barbier','ra-cut-palm','heal','Barbier','Petites et grandes chirurgies des blessés.',1,NULL,NULL,NULL,NULL);
INSERT INTO `actions` VALUES (6,'special/attaque_sautee','ra-axe-swing','spell','Attaque Sautée','Avec une arme de mêlée, déplace immédiatement le personnage au contact de la cible et lui inflige des dégâts magiques.',1,NULL,NULL,NULL,NULL);
INSERT INTO `actions` VALUES (7,'vol_a_la_tire','ra-nuclear','steal','Vol à la tire','Dérobe des pièces d\'or !',1,NULL,NULL,NULL,NULL);
INSERT INTO `actions` VALUES (8,'repos','ra-campfire','rest','Repos',NULL,1,NULL,NULL,NULL,NULL);
INSERT INTO `actions` VALUES (9,'courir','ra-boot-stomp','run','Courir',NULL,1,NULL,NULL,NULL,NULL);
INSERT INTO `actions` VALUES (10,'esquive/cle_de_bras','ra-bear-trap','spell','Clé de bras','Pare la prochaine attaque de mêlée et immobilise l\'adversaire (uniquement à Mains nues).',1,NULL,NULL,NULL,NULL);
INSERT INTO `actions` VALUES (11,'prier','ra-crowned-heart','pray','Prier',NULL,1,NULL,NULL,NULL,NULL);
INSERT INTO `actions` VALUES (12,'fouiller','ra-clover','search','Fouiller',NULL,1,NULL,NULL,NULL,NULL);
INSERT INTO `actions` VALUES (13,'entrainement','ra-archery-target','train','Entraînement',NULL,1,NULL,NULL,NULL,NULL);
INSERT INTO `actions` VALUES (14,'tuto/attaquer','ra-crossed-swords','melee','Attaquer',NULL,1,NULL,NULL,NULL,NULL);
INSERT INTO `actions` VALUES (15,'dmg2/frappe_vicieuse','ra-diving-dagger','technique','Frappe Vicieuse','Ignore l\'armure de l\'adversaire.',1,NULL,NULL,NULL,NULL);
INSERT INTO `actions` VALUES (16,'corrupt/corruption_du_bois','ra-biohazard','spell','Corruption du Bois','Augmente la chance que l\'équipement en bois de l\'adversaire se casse.',1,NULL,NULL,NULL,NULL);
INSERT INTO `actions` VALUES (17,'epuisement','ra-crossed-axes','technique','Épuisement','Jet pur. Essoufflement(X/2) où X est la différence des jets de dé',1,NULL,'melee-curse','<span style=\"color: #8e44ad;\">1 A</span>',NULL);
INSERT INTO `actions` VALUES (18,'attaque_precise','ra-crossed-axes','technique','Attaque précise','+4 pour toucher, -3 Dmg',1,NULL,'melee-off','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">2 PM</span>',NULL);
INSERT INTO `actions` VALUES (19,'attaque_violente','ra-crossed-axes','technique','Attaque violente','-6 pour toucher, +2 Dmg',1,NULL,'melee-off','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">2 PM</span>',NULL);
INSERT INTO `actions` VALUES (20,'croc-en-jambe','ra-crossed-axes','technique','Croc-en-jambe','Ralentissement(x2D2)',2,NULL,'melee-off','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">6 PM</span>',NULL);
INSERT INTO `actions` VALUES (21,'manchette','ra-crossed-axes','technique','Manchette','Jet pur. Maladresse(X/2)  où X est la différence des jets de dé',2,NULL,'melee-curse','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">2 PM</span>',NULL);
INSERT INTO `actions` VALUES (23,'arme_infusee','ra-crossed-axes','technique','Arme infusée','+M/3 Dmg',3,NULL,'melee-off','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">8 PM</span>',NULL);
INSERT INTO `actions` VALUES (24,'tir_epuisant','ra-crossbow','technique','Tir épuisant','Jet pur. Essoufflement(X/3) où X est la différence des jets de dé',1,NULL,'distance-curse','<span style=\"color: #8e44ad;\">1 A</span>',NULL);
INSERT INTO `actions` VALUES (25,'tir_precis','ra-crossbow','technique','Tir précis','+4 pour toucher, -3 Dmg',1,NULL,'distance-off','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">2 PM</span>',NULL);
INSERT INTO `actions` VALUES (26,'tir_violent','ra-crossbow','technique','Tir violent','-6 pour toucher, +2 Dmg',1,NULL,'distance-off','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">2 PM</span>',NULL);
INSERT INTO `actions` VALUES (27,'tir_a_la_cheville','ra-crossbow','technique','Tir à la cheville','Nécessite une arme à munitions. Ralentissement(x1D2)',2,NULL,'distance-off','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">6 PM</span>',NULL);
INSERT INTO `actions` VALUES (28,'tir_handicapant','ra-crossbow','technique','Tir handicapant','Jet pur. Vulnérabilité(X/3)  où X est la différence des jets de dé',2,NULL,'distance-curse','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">4 PM</span>',NULL);
INSERT INTO `actions` VALUES (29,'jet_infuse','ra-crossbow','technique','Jet infusé','Nécessite une arme de jet. +M/3 Dmg',3,NULL,'distance-off','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">10 PM</span>',NULL);
INSERT INTO `actions` VALUES (30,'epuisement_arcanique','ra-fairy-wand','spell','Épuisement arcanique','Sort pur qui vise à épuiser l\'adversaire plutôt que le blesser.',1,NULL,'spell-curse','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">4 PM</span>',NULL);
INSERT INTO `actions` VALUES (31,'arcane_precise','ra-fairy-wand','spell','Arcane précise','+4 pour toucher',1,NULL,'spell-off','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">6 PM</span>',NULL);
INSERT INTO `actions` VALUES (32,'arcane_violente','ra-fairy-wand','spell','Arcane violente','-6 pour toucher, +5 Dmg',1,NULL,'spell-off','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">6 PM</span>',NULL);
INSERT INTO `actions` VALUES (33,'aveuglement','ra-fairy-wand','spell','Aveuglement','Aveuglement(x1)',1,NULL,'spell-curse','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">4 PM</span>',NULL);
INSERT INTO `actions` VALUES (34,'coup_precis','ra-fairy-wand','buff','Coup précis','Dextérité(x2)',1,NULL,'spell-support','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">4 PM</span>',NULL);
INSERT INTO `actions` VALUES (35,'peau_de_granit','ra-fairy-wand','buff','Peau de granit','Protection(x2)',1,NULL,'spell-support','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">4 PM</span>',NULL);
INSERT INTO `actions` VALUES (36,'maladresse','ra-fairy-wand','spell','Maladresse','Maladresse(x2)',1,NULL,'spell-curse','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">4 PM</span>',NULL);
INSERT INTO `actions` VALUES (37,'vulnerabilite','ra-fairy-wand','spell','Vulnérabilité','Vulnérabilité(x2)',1,NULL,'spell-curse','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">6 PM</span>',NULL);
INSERT INTO `actions` VALUES (38,'restauration_mineure','ra-fairy-wand','buff','Restauration mineure','Restauration(5)',1,NULL,'spell-support','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">6 PM</span>',NULL);
INSERT INTO `actions` VALUES (39,'enchevetrement','ra-fairy-wand','spell','Enchevêtrement','Ralentissement (x1D2), +1 Dmg',2,NULL,'spell-off','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">6 PM</span>',NULL);
INSERT INTO `actions` VALUES (40,'exploration','ra-aware','buff','Exploration','Acuité visuelle(X) où X est le nombre d\'A utilisées',1,NULL,'stealth-buff','<span style=\"color: #8e44ad;\">Toutes les A restantes</span>',NULL);
INSERT INTO `actions` VALUES (41,'discretion','ra-player-dodge','buff','Discrétion','Imposture(+1). Le personnage n\'apparaît plus sur la carte générale jusqu\'à son prochain tour',3,NULL,'stealth-buff','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">2x(<i class=\"ra ra-player-teleport\"></i>+1) PM</span>, <span style=\"color: #27ae60;\">1/2x(<i class=\"ra ra-player-teleport\"></i>+1) Mvt</span>',NULL);
INSERT INTO `actions` VALUES (42,'camouflage-olympien','ra-player-dodge','buff','Camouflage (Olympien)','Apparaît en Olympien sur la carte générale jusqu\'à son prochain tour',4,NULL,'stealth-buff','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">4x(<i class=\"ra ra-player-teleport\"></i>+1) PM</span>, <span style=\"color: #27ae60;\">1/2x(<i class=\"ra ra-player-teleport\"></i>+1) Mvt</span>',NULL);
INSERT INTO `actions` VALUES (43,'camouflage-nain','ra-player-dodge','buff','Camouflage (Nain)','Apparaît en Nain sur la carte générale jusqu\'à son prochain tour',4,NULL,'stealth-buff','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">4x(<i class=\"ra ra-player-teleport\"></i>+1) PM</span>, <span style=\"color: #27ae60;\">1/2x(<i class=\"ra ra-player-teleport\"></i>+1) Mvt</span>',NULL);
INSERT INTO `actions` VALUES (44,'camouflage-elfe','ra-player-dodge','buff','Camouflage (Elfe)','Apparaît en Elfe sur la carte générale jusqu\'à son prochain tour',4,NULL,'stealth-buff','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">4x(<i class=\"ra ra-player-teleport\"></i>+1) PM</span>, <span style=\"color: #27ae60;\">1/2x(<i class=\"ra ra-player-teleport\"></i>+1) Mvt</span>',NULL);
INSERT INTO `actions` VALUES (45,'camouflage-geant','ra-player-dodge','buff','Camouflage (Géant)','Apparaît en Géant sur la carte générale jusqu\'à son prochain tour',4,NULL,'stealth-buff','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">4x(<i class=\"ra ra-player-teleport\"></i>+1) PM</span>, <span style=\"color: #27ae60;\">1/2x(<i class=\"ra ra-player-teleport\"></i>+1) Mvt</span>',NULL);
INSERT INTO `actions` VALUES (46,'camouflage-hs','ra-player-dodge','buff','Camouflage (HS)','Apparaît en Homme Sauvage sur la carte générale jusqu\'à son prochain tour',4,NULL,'stealth-buff','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">4x(<i class=\"ra ra-player-teleport\"></i>+1) PM</span>, <span style=\"color: #27ae60;\">1/2x(<i class=\"ra ra-player-teleport\"></i>+1) Mvt</span>',NULL);
INSERT INTO `actions` VALUES (47,'coup_ajuste','ra-bowie-knife','technique','Coup ajusté','Avantage',1,NULL,'melee-off','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">2 PM</span>',NULL);
INSERT INTO `actions` VALUES (48,'coup_epaule','ra-shovel','technique','Coup d\'épaule','Une attaque à -4 pour toucher et -3 Dmg',1,NULL,'melee-off','<span style=\"color: #27ae60;\">5 Mvt</span>',NULL);
INSERT INTO `actions` VALUES (49,'saut_attaque','ra-overhead','technique','Saut d\'attaque','Saute sur la cible et l\'attaque au contact.',3,NULL,'todo','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">10 PM</span>,<span style=\"color: #27ae60;\">1 Mvt</span>',NULL);
INSERT INTO `actions` VALUES (50,'recuperation','ra-medical-pack','heal','Récupération','Soin(R/2)',3,NULL,'spell-support','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">4 PM</span>',NULL);
INSERT INTO `actions` VALUES (51,'recuperation_superieure','ra-medical-pack','heal','Récupération supérieure','Soin(R)',4,NULL,'spell-support','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">10 PM</span>',NULL);
INSERT INTO `actions` VALUES (52,'restauration','ra-fairy-wand','buff','Restauration','Restauration(R/2)',2,NULL,'spell-support','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">6 PM</span>',NULL);
INSERT INTO `actions` VALUES (53,'restauration_majeure','ra-fairy-wand','buff','Restauration majeure','Restauration(R)',3,NULL,'spell-support','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">12 PM</span>',NULL);
INSERT INTO `actions` VALUES (54,'regeneration','ra-medical-pack','heal','Régénération','Soin(X/2) où X est la R de la cible',2,NULL,'spell-support','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">4 PM</span>',NULL);
INSERT INTO `actions` VALUES (55,'regeneration_acceleree','ra-medical-pack','heal','Régénération accélérée','Soin(X) où X est la R de la cible',3,NULL,'spell-support','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">10 PM</span>',NULL);
INSERT INTO `actions` VALUES (56,'pas-leger','ra-shoe-prints','buff','Pas léger','Les déplacements ne laissent pas de traces de pas jusqu\'au prochain tour',1,NULL,'stealth-buff','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">2x(<i class=\"ra ra-player-teleport\"></i>+1) PM</span>, <span style=\"color: #27ae60;\">1x(<i class=\"ra ra-player-teleport\"></i>+1) Mvt</span>',NULL);
INSERT INTO `actions` VALUES (57,'puissance_nature','ra-clover','buff','Puissance de la nature','Dextérité(x2), Protection(x2)',2,NULL,'spell-support','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">8 PM</span>',NULL);
INSERT INTO `actions` VALUES (58,'aide','ra-fairy-wand','buff','Aide','Dextérité(x4)',3,NULL,'spell-support','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">8 PM</span>',NULL);
INSERT INTO `actions` VALUES (59,'reflexes_accrus','ra-fairy-wand','buff','Réflexes accrus','Protection(x4)',3,NULL,'spell-support','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">8 PM</span>',NULL);
INSERT INTO `actions` VALUES (60,'benediction','ra-fairy-wand','buff','Bénédiction','Dextérité(x4), Protection(x4)',4,NULL,'spell-support','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">15 PM</span>',NULL);
INSERT INTO `actions` VALUES (61,'sauvegarde','ra-fairy-wand','buff','Sauvegarde','Protection(x8)',5,NULL,'spell-support','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">20 PM</span>',NULL);
INSERT INTO `actions` VALUES (62,'virtuose','ra-fairy-wand','buff','Virtuose','Dextérité(x8)',5,NULL,'spell-support','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">20 PM</span>',NULL);
INSERT INTO `actions` VALUES (63,'armure','ra-vest','buff','Armure','Armure(x1)',2,NULL,'spell-support','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">8 PM</span>',NULL);
INSERT INTO `actions` VALUES (64,'agressivite','ra-dinosaur','buff','Agressivité','Agressivité(x1)',2,NULL,'spell-support','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">6 PM</span>',NULL);
INSERT INTO `actions` VALUES (65,'cuirasse','ra-vest','buff','Cuirasse','Armure(x2)',4,NULL,'spell-support','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">15 PM</span>',NULL);
INSERT INTO `actions` VALUES (66,'ferocite','ra-dinosaur','buff','Férocité','Agressivité(x2)',4,NULL,'spell-support','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">12 PM</span>',NULL);
INSERT INTO `actions` VALUES (67,'fragilite','ra-broken-bottle','spell','Fragilité','Fragilité(x1)',2,NULL,'spell-curse','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">10 PM</span>',NULL);
INSERT INTO `actions` VALUES (68,'friabilite','ra-broken-bottle','spell','Friabilité','Fragilité(x2)',4,NULL,'spell-curse','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">20 PM</span>',NULL);
INSERT INTO `actions` VALUES (69,'faiblesse','ra-player-pain','spell','Faiblesse','Faiblesse(x1)',2,NULL,'spell-curse','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">6 PM</span>',NULL);
INSERT INTO `actions` VALUES (70,'anemie','ra-player-pain','spell','Anémie','Faiblesse(x2)',4,NULL,'spell-curse','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">12 PM</span>',NULL);
INSERT INTO `actions` VALUES (71,'colere_nature','ra-player-thunder-struck','spell','Colère de la nature','Maladresse(x2), Vulnérabilité(x2)',2,NULL,'spell-curse','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">8 PM</span>',NULL);
INSERT INTO `actions` VALUES (72,'fatigue','ra-broken-shield','spell','Fatigue','Vulnérabilité(x4)',3,NULL,'spell-curse','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">12 PM</span>',NULL);
INSERT INTO `actions` VALUES (73,'malchance','ra-cut-palm','spell','Malchance','Maladresse(x4)',3,NULL,'spell-curse','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">8 PM</span>',NULL);
INSERT INTO `actions` VALUES (74,'puissance_lutin','ra-player-thunder-struck','spell','Puissance du Lutin capricieux','Maladresse(x4), Vulnérabilité(x4)',4,NULL,'spell-curse','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">20 PM</span>',NULL);
INSERT INTO `actions` VALUES (75,'extenuation','ra-broken-shield','spell','Exténuation','Vulnérabilité(x8)',5,NULL,'spell-curse','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">25 PM</span>',NULL);
INSERT INTO `actions` VALUES (76,'guigne','ra-broken-shield','spell','Guigne','Maladresse(x8)',5,NULL,'spell-curse','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">20 PM</span>',NULL);
INSERT INTO `actions` VALUES (77,'attaque_drainante','ra-knife-fork','technique','Attaque drainante','Drain',3,NULL,'melee-off','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">4 PM</span>',NULL);
INSERT INTO `actions` VALUES (78,'attaque_siphonnante','ra-knife-fork','technique','Attaque siphonnante','Siphon',3,NULL,'melee-off','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #c0392b;\">2 PV</span>',NULL);
INSERT INTO `actions` VALUES (79,'frappe_tempe','ra-decapitation','technique','Frappe à la tempe','Dommages Mentaux(6)',3,NULL,'melee-off','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">4 PM</span>',NULL);
INSERT INTO `actions` VALUES (80,'arme_impro','ra-wrench','technique','Arme improvisée','Tir sans arme à distance équipée. -4 pour toucher, -2 Dmg',1,NULL,'distance-off','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">2 PM</span>',NULL);
INSERT INTO `actions` VALUES (81,'bout_portant','ra-supersonic-arrow','technique','Bout portant','Tir avec arme de jet au contact. -8 pour toucher',1,NULL,'distance-off','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">2 PM</span>',NULL);
INSERT INTO `actions` VALUES (82,'tir_ajuste','ra-supersonic-arrow','technique','Tir ajusté','Avantage',1,NULL,'distance-off','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">2 PM</span>',NULL);
INSERT INTO `actions` VALUES (83,'jet_sable','ra-splash','technique','Jet de sable','Attaque au contact avec jet de CT sans dégâts et sans arme. Aveuglement (x2)',2,NULL,'distance-curse','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">4 PM</span>, <span style=\"color: #27ae60;\">1 Mvt</span>',NULL);
INSERT INTO `actions` VALUES (84,'arcane_ajustee','ra-fairy-wand','spell','Arcane ajustée','+3 Dmg, Avantage',1,NULL,'spell-off','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">6 PM</span>',NULL);
INSERT INTO `actions` VALUES (85,'dard','ra-fairy-wand','spell','Dard','+1 Dmg',1,NULL,'spell-off','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">3 PM</span>',NULL);
INSERT INTO `actions` VALUES (86,'drain','ra-knife-fork','spell','Drain','+1 Dmg, Drain',2,NULL,'spell-off','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">6 PM</span>',NULL);
INSERT INTO `actions` VALUES (87,'siphon','ra-knife-fork','spell','Siphon','+1 Dmg, Siphon',2,NULL,'spell-off','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #c0392b;\">5 PV</span>, <span style=\"color: #27ae60;\">2 Mvt</span>',NULL);
INSERT INTO `actions` VALUES (88,'stabilisation','ra-boot-stomp','buff','Stabilisation','Stabilité(+6)',2,NULL,'spell-support','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">2 PM</span>, <span style=\"color: #27ae60;\">1 Mvt</span>',NULL);
INSERT INTO `actions` VALUES (89,'renforcement','ra-lion','buff','Renforcement','Renforcement(x6)',2,NULL,'spell-support','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">6 PM</span>',NULL);
INSERT INTO `actions` VALUES (90,'instabilite','ra-falling','spell','Instabilité','Instabilité(x6)',2,NULL,'spell-curse','<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">6 PM</span>',NULL);
INSERT INTO `actions` VALUES (91,'bousculade','ra-falling','technique','Bousculade','Touche automatique sans dégâts. Poussée sur la case opposée.',2,NULL,'melee-curse','<span style=\"color: #8e44ad;\">1 A</span>,<span style=\"color: #27ae60;\">1 Mvt</span>',NULL);
/*!40000 ALTER TABLE `actions` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `audit_key` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `timestamp` datetime NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(255) DEFAULT NULL,
  `details` longtext DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `audit` DISABLE KEYS */;
/*!40000 ALTER TABLE `audit` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `coords` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `x` int(11) NOT NULL DEFAULT 0,
  `y` int(11) NOT NULL DEFAULT 0,
  `z` int(11) NOT NULL DEFAULT 0,
  `plan` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=51717 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `coords` DISABLE KEYS */;
INSERT INTO `coords` VALUES (1,0,0,0,'gaia');
INSERT INTO `coords` VALUES (2,0,-1,0,'gaia');
INSERT INTO `coords` VALUES (3,1,0,0,'gaia');
INSERT INTO `coords` VALUES (4,1,-1,0,'gaia');
INSERT INTO `coords` VALUES (5,2,0,0,'gaia');
INSERT INTO `coords` VALUES (6,0,-2,0,'gaia');
INSERT INTO `coords` VALUES (7,1,-2,0,'gaia');
INSERT INTO `coords` VALUES (8,2,-2,0,'gaia');
INSERT INTO `coords` VALUES (9,2,-3,0,'gaia');
INSERT INTO `coords` VALUES (10,3,-2,0,'gaia');
INSERT INTO `coords` VALUES (11,4,-2,0,'gaia');
INSERT INTO `coords` VALUES (12,2,-4,0,'gaia');
INSERT INTO `coords` VALUES (13,3,-3,0,'gaia');
INSERT INTO `coords` VALUES (14,3,-4,0,'gaia');
INSERT INTO `coords` VALUES (15,4,-4,0,'gaia');
INSERT INTO `coords` VALUES (16,4,-3,0,'gaia');
INSERT INTO `coords` VALUES (17,2,-5,0,'gaia');
INSERT INTO `coords` VALUES (18,3,-5,0,'gaia');
INSERT INTO `coords` VALUES (19,4,-5,0,'gaia');
INSERT INTO `coords` VALUES (20,5,-5,0,'gaia');
INSERT INTO `coords` VALUES (21,5,-4,0,'gaia');
INSERT INTO `coords` VALUES (22,5,-3,0,'gaia');
INSERT INTO `coords` VALUES (23,5,-2,0,'gaia');
INSERT INTO `coords` VALUES (24,0,-3,0,'gaia');
INSERT INTO `coords` VALUES (25,1,-3,0,'gaia');
INSERT INTO `coords` VALUES (26,2,-6,0,'gaia');
INSERT INTO `coords` VALUES (27,3,-6,0,'gaia');
INSERT INTO `coords` VALUES (28,4,-6,0,'gaia');
INSERT INTO `coords` VALUES (29,5,-6,0,'gaia');
INSERT INTO `coords` VALUES (30,6,-6,0,'gaia');
INSERT INTO `coords` VALUES (31,5,-7,0,'gaia');
INSERT INTO `coords` VALUES (32,6,-5,0,'gaia');
INSERT INTO `coords` VALUES (33,7,-5,0,'gaia');
INSERT INTO `coords` VALUES (34,7,-6,0,'gaia');
INSERT INTO `coords` VALUES (35,6,-7,0,'gaia');
INSERT INTO `coords` VALUES (36,8,-5,0,'gaia');
INSERT INTO `coords` VALUES (37,9,-6,0,'gaia');
INSERT INTO `coords` VALUES (38,10,-7,0,'gaia');
INSERT INTO `coords` VALUES (39,10,-8,0,'gaia');
INSERT INTO `coords` VALUES (40,9,-9,0,'gaia');
INSERT INTO `coords` VALUES (41,8,-10,0,'gaia');
INSERT INTO `coords` VALUES (42,7,-10,0,'gaia');
INSERT INTO `coords` VALUES (43,6,-9,0,'gaia');
INSERT INTO `coords` VALUES (44,5,-8,0,'gaia');
INSERT INTO `coords` VALUES (45,8,-6,0,'gaia');
INSERT INTO `coords` VALUES (46,9,-7,0,'gaia');
INSERT INTO `coords` VALUES (47,10,-9,0,'gaia');
INSERT INTO `coords` VALUES (48,9,-10,0,'gaia');
INSERT INTO `coords` VALUES (49,6,-10,0,'gaia');
INSERT INTO `coords` VALUES (50,5,-9,0,'gaia');
INSERT INTO `coords` VALUES (51,7,-11,0,'gaia');
INSERT INTO `coords` VALUES (52,8,-11,0,'gaia');
INSERT INTO `coords` VALUES (53,-2,-1,0,'gaia');
INSERT INTO `coords` VALUES (54,0,3,0,'gaia');
INSERT INTO `coords` VALUES (55,0,2,0,'gaia');
INSERT INTO `coords` VALUES (56,-2,-2,0,'gaia');
INSERT INTO `coords` VALUES (57,-4,1,0,'gaia');
INSERT INTO `coords` VALUES (58,-4,0,0,'gaia');
INSERT INTO `coords` VALUES (59,2,-1,0,'gaia');
INSERT INTO `coords` VALUES (60,-1,1,0,'gaia');
INSERT INTO `coords` VALUES (61,0,1,0,'gaia');
INSERT INTO `coords` VALUES (62,-1,0,0,'gaia');
INSERT INTO `coords` VALUES (63,-1,-1,0,'gaia');
INSERT INTO `coords` VALUES (64,-1,-2,0,'gaia');
INSERT INTO `coords` VALUES (65,-1,-3,0,'gaia');
INSERT INTO `coords` VALUES (66,1,-4,0,'gaia');
INSERT INTO `coords` VALUES (67,1,-5,0,'gaia');
INSERT INTO `coords` VALUES (68,1,-6,0,'gaia');
INSERT INTO `coords` VALUES (69,4,-7,0,'gaia');
INSERT INTO `coords` VALUES (70,4,-8,0,'gaia');
INSERT INTO `coords` VALUES (71,4,-9,0,'gaia');
INSERT INTO `coords` VALUES (72,5,-10,0,'gaia');
INSERT INTO `coords` VALUES (73,1,1,0,'gaia');
INSERT INTO `coords` VALUES (74,2,1,0,'gaia');
INSERT INTO `coords` VALUES (75,3,1,0,'gaia');
INSERT INTO `coords` VALUES (76,3,0,0,'gaia');
INSERT INTO `coords` VALUES (77,3,-1,0,'gaia');
INSERT INTO `coords` VALUES (78,4,-1,0,'gaia');
INSERT INTO `coords` VALUES (79,5,-1,0,'gaia');
INSERT INTO `coords` VALUES (80,6,-1,0,'gaia');
INSERT INTO `coords` VALUES (81,6,-2,0,'gaia');
INSERT INTO `coords` VALUES (82,6,-3,0,'gaia');
INSERT INTO `coords` VALUES (83,6,-4,0,'gaia');
INSERT INTO `coords` VALUES (84,7,-4,0,'gaia');
INSERT INTO `coords` VALUES (85,8,-4,0,'gaia');
INSERT INTO `coords` VALUES (86,9,-4,0,'gaia');
INSERT INTO `coords` VALUES (87,9,-5,0,'gaia');
INSERT INTO `coords` VALUES (88,10,-5,0,'gaia');
INSERT INTO `coords` VALUES (89,10,-6,0,'gaia');
INSERT INTO `coords` VALUES (90,6,-11,0,'gaia');
INSERT INTO `coords` VALUES (91,9,-11,0,'gaia');
INSERT INTO `coords` VALUES (92,10,-10,0,'gaia');
INSERT INTO `coords` VALUES (93,11,-6,0,'gaia');
INSERT INTO `coords` VALUES (94,11,-7,0,'gaia');
INSERT INTO `coords` VALUES (95,11,-8,0,'gaia');
INSERT INTO `coords` VALUES (96,11,-9,0,'gaia');
INSERT INTO `coords` VALUES (97,7,-7,0,'gaia');
INSERT INTO `coords` VALUES (98,8,-7,0,'gaia');
INSERT INTO `coords` VALUES (99,7,-8,0,'gaia');
INSERT INTO `coords` VALUES (100,8,-8,0,'gaia');
INSERT INTO `coords` VALUES (101,3,-3,0,'gaia2');
INSERT INTO `coords` VALUES (102,4,-4,0,'gaia2');
INSERT INTO `coords` VALUES (103,2,-2,0,'gaia2');
INSERT INTO `coords` VALUES (104,3,-2,0,'gaia2');
INSERT INTO `coords` VALUES (105,4,-2,0,'gaia2');
INSERT INTO `coords` VALUES (106,4,-3,0,'gaia2');
INSERT INTO `coords` VALUES (107,2,-3,0,'gaia2');
INSERT INTO `coords` VALUES (108,2,-4,0,'gaia2');
INSERT INTO `coords` VALUES (109,3,-4,0,'gaia2');
INSERT INTO `coords` VALUES (110,1,-2,0,'gaia2');
INSERT INTO `coords` VALUES (111,2,-1,0,'gaia2');
INSERT INTO `coords` VALUES (112,2,0,0,'gaia2');
INSERT INTO `coords` VALUES (113,1,0,0,'gaia2');
INSERT INTO `coords` VALUES (114,0,0,0,'gaia2');
INSERT INTO `coords` VALUES (115,0,-1,0,'gaia2');
INSERT INTO `coords` VALUES (116,0,-2,0,'gaia2');
INSERT INTO `coords` VALUES (117,1,-1,0,'gaia2');
INSERT INTO `coords` VALUES (118,5,-2,0,'gaia2');
INSERT INTO `coords` VALUES (119,5,-3,0,'gaia2');
INSERT INTO `coords` VALUES (120,5,-4,0,'gaia2');
INSERT INTO `coords` VALUES (121,2,-5,0,'gaia2');
INSERT INTO `coords` VALUES (122,3,-5,0,'gaia2');
INSERT INTO `coords` VALUES (123,4,-5,0,'gaia2');
INSERT INTO `coords` VALUES (124,5,-5,0,'gaia2');
INSERT INTO `coords` VALUES (125,0,-3,0,'gaia2');
INSERT INTO `coords` VALUES (126,1,-3,0,'gaia2');
INSERT INTO `coords` VALUES (127,2,-6,0,'gaia2');
INSERT INTO `coords` VALUES (128,3,-6,0,'gaia2');
INSERT INTO `coords` VALUES (129,4,-6,0,'gaia2');
INSERT INTO `coords` VALUES (130,5,-6,0,'gaia2');
INSERT INTO `coords` VALUES (131,6,-5,0,'gaia2');
INSERT INTO `coords` VALUES (132,6,-6,0,'gaia2');
INSERT INTO `coords` VALUES (133,7,-5,0,'gaia2');
INSERT INTO `coords` VALUES (134,5,-7,0,'gaia2');
INSERT INTO `coords` VALUES (135,7,-6,0,'gaia2');
INSERT INTO `coords` VALUES (136,6,-7,0,'gaia2');
INSERT INTO `coords` VALUES (137,8,-5,0,'gaia2');
INSERT INTO `coords` VALUES (138,9,-6,0,'gaia2');
INSERT INTO `coords` VALUES (139,10,-7,0,'gaia2');
INSERT INTO `coords` VALUES (140,10,-8,0,'gaia2');
INSERT INTO `coords` VALUES (141,9,-9,0,'gaia2');
INSERT INTO `coords` VALUES (142,8,-10,0,'gaia2');
INSERT INTO `coords` VALUES (143,7,-10,0,'gaia2');
INSERT INTO `coords` VALUES (144,6,-9,0,'gaia2');
INSERT INTO `coords` VALUES (145,5,-8,0,'gaia2');
INSERT INTO `coords` VALUES (146,5,-9,0,'gaia2');
INSERT INTO `coords` VALUES (147,6,-10,0,'gaia2');
INSERT INTO `coords` VALUES (148,8,-6,0,'gaia2');
INSERT INTO `coords` VALUES (149,9,-7,0,'gaia2');
INSERT INTO `coords` VALUES (150,10,-9,0,'gaia2');
INSERT INTO `coords` VALUES (151,9,-10,0,'gaia2');
INSERT INTO `coords` VALUES (152,7,-11,0,'gaia2');
INSERT INTO `coords` VALUES (153,8,-11,0,'gaia2');
INSERT INTO `coords` VALUES (154,-2,-1,0,'gaia2');
INSERT INTO `coords` VALUES (155,-4,1,0,'gaia2');
INSERT INTO `coords` VALUES (156,0,3,0,'gaia2');
INSERT INTO `coords` VALUES (157,-2,-2,0,'gaia2');
INSERT INTO `coords` VALUES (158,-4,0,0,'gaia2');
INSERT INTO `coords` VALUES (159,0,2,0,'gaia2');
INSERT INTO `coords` VALUES (160,7,-7,0,'gaia2');
INSERT INTO `coords` VALUES (161,6,-8,0,'gaia2');
INSERT INTO `coords` VALUES (162,7,-8,0,'gaia2');
INSERT INTO `coords` VALUES (163,7,-9,0,'gaia2');
INSERT INTO `coords` VALUES (164,8,-8,0,'gaia2');
INSERT INTO `coords` VALUES (165,8,-9,0,'gaia2');
INSERT INTO `coords` VALUES (166,9,-8,0,'gaia2');
INSERT INTO `coords` VALUES (167,8,-7,0,'gaia2');
INSERT INTO `coords` VALUES (168,-1,1,0,'gaia2');
INSERT INTO `coords` VALUES (169,-1,0,0,'gaia2');
INSERT INTO `coords` VALUES (170,-1,-1,0,'gaia2');
INSERT INTO `coords` VALUES (171,-1,-2,0,'gaia2');
INSERT INTO `coords` VALUES (172,-1,-3,0,'gaia2');
INSERT INTO `coords` VALUES (173,1,-4,0,'gaia2');
INSERT INTO `coords` VALUES (174,1,-5,0,'gaia2');
INSERT INTO `coords` VALUES (175,1,-6,0,'gaia2');
INSERT INTO `coords` VALUES (176,4,-7,0,'gaia2');
INSERT INTO `coords` VALUES (177,4,-8,0,'gaia2');
INSERT INTO `coords` VALUES (178,4,-9,0,'gaia2');
INSERT INTO `coords` VALUES (179,5,-10,0,'gaia2');
INSERT INTO `coords` VALUES (180,0,1,0,'gaia2');
INSERT INTO `coords` VALUES (181,1,1,0,'gaia2');
INSERT INTO `coords` VALUES (182,2,1,0,'gaia2');
INSERT INTO `coords` VALUES (183,3,1,0,'gaia2');
INSERT INTO `coords` VALUES (184,3,0,0,'gaia2');
INSERT INTO `coords` VALUES (185,3,-1,0,'gaia2');
INSERT INTO `coords` VALUES (186,4,-1,0,'gaia2');
INSERT INTO `coords` VALUES (187,5,-1,0,'gaia2');
INSERT INTO `coords` VALUES (188,6,-1,0,'gaia2');
INSERT INTO `coords` VALUES (189,6,-2,0,'gaia2');
INSERT INTO `coords` VALUES (190,6,-3,0,'gaia2');
INSERT INTO `coords` VALUES (191,6,-4,0,'gaia2');
INSERT INTO `coords` VALUES (192,7,-4,0,'gaia2');
INSERT INTO `coords` VALUES (193,8,-4,0,'gaia2');
INSERT INTO `coords` VALUES (194,9,-4,0,'gaia2');
INSERT INTO `coords` VALUES (195,9,-5,0,'gaia2');
INSERT INTO `coords` VALUES (196,10,-5,0,'gaia2');
INSERT INTO `coords` VALUES (197,10,-6,0,'gaia2');
INSERT INTO `coords` VALUES (198,6,-11,0,'gaia2');
INSERT INTO `coords` VALUES (199,9,-11,0,'gaia2');
INSERT INTO `coords` VALUES (200,10,-10,0,'gaia2');
INSERT INTO `coords` VALUES (201,11,-9,0,'gaia2');
INSERT INTO `coords` VALUES (202,11,-8,0,'gaia2');
INSERT INTO `coords` VALUES (203,11,-7,0,'gaia2');
INSERT INTO `coords` VALUES (204,11,-6,0,'gaia2');
INSERT INTO `coords` VALUES (12670,-10,-10,0,'enfers');
INSERT INTO `coords` VALUES (12671,-22,22,0,'enfers');
INSERT INTO `coords` VALUES (12672,-22,21,0,'enfers');
INSERT INTO `coords` VALUES (12673,-21,20,0,'enfers');
INSERT INTO `coords` VALUES (12674,-20,20,0,'enfers');
INSERT INTO `coords` VALUES (12675,-19,19,0,'enfers');
INSERT INTO `coords` VALUES (12676,43,19,0,'enfers');
INSERT INTO `coords` VALUES (12677,44,19,0,'enfers');
INSERT INTO `coords` VALUES (12678,43,18,0,'enfers');
INSERT INTO `coords` VALUES (12679,44,18,0,'enfers');
INSERT INTO `coords` VALUES (12680,0,0,0,'enfers');
INSERT INTO `coords` VALUES (12681,1,0,0,'enfers');
INSERT INTO `coords` VALUES (12682,-1,-1,0,'enfers');
INSERT INTO `coords` VALUES (12683,-2,-2,0,'enfers');
INSERT INTO `coords` VALUES (12684,-2,-3,0,'enfers');
INSERT INTO `coords` VALUES (12685,-3,-4,0,'enfers');
INSERT INTO `coords` VALUES (12686,2,0,0,'enfers');
INSERT INTO `coords` VALUES (12687,3,-1,0,'enfers');
INSERT INTO `coords` VALUES (12688,3,-2,0,'enfers');
INSERT INTO `coords` VALUES (12689,4,-3,0,'enfers');
INSERT INTO `coords` VALUES (12690,2,1,0,'enfers');
INSERT INTO `coords` VALUES (12691,2,2,0,'enfers');
INSERT INTO `coords` VALUES (12692,3,3,0,'enfers');
INSERT INTO `coords` VALUES (12693,-1,0,0,'enfers');
INSERT INTO `coords` VALUES (12694,-2,1,0,'enfers');
INSERT INTO `coords` VALUES (12695,-3,2,0,'enfers');
INSERT INTO `coords` VALUES (12696,-3,3,0,'enfers');
INSERT INTO `coords` VALUES (12697,0,1,0,'enfers');
INSERT INTO `coords` VALUES (12698,1,1,0,'enfers');
INSERT INTO `coords` VALUES (12699,-1,1,0,'enfers');
INSERT INTO `coords` VALUES (12700,-4,4,0,'enfers');
INSERT INTO `coords` VALUES (12701,4,4,0,'enfers');
INSERT INTO `coords` VALUES (12702,5,-4,0,'enfers');
INSERT INTO `coords` VALUES (12703,-4,-4,0,'enfers');
INSERT INTO `coords` VALUES (12704,-12,-11,0,'enfers');
INSERT INTO `coords` VALUES (12705,-12,-12,0,'enfers');
INSERT INTO `coords` VALUES (12706,-12,12,0,'enfers');
INSERT INTO `coords` VALUES (12707,-12,11,0,'enfers');
INSERT INTO `coords` VALUES (12708,12,12,0,'enfers');
INSERT INTO `coords` VALUES (12709,12,11,0,'enfers');
INSERT INTO `coords` VALUES (12710,12,-12,0,'enfers');
INSERT INTO `coords` VALUES (12711,12,-13,0,'enfers');
INSERT INTO `coords` VALUES (12712,-6,-6,0,'enfers');
INSERT INTO `coords` VALUES (12713,-7,-7,0,'enfers');
INSERT INTO `coords` VALUES (12714,-7,7,0,'enfers');
INSERT INTO `coords` VALUES (12715,-6,6,0,'enfers');
INSERT INTO `coords` VALUES (12716,7,7,0,'enfers');
INSERT INTO `coords` VALUES (12717,6,6,0,'enfers');
INSERT INTO `coords` VALUES (12718,7,-7,0,'enfers');
INSERT INTO `coords` VALUES (12719,6,-6,0,'enfers');
INSERT INTO `coords` VALUES (12720,8,-3,0,'enfers');
INSERT INTO `coords` VALUES (12721,7,-3,0,'enfers');
INSERT INTO `coords` VALUES (12722,6,-2,0,'enfers');
INSERT INTO `coords` VALUES (12723,-7,3,0,'enfers');
INSERT INTO `coords` VALUES (12724,-8,3,0,'enfers');
INSERT INTO `coords` VALUES (12725,-6,2,0,'enfers');
INSERT INTO `coords` VALUES (12726,3,8,0,'enfers');
INSERT INTO `coords` VALUES (12727,3,7,0,'enfers');
INSERT INTO `coords` VALUES (12728,2,6,0,'enfers');
INSERT INTO `coords` VALUES (12729,-3,-8,0,'enfers');
INSERT INTO `coords` VALUES (12730,-3,-7,0,'enfers');
INSERT INTO `coords` VALUES (12731,-2,-6,0,'enfers');
INSERT INTO `coords` VALUES (12732,5,2,0,'enfers');
INSERT INTO `coords` VALUES (12733,6,2,0,'enfers');
INSERT INTO `coords` VALUES (12734,7,1,0,'enfers');
INSERT INTO `coords` VALUES (12735,1,-7,0,'enfers');
INSERT INTO `coords` VALUES (12736,2,-6,0,'enfers');
INSERT INTO `coords` VALUES (12737,2,-5,0,'enfers');
INSERT INTO `coords` VALUES (12738,-7,-1,0,'enfers');
INSERT INTO `coords` VALUES (12739,-6,-2,0,'enfers');
INSERT INTO `coords` VALUES (12740,-5,-2,0,'enfers');
INSERT INTO `coords` VALUES (12741,-1,7,0,'enfers');
INSERT INTO `coords` VALUES (12742,-2,6,0,'enfers');
INSERT INTO `coords` VALUES (12743,-2,5,0,'enfers');
INSERT INTO `coords` VALUES (12744,-13,-11,0,'enfers');
INSERT INTO `coords` VALUES (12745,-11,-11,0,'enfers');
INSERT INTO `coords` VALUES (12746,-11,-12,0,'enfers');
INSERT INTO `coords` VALUES (12747,-11,-13,0,'enfers');
INSERT INTO `coords` VALUES (12748,-10,10,0,'enfers');
INSERT INTO `coords` VALUES (12749,-11,11,0,'enfers');
INSERT INTO `coords` VALUES (12750,-11,12,0,'enfers');
INSERT INTO `coords` VALUES (12751,-13,12,0,'enfers');
INSERT INTO `coords` VALUES (12752,-13,10,0,'enfers');
INSERT INTO `coords` VALUES (12753,10,10,0,'enfers');
INSERT INTO `coords` VALUES (12754,11,11,0,'enfers');
INSERT INTO `coords` VALUES (12755,13,11,0,'enfers');
INSERT INTO `coords` VALUES (12756,11,12,0,'enfers');
INSERT INTO `coords` VALUES (12757,10,-10,0,'enfers');
INSERT INTO `coords` VALUES (12758,11,-11,0,'enfers');
INSERT INTO `coords` VALUES (12759,11,-12,0,'enfers');
INSERT INTO `coords` VALUES (12760,13,-13,0,'enfers');
INSERT INTO `coords` VALUES (12761,14,-14,0,'enfers');
INSERT INTO `coords` VALUES (12762,-22,23,0,'enfers');
INSERT INTO `coords` VALUES (12763,-21,22,0,'enfers');
INSERT INTO `coords` VALUES (12764,-23,21,0,'enfers');
INSERT INTO `coords` VALUES (12765,-23,-22,0,'enfers');
INSERT INTO `coords` VALUES (12766,-24,-23,0,'enfers');
INSERT INTO `coords` VALUES (12767,-25,-24,0,'enfers');
INSERT INTO `coords` VALUES (12768,-26,-24,0,'enfers');
INSERT INTO `coords` VALUES (12769,-27,-25,0,'enfers');
INSERT INTO `coords` VALUES (12770,-28,-26,0,'enfers');
INSERT INTO `coords` VALUES (12771,-22,-22,0,'enfers');
INSERT INTO `coords` VALUES (12772,-29,-26,0,'enfers');
INSERT INTO `coords` VALUES (12773,-30,-27,0,'enfers');
INSERT INTO `coords` VALUES (12774,-21,-22,0,'enfers');
INSERT INTO `coords` VALUES (12775,-20,-23,0,'enfers');
INSERT INTO `coords` VALUES (12776,-19,-23,0,'enfers');
INSERT INTO `coords` VALUES (12777,-18,-22,0,'enfers');
INSERT INTO `coords` VALUES (12778,-17,-22,0,'enfers');
INSERT INTO `coords` VALUES (12779,-16,-21,0,'enfers');
INSERT INTO `coords` VALUES (12780,-15,-21,0,'enfers');
INSERT INTO `coords` VALUES (12781,-14,-21,0,'enfers');
INSERT INTO `coords` VALUES (12782,-13,-22,0,'enfers');
INSERT INTO `coords` VALUES (12783,-12,-22,0,'enfers');
INSERT INTO `coords` VALUES (12784,-31,-28,0,'enfers');
INSERT INTO `coords` VALUES (12785,-32,-28,0,'enfers');
INSERT INTO `coords` VALUES (12786,-33,-29,0,'enfers');
INSERT INTO `coords` VALUES (12787,-34,-30,0,'enfers');
INSERT INTO `coords` VALUES (12788,-35,-30,0,'enfers');
INSERT INTO `coords` VALUES (12789,-36,-31,0,'enfers');
INSERT INTO `coords` VALUES (12790,-37,-32,0,'enfers');
INSERT INTO `coords` VALUES (12791,-38,-32,0,'enfers');
INSERT INTO `coords` VALUES (12792,-39,-33,0,'enfers');
INSERT INTO `coords` VALUES (12793,-40,-34,0,'enfers');
INSERT INTO `coords` VALUES (12794,-41,-34,0,'enfers');
INSERT INTO `coords` VALUES (12795,-42,-35,0,'enfers');
INSERT INTO `coords` VALUES (12796,-43,-36,0,'enfers');
INSERT INTO `coords` VALUES (12797,-44,-36,0,'enfers');
INSERT INTO `coords` VALUES (12798,-45,-37,0,'enfers');
INSERT INTO `coords` VALUES (12799,-44,-37,0,'enfers');
INSERT INTO `coords` VALUES (12800,-43,-37,0,'enfers');
INSERT INTO `coords` VALUES (12801,-42,-37,0,'enfers');
INSERT INTO `coords` VALUES (12802,-46,-38,0,'enfers');
INSERT INTO `coords` VALUES (12803,-47,-38,0,'enfers');
INSERT INTO `coords` VALUES (12804,-45,-38,0,'enfers');
INSERT INTO `coords` VALUES (12805,-44,-38,0,'enfers');
INSERT INTO `coords` VALUES (12806,-43,-38,0,'enfers');
INSERT INTO `coords` VALUES (12807,-48,-39,0,'enfers');
INSERT INTO `coords` VALUES (12808,-47,-39,0,'enfers');
INSERT INTO `coords` VALUES (12809,-46,-39,0,'enfers');
INSERT INTO `coords` VALUES (12810,-45,-39,0,'enfers');
INSERT INTO `coords` VALUES (12811,-44,-39,0,'enfers');
INSERT INTO `coords` VALUES (12812,-43,-39,0,'enfers');
INSERT INTO `coords` VALUES (12813,-50,-40,0,'enfers');
INSERT INTO `coords` VALUES (12814,-49,-40,0,'enfers');
INSERT INTO `coords` VALUES (12815,-48,-40,0,'enfers');
INSERT INTO `coords` VALUES (12816,-47,-40,0,'enfers');
INSERT INTO `coords` VALUES (12817,-46,-40,0,'enfers');
INSERT INTO `coords` VALUES (12818,-45,-40,0,'enfers');
INSERT INTO `coords` VALUES (12819,-44,-40,0,'enfers');
INSERT INTO `coords` VALUES (12820,-43,-40,0,'enfers');
INSERT INTO `coords` VALUES (12821,-42,-40,0,'enfers');
INSERT INTO `coords` VALUES (12822,-51,-41,0,'enfers');
INSERT INTO `coords` VALUES (12823,-50,-41,0,'enfers');
INSERT INTO `coords` VALUES (12824,-49,-41,0,'enfers');
INSERT INTO `coords` VALUES (12825,-48,-41,0,'enfers');
INSERT INTO `coords` VALUES (12826,-47,-41,0,'enfers');
INSERT INTO `coords` VALUES (12827,-46,-41,0,'enfers');
INSERT INTO `coords` VALUES (12828,-45,-41,0,'enfers');
INSERT INTO `coords` VALUES (12829,-44,-41,0,'enfers');
INSERT INTO `coords` VALUES (12830,-43,-41,0,'enfers');
INSERT INTO `coords` VALUES (12831,-42,-41,0,'enfers');
INSERT INTO `coords` VALUES (12832,-41,-41,0,'enfers');
INSERT INTO `coords` VALUES (12833,-50,-42,0,'enfers');
INSERT INTO `coords` VALUES (12834,-49,-42,0,'enfers');
INSERT INTO `coords` VALUES (12835,-48,-42,0,'enfers');
INSERT INTO `coords` VALUES (12836,-47,-42,0,'enfers');
INSERT INTO `coords` VALUES (12837,-46,-42,0,'enfers');
INSERT INTO `coords` VALUES (12838,-45,-42,0,'enfers');
INSERT INTO `coords` VALUES (12839,-44,-42,0,'enfers');
INSERT INTO `coords` VALUES (12840,-43,-42,0,'enfers');
INSERT INTO `coords` VALUES (12841,-48,-43,0,'enfers');
INSERT INTO `coords` VALUES (12842,-47,-43,0,'enfers');
INSERT INTO `coords` VALUES (12843,-46,-43,0,'enfers');
INSERT INTO `coords` VALUES (12844,-45,-43,0,'enfers');
INSERT INTO `coords` VALUES (12845,-44,-43,0,'enfers');
INSERT INTO `coords` VALUES (12846,-47,-44,0,'enfers');
INSERT INTO `coords` VALUES (12847,-46,-44,0,'enfers');
INSERT INTO `coords` VALUES (12848,-45,-44,0,'enfers');
INSERT INTO `coords` VALUES (12849,-49,-43,0,'enfers');
INSERT INTO `coords` VALUES (12850,-11,-21,0,'enfers');
INSERT INTO `coords` VALUES (12851,-10,-21,0,'enfers');
INSERT INTO `coords` VALUES (12852,-9,-21,0,'enfers');
INSERT INTO `coords` VALUES (12853,-8,-20,0,'enfers');
INSERT INTO `coords` VALUES (12854,-7,-20,0,'enfers');
INSERT INTO `coords` VALUES (12855,-6,-21,0,'enfers');
INSERT INTO `coords` VALUES (12856,-5,-21,0,'enfers');
INSERT INTO `coords` VALUES (12857,-4,-21,0,'enfers');
INSERT INTO `coords` VALUES (12858,-3,-22,0,'enfers');
INSERT INTO `coords` VALUES (12859,-2,-22,0,'enfers');
INSERT INTO `coords` VALUES (12860,-1,-22,0,'enfers');
INSERT INTO `coords` VALUES (12861,0,-21,0,'enfers');
INSERT INTO `coords` VALUES (12862,1,-21,0,'enfers');
INSERT INTO `coords` VALUES (12863,2,-22,0,'enfers');
INSERT INTO `coords` VALUES (12864,3,-22,0,'enfers');
INSERT INTO `coords` VALUES (12865,4,-22,0,'enfers');
INSERT INTO `coords` VALUES (12866,5,-21,0,'enfers');
INSERT INTO `coords` VALUES (12867,6,-21,0,'enfers');
INSERT INTO `coords` VALUES (12868,7,-22,0,'enfers');
INSERT INTO `coords` VALUES (12869,8,-22,0,'enfers');
INSERT INTO `coords` VALUES (12870,9,-22,0,'enfers');
INSERT INTO `coords` VALUES (12871,10,-21,0,'enfers');
INSERT INTO `coords` VALUES (12872,11,-21,0,'enfers');
INSERT INTO `coords` VALUES (12873,12,-22,0,'enfers');
INSERT INTO `coords` VALUES (12874,13,-22,0,'enfers');
INSERT INTO `coords` VALUES (12875,14,-22,0,'enfers');
INSERT INTO `coords` VALUES (12876,15,-21,0,'enfers');
INSERT INTO `coords` VALUES (12877,16,-21,0,'enfers');
INSERT INTO `coords` VALUES (12878,17,-20,0,'enfers');
INSERT INTO `coords` VALUES (12879,18,-20,0,'enfers');
INSERT INTO `coords` VALUES (12880,19,-20,0,'enfers');
INSERT INTO `coords` VALUES (12881,20,-19,0,'enfers');
INSERT INTO `coords` VALUES (12882,21,-19,0,'enfers');
INSERT INTO `coords` VALUES (12883,22,-18,0,'enfers');
INSERT INTO `coords` VALUES (12884,23,-18,0,'enfers');
INSERT INTO `coords` VALUES (12885,24,-18,0,'enfers');
INSERT INTO `coords` VALUES (12886,25,-17,0,'enfers');
INSERT INTO `coords` VALUES (12887,26,-17,0,'enfers');
INSERT INTO `coords` VALUES (12888,27,-16,0,'enfers');
INSERT INTO `coords` VALUES (12889,28,-15,0,'enfers');
INSERT INTO `coords` VALUES (12890,29,-14,0,'enfers');
INSERT INTO `coords` VALUES (12891,29,-13,0,'enfers');
INSERT INTO `coords` VALUES (12892,30,-12,0,'enfers');
INSERT INTO `coords` VALUES (12893,30,-11,0,'enfers');
INSERT INTO `coords` VALUES (12894,30,-10,0,'enfers');
INSERT INTO `coords` VALUES (12895,31,-9,0,'enfers');
INSERT INTO `coords` VALUES (12896,31,-8,0,'enfers');
INSERT INTO `coords` VALUES (12897,32,-7,0,'enfers');
INSERT INTO `coords` VALUES (12898,32,-6,0,'enfers');
INSERT INTO `coords` VALUES (12899,32,-5,0,'enfers');
INSERT INTO `coords` VALUES (12900,33,-4,0,'enfers');
INSERT INTO `coords` VALUES (12901,33,-3,0,'enfers');
INSERT INTO `coords` VALUES (12902,34,-2,0,'enfers');
INSERT INTO `coords` VALUES (12903,34,-1,0,'enfers');
INSERT INTO `coords` VALUES (12904,34,0,0,'enfers');
INSERT INTO `coords` VALUES (12905,35,1,0,'enfers');
INSERT INTO `coords` VALUES (12906,35,2,0,'enfers');
INSERT INTO `coords` VALUES (12907,36,3,0,'enfers');
INSERT INTO `coords` VALUES (12908,36,4,0,'enfers');
INSERT INTO `coords` VALUES (12909,36,5,0,'enfers');
INSERT INTO `coords` VALUES (12910,37,6,0,'enfers');
INSERT INTO `coords` VALUES (12911,37,7,0,'enfers');
INSERT INTO `coords` VALUES (12912,38,8,0,'enfers');
INSERT INTO `coords` VALUES (12913,38,9,0,'enfers');
INSERT INTO `coords` VALUES (12914,38,10,0,'enfers');
INSERT INTO `coords` VALUES (12915,39,11,0,'enfers');
INSERT INTO `coords` VALUES (12916,39,12,0,'enfers');
INSERT INTO `coords` VALUES (12917,40,13,0,'enfers');
INSERT INTO `coords` VALUES (12918,40,14,0,'enfers');
INSERT INTO `coords` VALUES (12919,40,15,0,'enfers');
INSERT INTO `coords` VALUES (12920,41,16,0,'enfers');
INSERT INTO `coords` VALUES (12921,41,17,0,'enfers');
INSERT INTO `coords` VALUES (12922,42,18,0,'enfers');
INSERT INTO `coords` VALUES (12923,42,19,0,'enfers');
INSERT INTO `coords` VALUES (12924,43,20,0,'enfers');
INSERT INTO `coords` VALUES (12925,44,20,0,'enfers');
INSERT INTO `coords` VALUES (12926,43,17,0,'enfers');
INSERT INTO `coords` VALUES (12927,44,17,0,'enfers');
INSERT INTO `coords` VALUES (12928,45,19,0,'enfers');
INSERT INTO `coords` VALUES (12929,45,18,0,'enfers');
INSERT INTO `coords` VALUES (12930,42,17,0,'enfers');
INSERT INTO `coords` VALUES (15318,0,0,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (15457,0,0,0,'arcadia');
INSERT INTO `coords` VALUES (15458,-1,2,0,'arcadia');
INSERT INTO `coords` VALUES (15459,0,2,0,'arcadia');
INSERT INTO `coords` VALUES (15460,1,2,0,'arcadia');
INSERT INTO `coords` VALUES (15461,-2,1,0,'arcadia');
INSERT INTO `coords` VALUES (15462,-1,1,0,'arcadia');
INSERT INTO `coords` VALUES (15463,0,1,0,'arcadia');
INSERT INTO `coords` VALUES (15464,1,1,0,'arcadia');
INSERT INTO `coords` VALUES (15465,2,1,0,'arcadia');
INSERT INTO `coords` VALUES (15466,2,0,0,'arcadia');
INSERT INTO `coords` VALUES (15467,2,-1,0,'arcadia');
INSERT INTO `coords` VALUES (15468,1,-1,0,'arcadia');
INSERT INTO `coords` VALUES (15469,1,0,0,'arcadia');
INSERT INTO `coords` VALUES (15470,0,-1,0,'arcadia');
INSERT INTO `coords` VALUES (15471,-1,-1,0,'arcadia');
INSERT INTO `coords` VALUES (15472,-1,0,0,'arcadia');
INSERT INTO `coords` VALUES (15473,-2,0,0,'arcadia');
INSERT INTO `coords` VALUES (15474,-2,-1,0,'arcadia');
INSERT INTO `coords` VALUES (15475,-1,-2,0,'arcadia');
INSERT INTO `coords` VALUES (15476,0,-2,0,'arcadia');
INSERT INTO `coords` VALUES (15477,1,-2,0,'arcadia');
INSERT INTO `coords` VALUES (15503,-2,2,0,'arcadia');
INSERT INTO `coords` VALUES (15504,-1,3,0,'arcadia');
INSERT INTO `coords` VALUES (15505,0,3,0,'arcadia');
INSERT INTO `coords` VALUES (15506,1,3,0,'arcadia');
INSERT INTO `coords` VALUES (15507,2,2,0,'arcadia');
INSERT INTO `coords` VALUES (15508,3,1,0,'arcadia');
INSERT INTO `coords` VALUES (15509,3,0,0,'arcadia');
INSERT INTO `coords` VALUES (15510,3,-1,0,'arcadia');
INSERT INTO `coords` VALUES (15511,2,-2,0,'arcadia');
INSERT INTO `coords` VALUES (15512,1,-3,0,'arcadia');
INSERT INTO `coords` VALUES (15513,0,-3,0,'arcadia');
INSERT INTO `coords` VALUES (15514,-1,-3,0,'arcadia');
INSERT INTO `coords` VALUES (15515,-2,-2,0,'arcadia');
INSERT INTO `coords` VALUES (15516,-3,-1,0,'arcadia');
INSERT INTO `coords` VALUES (15517,-3,0,0,'arcadia');
INSERT INTO `coords` VALUES (15518,-3,1,0,'arcadia');
INSERT INTO `coords` VALUES (15519,-3,2,0,'arcadia');
INSERT INTO `coords` VALUES (15520,-2,3,0,'arcadia');
INSERT INTO `coords` VALUES (15521,-1,4,0,'arcadia');
INSERT INTO `coords` VALUES (15522,0,4,0,'arcadia');
INSERT INTO `coords` VALUES (15523,1,4,0,'arcadia');
INSERT INTO `coords` VALUES (15524,2,3,0,'arcadia');
INSERT INTO `coords` VALUES (15525,3,2,0,'arcadia');
INSERT INTO `coords` VALUES (15526,4,1,0,'arcadia');
INSERT INTO `coords` VALUES (15527,4,0,0,'arcadia');
INSERT INTO `coords` VALUES (15528,4,-1,0,'arcadia');
INSERT INTO `coords` VALUES (15529,3,-2,0,'arcadia');
INSERT INTO `coords` VALUES (15530,2,-3,0,'arcadia');
INSERT INTO `coords` VALUES (15531,1,-4,0,'arcadia');
INSERT INTO `coords` VALUES (15532,0,-4,0,'arcadia');
INSERT INTO `coords` VALUES (15533,-1,-4,0,'arcadia');
INSERT INTO `coords` VALUES (15534,-2,-3,0,'arcadia');
INSERT INTO `coords` VALUES (15535,-3,-2,0,'arcadia');
INSERT INTO `coords` VALUES (15536,-4,-1,0,'arcadia');
INSERT INTO `coords` VALUES (15537,-4,0,0,'arcadia');
INSERT INTO `coords` VALUES (15538,-4,1,0,'arcadia');
INSERT INTO `coords` VALUES (15539,-4,2,0,'arcadia');
INSERT INTO `coords` VALUES (15540,-3,3,0,'arcadia');
INSERT INTO `coords` VALUES (15541,-2,4,0,'arcadia');
INSERT INTO `coords` VALUES (15542,-1,5,0,'arcadia');
INSERT INTO `coords` VALUES (15543,0,5,0,'arcadia');
INSERT INTO `coords` VALUES (15544,1,5,0,'arcadia');
INSERT INTO `coords` VALUES (15545,2,4,0,'arcadia');
INSERT INTO `coords` VALUES (15546,3,3,0,'arcadia');
INSERT INTO `coords` VALUES (15547,4,2,0,'arcadia');
INSERT INTO `coords` VALUES (15548,5,1,0,'arcadia');
INSERT INTO `coords` VALUES (15549,5,0,0,'arcadia');
INSERT INTO `coords` VALUES (15550,5,-1,0,'arcadia');
INSERT INTO `coords` VALUES (15551,4,-2,0,'arcadia');
INSERT INTO `coords` VALUES (15552,3,-3,0,'arcadia');
INSERT INTO `coords` VALUES (15553,2,-4,0,'arcadia');
INSERT INTO `coords` VALUES (15554,1,-5,0,'arcadia');
INSERT INTO `coords` VALUES (15555,0,-5,0,'arcadia');
INSERT INTO `coords` VALUES (15556,-1,-5,0,'arcadia');
INSERT INTO `coords` VALUES (15557,-2,-4,0,'arcadia');
INSERT INTO `coords` VALUES (15558,-3,-3,0,'arcadia');
INSERT INTO `coords` VALUES (15559,-4,-2,0,'arcadia');
INSERT INTO `coords` VALUES (15560,-5,-1,0,'arcadia');
INSERT INTO `coords` VALUES (15561,-5,0,0,'arcadia');
INSERT INTO `coords` VALUES (15562,-5,1,0,'arcadia');
INSERT INTO `coords` VALUES (15564,8,4,0,'arcadia');
INSERT INTO `coords` VALUES (15565,7,4,0,'arcadia');
INSERT INTO `coords` VALUES (15566,6,4,0,'arcadia');
INSERT INTO `coords` VALUES (15567,7,3,0,'arcadia');
INSERT INTO `coords` VALUES (15568,8,3,0,'arcadia');
INSERT INTO `coords` VALUES (15573,6,3,0,'arcadia');
INSERT INTO `coords` VALUES (15576,5,4,0,'arcadia');
INSERT INTO `coords` VALUES (16595,4,9,0,'arcadia');
INSERT INTO `coords` VALUES (16596,9,-1,0,'arcadia');
INSERT INTO `coords` VALUES (16597,1,-10,0,'arcadia');
INSERT INTO `coords` VALUES (16598,-9,-6,0,'arcadia');
INSERT INTO `coords` VALUES (16599,-10,5,0,'arcadia');
INSERT INTO `coords` VALUES (16600,-7,9,0,'arcadia');
INSERT INTO `coords` VALUES (16601,9,-7,0,'arcadia');
INSERT INTO `coords` VALUES (16602,-6,-9,0,'arcadia');
INSERT INTO `coords` VALUES (16603,8,6,0,'arcadia');
INSERT INTO `coords` VALUES (16604,-2,9,0,'arcadia');
INSERT INTO `coords` VALUES (16605,-10,0,0,'arcadia');
INSERT INTO `coords` VALUES (16606,-6,-8,0,'arcadia');
INSERT INTO `coords` VALUES (16607,-5,-7,0,'arcadia');
INSERT INTO `coords` VALUES (16608,-5,-8,0,'arcadia');
INSERT INTO `coords` VALUES (16609,-3,-4,0,'arcadia');
INSERT INTO `coords` VALUES (16610,6,-9,0,'arcadia');
INSERT INTO `coords` VALUES (16611,7,-5,0,'arcadia');
INSERT INTO `coords` VALUES (16612,7,2,0,'arcadia');
INSERT INTO `coords` VALUES (16613,2,8,0,'arcadia');
INSERT INTO `coords` VALUES (16614,-3,7,0,'arcadia');
INSERT INTO `coords` VALUES (16615,-5,4,0,'arcadia');
INSERT INTO `coords` VALUES (16616,-8,2,0,'arcadia');
INSERT INTO `coords` VALUES (16617,-7,2,0,'arcadia');
INSERT INTO `coords` VALUES (16618,-6,3,0,'arcadia');
INSERT INTO `coords` VALUES (16619,-6,2,0,'arcadia');
INSERT INTO `coords` VALUES (16620,-5,2,0,'arcadia');
INSERT INTO `coords` VALUES (16621,-5,3,0,'arcadia');
INSERT INTO `coords` VALUES (16622,-4,3,0,'arcadia');
INSERT INTO `coords` VALUES (16623,-4,4,0,'arcadia');
INSERT INTO `coords` VALUES (16624,-3,4,0,'arcadia');
INSERT INTO `coords` VALUES (16625,-3,5,0,'arcadia');
INSERT INTO `coords` VALUES (16626,-2,5,0,'arcadia');
INSERT INTO `coords` VALUES (16627,-1,6,0,'arcadia');
INSERT INTO `coords` VALUES (16628,0,6,0,'arcadia');
INSERT INTO `coords` VALUES (16629,1,6,0,'arcadia');
INSERT INTO `coords` VALUES (16630,2,5,0,'arcadia');
INSERT INTO `coords` VALUES (16631,3,6,0,'arcadia');
INSERT INTO `coords` VALUES (16632,3,5,0,'arcadia');
INSERT INTO `coords` VALUES (16633,3,4,0,'arcadia');
INSERT INTO `coords` VALUES (16634,4,4,0,'arcadia');
INSERT INTO `coords` VALUES (16635,4,3,0,'arcadia');
INSERT INTO `coords` VALUES (16636,5,2,0,'arcadia');
INSERT INTO `coords` VALUES (16637,5,3,0,'arcadia');
INSERT INTO `coords` VALUES (16638,9,3,0,'arcadia');
INSERT INTO `coords` VALUES (16639,8,5,0,'arcadia');
INSERT INTO `coords` VALUES (16640,7,5,0,'arcadia');
INSERT INTO `coords` VALUES (16641,6,5,0,'arcadia');
INSERT INTO `coords` VALUES (16642,5,5,0,'arcadia');
INSERT INTO `coords` VALUES (16643,4,5,0,'arcadia');
INSERT INTO `coords` VALUES (16644,6,2,0,'arcadia');
INSERT INTO `coords` VALUES (16645,6,1,0,'arcadia');
INSERT INTO `coords` VALUES (16646,6,0,0,'arcadia');
INSERT INTO `coords` VALUES (16647,6,-1,0,'arcadia');
INSERT INTO `coords` VALUES (16648,5,-2,0,'arcadia');
INSERT INTO `coords` VALUES (16649,6,-2,0,'arcadia');
INSERT INTO `coords` VALUES (16650,6,-3,0,'arcadia');
INSERT INTO `coords` VALUES (16651,5,-3,0,'arcadia');
INSERT INTO `coords` VALUES (16652,4,-3,0,'arcadia');
INSERT INTO `coords` VALUES (16653,7,-4,0,'arcadia');
INSERT INTO `coords` VALUES (16654,7,-3,0,'arcadia');
INSERT INTO `coords` VALUES (16655,7,-2,0,'arcadia');
INSERT INTO `coords` VALUES (16656,7,-1,0,'arcadia');
INSERT INTO `coords` VALUES (16657,10,-1,0,'arcadia');
INSERT INTO `coords` VALUES (16658,10,0,0,'arcadia');
INSERT INTO `coords` VALUES (16659,10,1,0,'arcadia');
INSERT INTO `coords` VALUES (16660,9,2,0,'arcadia');
INSERT INTO `coords` VALUES (16661,7,-6,0,'arcadia');
INSERT INTO `coords` VALUES (16662,8,-7,0,'arcadia');
INSERT INTO `coords` VALUES (16663,8,-8,0,'arcadia');
INSERT INTO `coords` VALUES (16664,7,-9,0,'arcadia');
INSERT INTO `coords` VALUES (16665,7,-8,0,'arcadia');
INSERT INTO `coords` VALUES (16666,7,-7,0,'arcadia');
INSERT INTO `coords` VALUES (16667,1,-6,0,'arcadia');
INSERT INTO `coords` VALUES (16668,1,-7,0,'arcadia');
INSERT INTO `coords` VALUES (16669,1,-8,0,'arcadia');
INSERT INTO `coords` VALUES (16670,1,-9,0,'arcadia');
INSERT INTO `coords` VALUES (16671,1,7,0,'arcadia');
INSERT INTO `coords` VALUES (16672,1,8,0,'arcadia');
INSERT INTO `coords` VALUES (16673,1,9,0,'arcadia');
INSERT INTO `coords` VALUES (16674,2,7,0,'arcadia');
INSERT INTO `coords` VALUES (16675,2,6,0,'arcadia');
INSERT INTO `coords` VALUES (16676,2,9,0,'arcadia');
INSERT INTO `coords` VALUES (16677,3,9,0,'arcadia');
INSERT INTO `coords` VALUES (16678,3,8,0,'arcadia');
INSERT INTO `coords` VALUES (16679,3,7,0,'arcadia');
INSERT INTO `coords` VALUES (16680,4,8,0,'arcadia');
INSERT INTO `coords` VALUES (16681,4,7,0,'arcadia');
INSERT INTO `coords` VALUES (16682,4,6,0,'arcadia');
INSERT INTO `coords` VALUES (16683,5,6,0,'arcadia');
INSERT INTO `coords` VALUES (16684,6,6,0,'arcadia');
INSERT INTO `coords` VALUES (16685,7,6,0,'arcadia');
INSERT INTO `coords` VALUES (16686,7,7,0,'arcadia');
INSERT INTO `coords` VALUES (16687,6,7,0,'arcadia');
INSERT INTO `coords` VALUES (16688,5,7,0,'arcadia');
INSERT INTO `coords` VALUES (16689,5,8,0,'arcadia');
INSERT INTO `coords` VALUES (16690,6,8,0,'arcadia');
INSERT INTO `coords` VALUES (16692,8,7,0,'arcadia');
INSERT INTO `coords` VALUES (16704,10,5,0,'arcadia');
INSERT INTO `coords` VALUES (16705,10,4,0,'arcadia');
INSERT INTO `coords` VALUES (16706,10,3,0,'arcadia');
INSERT INTO `coords` VALUES (16707,10,2,0,'arcadia');
INSERT INTO `coords` VALUES (16708,9,6,0,'arcadia');
INSERT INTO `coords` VALUES (16709,9,5,0,'arcadia');
INSERT INTO `coords` VALUES (16710,9,4,0,'arcadia');
INSERT INTO `coords` VALUES (16711,10,-2,0,'arcadia');
INSERT INTO `coords` VALUES (16712,10,-3,0,'arcadia');
INSERT INTO `coords` VALUES (16713,10,-4,0,'arcadia');
INSERT INTO `coords` VALUES (16714,10,-5,0,'arcadia');
INSERT INTO `coords` VALUES (16715,9,-6,0,'arcadia');
INSERT INTO `coords` VALUES (16716,5,-9,0,'arcadia');
INSERT INTO `coords` VALUES (16717,4,-9,0,'arcadia');
INSERT INTO `coords` VALUES (16718,6,-10,0,'arcadia');
INSERT INTO `coords` VALUES (16719,5,-10,0,'arcadia');
INSERT INTO `coords` VALUES (16720,4,-10,0,'arcadia');
INSERT INTO `coords` VALUES (16721,2,-10,0,'arcadia');
INSERT INTO `coords` VALUES (16722,3,-10,0,'arcadia');
INSERT INTO `coords` VALUES (16723,3,-4,0,'arcadia');
INSERT INTO `coords` VALUES (16724,4,-4,0,'arcadia');
INSERT INTO `coords` VALUES (16725,5,-4,0,'arcadia');
INSERT INTO `coords` VALUES (16726,6,-4,0,'arcadia');
INSERT INTO `coords` VALUES (16727,6,-5,0,'arcadia');
INSERT INTO `coords` VALUES (16728,6,-6,0,'arcadia');
INSERT INTO `coords` VALUES (16729,2,-5,0,'arcadia');
INSERT INTO `coords` VALUES (16730,3,-5,0,'arcadia');
INSERT INTO `coords` VALUES (16731,4,-5,0,'arcadia');
INSERT INTO `coords` VALUES (16732,5,-5,0,'arcadia');
INSERT INTO `coords` VALUES (16733,4,-6,0,'arcadia');
INSERT INTO `coords` VALUES (16734,5,-6,0,'arcadia');
INSERT INTO `coords` VALUES (16735,6,-7,0,'arcadia');
INSERT INTO `coords` VALUES (16736,6,-8,0,'arcadia');
INSERT INTO `coords` VALUES (16737,5,-8,0,'arcadia');
INSERT INTO `coords` VALUES (16738,5,-7,0,'arcadia');
INSERT INTO `coords` VALUES (16739,3,-6,0,'arcadia');
INSERT INTO `coords` VALUES (16740,2,-6,0,'arcadia');
INSERT INTO `coords` VALUES (16741,2,-7,0,'arcadia');
INSERT INTO `coords` VALUES (16742,3,-7,0,'arcadia');
INSERT INTO `coords` VALUES (16743,4,-7,0,'arcadia');
INSERT INTO `coords` VALUES (16744,4,-8,0,'arcadia');
INSERT INTO `coords` VALUES (16745,3,-8,0,'arcadia');
INSERT INTO `coords` VALUES (16746,2,-9,0,'arcadia');
INSERT INTO `coords` VALUES (16747,3,-9,0,'arcadia');
INSERT INTO `coords` VALUES (16748,2,-8,0,'arcadia');
INSERT INTO `coords` VALUES (16749,8,-6,0,'arcadia');
INSERT INTO `coords` VALUES (16750,8,-5,0,'arcadia');
INSERT INTO `coords` VALUES (16751,9,-5,0,'arcadia');
INSERT INTO `coords` VALUES (16752,9,-4,0,'arcadia');
INSERT INTO `coords` VALUES (16753,8,-4,0,'arcadia');
INSERT INTO `coords` VALUES (16754,8,-3,0,'arcadia');
INSERT INTO `coords` VALUES (16755,9,-3,0,'arcadia');
INSERT INTO `coords` VALUES (16756,9,-2,0,'arcadia');
INSERT INTO `coords` VALUES (16757,8,-2,0,'arcadia');
INSERT INTO `coords` VALUES (16758,8,-1,0,'arcadia');
INSERT INTO `coords` VALUES (16759,8,0,0,'arcadia');
INSERT INTO `coords` VALUES (16760,8,1,0,'arcadia');
INSERT INTO `coords` VALUES (16761,8,2,0,'arcadia');
INSERT INTO `coords` VALUES (16762,7,1,0,'arcadia');
INSERT INTO `coords` VALUES (16763,7,0,0,'arcadia');
INSERT INTO `coords` VALUES (16764,9,0,0,'arcadia');
INSERT INTO `coords` VALUES (16765,9,1,0,'arcadia');
INSERT INTO `coords` VALUES (16767,-7,-7,0,'arcadia');
INSERT INTO `coords` VALUES (16768,-6,-3,0,'arcadia');
INSERT INTO `coords` VALUES (16769,-4,-9,0,'arcadia');
INSERT INTO `coords` VALUES (16770,-5,-9,0,'arcadia');
INSERT INTO `coords` VALUES (16771,0,8,0,'arcadia');
INSERT INTO `coords` VALUES (16772,-1,8,0,'arcadia');
INSERT INTO `coords` VALUES (16777,-2,7,0,'arcadia');
INSERT INTO `coords` VALUES (16779,0,7,0,'arcadia');
INSERT INTO `coords` VALUES (16780,-1,7,0,'arcadia');
INSERT INTO `coords` VALUES (16781,-2,6,0,'arcadia');
INSERT INTO `coords` VALUES (16782,-3,6,0,'arcadia');
INSERT INTO `coords` VALUES (16783,-4,6,0,'arcadia');
INSERT INTO `coords` VALUES (16784,-5,6,0,'arcadia');
INSERT INTO `coords` VALUES (16785,-4,5,0,'arcadia');
INSERT INTO `coords` VALUES (16786,-5,5,0,'arcadia');
INSERT INTO `coords` VALUES (16787,-6,5,0,'arcadia');
INSERT INTO `coords` VALUES (16788,-7,5,0,'arcadia');
INSERT INTO `coords` VALUES (16789,-2,-8,0,'arcadia');
INSERT INTO `coords` VALUES (16790,-3,-9,0,'arcadia');
INSERT INTO `coords` VALUES (16791,-4,-10,0,'arcadia');
INSERT INTO `coords` VALUES (16792,-1,-7,0,'arcadia');
INSERT INTO `coords` VALUES (16793,-1,-6,0,'arcadia');
INSERT INTO `coords` VALUES (16794,-10,-5,0,'arcadia');
INSERT INTO `coords` VALUES (16795,-8,-7,0,'arcadia');
INSERT INTO `coords` VALUES (16796,-7,-8,0,'arcadia');
INSERT INTO `coords` VALUES (16797,-5,-10,0,'arcadia');
INSERT INTO `coords` VALUES (16798,0,-6,0,'arcadia');
INSERT INTO `coords` VALUES (16799,0,-7,0,'arcadia');
INSERT INTO `coords` VALUES (16800,0,-8,0,'arcadia');
INSERT INTO `coords` VALUES (16801,-1,-8,0,'arcadia');
INSERT INTO `coords` VALUES (16802,-3,-10,0,'arcadia');
INSERT INTO `coords` VALUES (16803,-2,-10,0,'arcadia');
INSERT INTO `coords` VALUES (16804,-1,-9,0,'arcadia');
INSERT INTO `coords` VALUES (16805,-2,-9,0,'arcadia');
INSERT INTO `coords` VALUES (16806,-1,-10,0,'arcadia');
INSERT INTO `coords` VALUES (16807,0,-9,0,'arcadia');
INSERT INTO `coords` VALUES (16808,0,-10,0,'arcadia');
INSERT INTO `coords` VALUES (16809,-10,-1,0,'arcadia');
INSERT INTO `coords` VALUES (16810,-6,1,0,'arcadia');
INSERT INTO `coords` VALUES (16811,-10,-2,0,'arcadia');
INSERT INTO `coords` VALUES (16812,-10,-3,0,'arcadia');
INSERT INTO `coords` VALUES (16813,-10,-4,0,'arcadia');
INSERT INTO `coords` VALUES (16814,-10,1,0,'arcadia');
INSERT INTO `coords` VALUES (16815,-10,2,0,'arcadia');
INSERT INTO `coords` VALUES (16816,-10,4,0,'arcadia');
INSERT INTO `coords` VALUES (16817,-10,3,0,'arcadia');
INSERT INTO `coords` VALUES (16818,-6,-2,0,'arcadia');
INSERT INTO `coords` VALUES (16819,-5,-2,0,'arcadia');
INSERT INTO `coords` VALUES (16820,-4,-3,0,'arcadia');
INSERT INTO `coords` VALUES (16821,-5,-3,0,'arcadia');
INSERT INTO `coords` VALUES (16822,-4,-4,0,'arcadia');
INSERT INTO `coords` VALUES (16823,-5,-4,0,'arcadia');
INSERT INTO `coords` VALUES (16824,-6,-4,0,'arcadia');
INSERT INTO `coords` VALUES (16825,-6,-1,0,'arcadia');
INSERT INTO `coords` VALUES (16826,-6,0,0,'arcadia');
INSERT INTO `coords` VALUES (16827,-7,1,0,'arcadia');
INSERT INTO `coords` VALUES (16828,-7,0,0,'arcadia');
INSERT INTO `coords` VALUES (16829,-7,-1,0,'arcadia');
INSERT INTO `coords` VALUES (16830,-7,-2,0,'arcadia');
INSERT INTO `coords` VALUES (16831,-7,-5,0,'arcadia');
INSERT INTO `coords` VALUES (16832,-7,-4,0,'arcadia');
INSERT INTO `coords` VALUES (16833,-7,-3,0,'arcadia');
INSERT INTO `coords` VALUES (16834,-3,-7,0,'arcadia');
INSERT INTO `coords` VALUES (16835,-5,-6,0,'arcadia');
INSERT INTO `coords` VALUES (16836,-3,-6,0,'arcadia');
INSERT INTO `coords` VALUES (16837,-4,-5,0,'arcadia');
INSERT INTO `coords` VALUES (16838,-4,-6,0,'arcadia');
INSERT INTO `coords` VALUES (16839,-4,-7,0,'arcadia');
INSERT INTO `coords` VALUES (16840,-4,-8,0,'arcadia');
INSERT INTO `coords` VALUES (16841,-3,-8,0,'arcadia');
INSERT INTO `coords` VALUES (16842,-2,-7,0,'arcadia');
INSERT INTO `coords` VALUES (16843,-2,-6,0,'arcadia');
INSERT INTO `coords` VALUES (16844,-2,-5,0,'arcadia');
INSERT INTO `coords` VALUES (16845,-3,-5,0,'arcadia');
INSERT INTO `coords` VALUES (16846,-6,-7,0,'arcadia');
INSERT INTO `coords` VALUES (16847,-6,-5,0,'arcadia');
INSERT INTO `coords` VALUES (16848,-5,-5,0,'arcadia');
INSERT INTO `coords` VALUES (16849,-6,-6,0,'arcadia');
INSERT INTO `coords` VALUES (16850,-8,-6,0,'arcadia');
INSERT INTO `coords` VALUES (16851,-7,-6,0,'arcadia');
INSERT INTO `coords` VALUES (16852,-8,-5,0,'arcadia');
INSERT INTO `coords` VALUES (16853,-9,-5,0,'arcadia');
INSERT INTO `coords` VALUES (16854,-8,-4,0,'arcadia');
INSERT INTO `coords` VALUES (16855,-9,-4,0,'arcadia');
INSERT INTO `coords` VALUES (16856,-9,-3,0,'arcadia');
INSERT INTO `coords` VALUES (16857,-8,-3,0,'arcadia');
INSERT INTO `coords` VALUES (16858,-8,-2,0,'arcadia');
INSERT INTO `coords` VALUES (16859,-9,-2,0,'arcadia');
INSERT INTO `coords` VALUES (16860,-8,-1,0,'arcadia');
INSERT INTO `coords` VALUES (16861,-9,-1,0,'arcadia');
INSERT INTO `coords` VALUES (16862,-9,0,0,'arcadia');
INSERT INTO `coords` VALUES (16863,-8,0,0,'arcadia');
INSERT INTO `coords` VALUES (16864,-9,1,0,'arcadia');
INSERT INTO `coords` VALUES (16865,-8,1,0,'arcadia');
INSERT INTO `coords` VALUES (16866,-9,2,0,'arcadia');
INSERT INTO `coords` VALUES (16867,-9,3,0,'arcadia');
INSERT INTO `coords` VALUES (16868,-9,4,0,'arcadia');
INSERT INTO `coords` VALUES (16869,-7,4,0,'arcadia');
INSERT INTO `coords` VALUES (16870,-6,4,0,'arcadia');
INSERT INTO `coords` VALUES (16871,-7,3,0,'arcadia');
INSERT INTO `coords` VALUES (16872,-8,3,0,'arcadia');
INSERT INTO `coords` VALUES (16873,-8,4,0,'arcadia');
INSERT INTO `coords` VALUES (16874,-6,6,0,'arcadia');
INSERT INTO `coords` VALUES (16875,-4,7,0,'arcadia');
INSERT INTO `coords` VALUES (16876,0,9,0,'arcadia');
INSERT INTO `coords` VALUES (16877,-2,8,0,'arcadia');
INSERT INTO `coords` VALUES (16878,-4,8,0,'arcadia');
INSERT INTO `coords` VALUES (16879,-3,8,0,'arcadia');
INSERT INTO `coords` VALUES (16880,-5,7,0,'arcadia');
INSERT INTO `coords` VALUES (16881,-6,7,0,'arcadia');
INSERT INTO `coords` VALUES (16882,-7,6,0,'arcadia');
INSERT INTO `coords` VALUES (16883,-8,5,0,'arcadia');
INSERT INTO `coords` VALUES (16884,-9,5,0,'arcadia');
INSERT INTO `coords` VALUES (16885,-1,9,0,'arcadia');
INSERT INTO `coords` VALUES (16998,-2,2,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (16999,-1,2,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17000,0,2,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17001,1,2,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17002,2,2,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17003,2,1,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17004,1,1,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17005,0,1,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17006,-1,1,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17007,-2,1,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17008,-2,0,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17009,-1,0,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17010,1,0,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17011,2,0,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17012,2,-1,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17013,1,-1,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17014,0,-1,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17015,-1,-1,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17016,-2,-1,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17017,-1,-2,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17018,0,-2,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17019,1,-2,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17020,2,-2,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17021,-2,-2,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17022,-3,3,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17023,-2,3,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17024,-1,3,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17025,0,3,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17026,1,3,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17027,2,3,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17028,3,3,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17029,3,2,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17030,3,1,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17031,3,0,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17032,3,-1,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17033,3,-2,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17034,3,-3,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17035,2,-3,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17036,1,-3,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17037,0,-3,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17038,-1,-3,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17039,-2,-3,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17040,-3,-3,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17041,-3,-2,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17042,-3,-1,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17043,-3,0,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17044,-3,1,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17045,-3,2,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17046,-1,-4,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17047,1,-4,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17048,4,3,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17049,5,3,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17050,6,3,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17051,7,3,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17052,8,3,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17053,9,3,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17054,9,2,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17055,9,1,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17056,9,0,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17057,9,-1,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17058,9,-2,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17059,9,-3,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17060,9,-4,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17061,9,-5,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17062,9,-6,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17063,8,-6,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17064,7,-6,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17065,6,-6,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17066,5,-6,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17067,-4,3,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17068,-5,3,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17069,-6,3,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17070,-7,3,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17071,-8,3,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17072,-9,3,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17073,-9,2,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17074,-9,1,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17075,-9,0,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17076,-9,-1,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17077,-9,-2,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17078,-9,-3,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17079,-9,-4,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17080,-9,-5,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17081,-9,-6,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17082,-8,-6,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17083,-7,-6,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17084,-6,-6,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17085,-5,-6,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17086,-5,-7,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17087,-5,-8,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17088,5,-7,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17089,5,-8,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17090,4,-8,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17091,-4,-8,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17092,-3,-8,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17093,-2,-8,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17094,3,-8,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17095,2,-8,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17096,2,-9,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17097,-2,-9,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17098,0,-4,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17099,0,-5,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17100,1,-5,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17101,2,-5,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17102,2,-4,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17103,3,-4,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17104,3,-5,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17105,3,-6,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17106,3,-7,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17107,2,-7,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17108,2,-6,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17109,1,-6,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17110,0,-6,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17111,-1,-6,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17112,-1,-5,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17113,-2,-5,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17114,-2,-4,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17115,-3,-4,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17116,-3,-5,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17117,-3,-6,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17118,-3,-7,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17119,-2,-7,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17120,-2,-6,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17121,-1,-7,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17122,0,-7,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17123,1,-7,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17124,1,-8,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17125,0,-8,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17126,-1,-8,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17127,-1,-9,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17128,0,-9,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17129,1,-9,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17130,1,-10,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17131,0,-10,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17132,-1,-10,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17133,4,-7,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17134,4,-6,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17135,4,-5,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17136,4,-4,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17137,4,-3,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17138,5,-3,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17139,5,-4,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17140,-4,-3,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17141,-5,-3,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17142,-5,-4,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17143,5,-5,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17144,-4,-7,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17145,-4,-6,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17146,-4,-5,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17147,-4,-4,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17148,-5,-5,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17149,-8,-5,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17150,-7,-5,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17151,-6,-5,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17152,-6,-4,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17153,-7,-4,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17154,-8,-4,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17155,-8,-3,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17156,-7,-3,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17157,-6,-3,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17158,-4,-2,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17159,-5,-2,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17160,-6,-2,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17161,-7,-2,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17162,-8,-2,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17163,-8,-1,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17164,-7,-1,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17165,-6,-1,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17166,-5,-1,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17167,-4,-1,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17168,-4,0,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17169,-5,0,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17170,-6,0,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17171,-7,0,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17172,-8,0,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17173,-8,1,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17174,-8,2,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17175,-7,2,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17176,-7,1,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17177,-6,1,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17178,-6,2,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17179,-5,2,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17180,-5,1,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17181,-4,1,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17182,-4,2,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17183,6,-5,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17184,6,-4,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17185,6,-3,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17186,6,-2,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17187,5,-2,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17188,4,-2,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17189,4,-1,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17190,5,-1,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17191,6,-1,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17192,6,0,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17193,5,0,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17194,4,0,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17195,4,1,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17196,5,1,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17197,6,1,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17198,6,2,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17199,5,2,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17200,4,2,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17201,7,2,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17202,8,2,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17203,8,1,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17204,7,1,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17205,7,0,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17206,8,0,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17207,8,-1,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17208,7,-1,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17209,7,-2,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17210,8,-2,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17211,8,-3,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17212,7,-3,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17213,7,-4,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17214,8,-4,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17215,8,-5,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17216,7,-5,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17217,-2,-10,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17218,2,-10,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17219,-5,-9,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17220,5,-9,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17221,-9,-7,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17222,-8,-7,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17223,-7,-7,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17224,-6,-7,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17225,-6,-8,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17226,-7,-8,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17227,-8,-8,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17228,-9,-8,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17229,-6,-9,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17230,-4,-9,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17231,-3,-9,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17232,3,-9,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17233,4,-9,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17234,6,-9,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17235,6,-8,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17236,6,-7,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17237,7,-7,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17238,8,-7,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17239,9,-7,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17240,9,-8,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17241,8,-8,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17242,7,-8,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17243,-1,-11,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17244,0,-11,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17245,1,-11,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17246,2,-11,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17247,3,-10,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17248,3,-11,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17249,4,-10,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17250,5,-10,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17251,6,-10,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17252,7,-10,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17253,7,-9,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17254,8,-9,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17255,-2,-11,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17256,-3,-10,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17257,-4,-10,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17258,-5,-10,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17259,-6,-10,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17260,-7,-10,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17261,-7,-9,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17262,-8,-9,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17263,-9,-9,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17264,-9,-10,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17265,-8,-10,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17266,-9,-11,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17267,-8,-11,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17268,-7,-11,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17269,-6,-11,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17270,-5,-11,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17271,-4,-11,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17272,-3,-11,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17273,4,-11,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17274,5,-11,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17275,6,-11,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17276,7,-11,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17277,8,-11,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17278,8,-10,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17279,9,-10,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17280,9,-9,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17281,9,-11,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17282,-2,-12,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17283,2,-12,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17284,3,-12,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17285,4,-12,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17286,5,-12,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17287,6,-12,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17288,7,-12,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17289,9,-12,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17290,8,-12,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17291,-3,-12,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17292,-4,-12,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17293,-5,-12,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17294,-6,-12,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17295,-7,-12,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17296,-8,-12,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17297,-9,-12,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17298,-1,-12,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17299,0,-12,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17300,1,-12,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17301,1,-13,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17302,0,-13,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17303,-1,-13,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17304,-1,-14,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17305,0,-14,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17306,1,-14,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17307,1,-15,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17308,0,-15,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17309,-1,-15,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17310,-1,-16,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17311,0,-16,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17312,1,-16,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17313,7,-13,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17314,7,-14,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17315,6,-14,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17316,5,-14,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17317,-7,-13,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17318,-7,-14,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17319,-6,-14,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17320,-5,-14,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17321,-4,-14,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17322,-3,-14,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17323,-2,-14,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17324,2,-14,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17325,3,-14,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17326,4,-14,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17327,2,-13,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17328,3,-13,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17329,4,-13,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17330,5,-13,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17331,6,-13,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17332,8,-13,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17333,9,-13,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17334,9,-14,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17335,8,-14,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17336,9,-15,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17337,9,-16,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17338,8,-16,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17339,8,-15,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17340,7,-15,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17341,6,-15,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17342,5,-15,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17343,4,-15,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17344,3,-15,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17345,2,-15,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17346,2,-16,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17347,3,-16,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17348,4,-16,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17349,5,-16,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17350,6,-16,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17351,7,-16,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17352,-2,-16,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17353,-2,-15,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17354,-3,-15,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17355,-3,-16,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17356,-4,-16,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17357,-4,-15,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17358,-5,-15,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17359,-5,-16,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17360,-6,-16,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17361,-6,-15,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17362,-7,-15,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17363,-7,-16,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17364,-8,-16,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17365,-8,-15,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17366,-8,-14,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17367,-8,-13,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17368,-9,-13,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17369,-9,-14,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17370,-9,-15,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17371,-9,-16,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17372,-6,-13,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17373,-5,-13,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17374,-4,-13,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17375,-3,-13,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17376,-2,-13,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17377,10,-6,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17378,10,-5,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17379,10,-4,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17380,10,-3,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17381,10,-2,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17382,10,-1,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17383,10,0,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17384,10,1,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17385,10,2,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17386,10,3,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17387,10,4,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17388,9,4,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17389,8,4,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17390,7,4,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17391,6,4,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17392,5,4,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17393,4,4,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17394,3,4,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17395,2,4,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17396,1,4,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17397,0,4,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17398,-1,4,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17399,-2,4,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17400,-3,4,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17401,-4,4,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17402,-5,4,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17403,-6,4,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17404,-7,4,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17405,-8,4,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17406,-9,4,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17407,-10,4,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17408,-10,3,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17409,-10,2,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17410,-10,1,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17411,-10,0,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17412,-10,-1,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17413,-10,-2,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17414,-10,-3,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17415,-10,-4,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17416,-10,-5,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17417,-10,-6,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17418,10,-7,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17419,10,-8,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17420,10,-9,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17421,10,-10,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17422,10,-11,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17423,10,-12,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17424,10,-13,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17425,10,-14,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17426,10,-15,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17427,10,-16,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17428,-10,-16,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17429,-10,-15,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17430,-10,-14,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17431,-10,-13,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17432,-10,-12,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17433,-10,-11,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17434,-10,-10,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17435,-10,-9,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17436,-10,-8,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (17437,-10,-7,0,'banque_des_lutins');
INSERT INTO `coords` VALUES (19770,-10,10,0,'arcadia');
INSERT INTO `coords` VALUES (19771,-10,9,0,'arcadia');
INSERT INTO `coords` VALUES (19772,-10,8,0,'arcadia');
INSERT INTO `coords` VALUES (19773,-10,7,0,'arcadia');
INSERT INTO `coords` VALUES (19774,-10,6,0,'arcadia');
INSERT INTO `coords` VALUES (19775,-10,-6,0,'arcadia');
INSERT INTO `coords` VALUES (19777,-10,-7,0,'arcadia');
INSERT INTO `coords` VALUES (19778,-10,-8,0,'arcadia');
INSERT INTO `coords` VALUES (19780,-10,-9,0,'arcadia');
INSERT INTO `coords` VALUES (19781,-10,-10,0,'arcadia');
INSERT INTO `coords` VALUES (19782,-9,-10,0,'arcadia');
INSERT INTO `coords` VALUES (19783,-9,-9,0,'arcadia');
INSERT INTO `coords` VALUES (19784,-9,-8,0,'arcadia');
INSERT INTO `coords` VALUES (19785,-9,-7,0,'arcadia');
INSERT INTO `coords` VALUES (19788,-8,-8,0,'arcadia');
INSERT INTO `coords` VALUES (19789,-8,-9,0,'arcadia');
INSERT INTO `coords` VALUES (19790,-8,-10,0,'arcadia');
INSERT INTO `coords` VALUES (19791,-7,-10,0,'arcadia');
INSERT INTO `coords` VALUES (19792,-6,-10,0,'arcadia');
INSERT INTO `coords` VALUES (19793,-7,-9,0,'arcadia');
INSERT INTO `coords` VALUES (19798,7,-10,0,'arcadia');
INSERT INTO `coords` VALUES (19799,8,-9,0,'arcadia');
INSERT INTO `coords` VALUES (19800,8,-10,0,'arcadia');
INSERT INTO `coords` VALUES (19801,9,-10,0,'arcadia');
INSERT INTO `coords` VALUES (19802,9,-9,0,'arcadia');
INSERT INTO `coords` VALUES (19803,9,-8,0,'arcadia');
INSERT INTO `coords` VALUES (19804,10,-6,0,'arcadia');
INSERT INTO `coords` VALUES (19805,10,-7,0,'arcadia');
INSERT INTO `coords` VALUES (19806,10,-8,0,'arcadia');
INSERT INTO `coords` VALUES (19807,10,-9,0,'arcadia');
INSERT INTO `coords` VALUES (19808,10,-10,0,'arcadia');
INSERT INTO `coords` VALUES (19809,10,6,0,'arcadia');
INSERT INTO `coords` VALUES (19810,10,7,0,'arcadia');
INSERT INTO `coords` VALUES (19811,9,7,0,'arcadia');
INSERT INTO `coords` VALUES (19812,10,8,0,'arcadia');
INSERT INTO `coords` VALUES (19813,9,8,0,'arcadia');
INSERT INTO `coords` VALUES (19814,8,8,0,'arcadia');
INSERT INTO `coords` VALUES (19815,7,8,0,'arcadia');
INSERT INTO `coords` VALUES (19816,5,9,0,'arcadia');
INSERT INTO `coords` VALUES (19817,6,9,0,'arcadia');
INSERT INTO `coords` VALUES (19818,7,9,0,'arcadia');
INSERT INTO `coords` VALUES (19819,8,9,0,'arcadia');
INSERT INTO `coords` VALUES (19820,9,9,0,'arcadia');
INSERT INTO `coords` VALUES (19821,10,9,0,'arcadia');
INSERT INTO `coords` VALUES (19822,10,10,0,'arcadia');
INSERT INTO `coords` VALUES (19823,9,10,0,'arcadia');
INSERT INTO `coords` VALUES (19824,8,10,0,'arcadia');
INSERT INTO `coords` VALUES (19825,7,10,0,'arcadia');
INSERT INTO `coords` VALUES (19826,6,10,0,'arcadia');
INSERT INTO `coords` VALUES (19827,5,10,0,'arcadia');
INSERT INTO `coords` VALUES (19828,4,10,0,'arcadia');
INSERT INTO `coords` VALUES (19829,3,10,0,'arcadia');
INSERT INTO `coords` VALUES (19830,2,10,0,'arcadia');
INSERT INTO `coords` VALUES (19831,1,10,0,'arcadia');
INSERT INTO `coords` VALUES (19832,0,10,0,'arcadia');
INSERT INTO `coords` VALUES (19833,-1,10,0,'arcadia');
INSERT INTO `coords` VALUES (19834,-2,10,0,'arcadia');
INSERT INTO `coords` VALUES (19835,-3,9,0,'arcadia');
INSERT INTO `coords` VALUES (19836,-4,9,0,'arcadia');
INSERT INTO `coords` VALUES (19837,-5,8,0,'arcadia');
INSERT INTO `coords` VALUES (19838,-6,8,0,'arcadia');
INSERT INTO `coords` VALUES (19839,-7,7,0,'arcadia');
INSERT INTO `coords` VALUES (19841,-8,6,0,'arcadia');
INSERT INTO `coords` VALUES (19842,-9,6,0,'arcadia');
INSERT INTO `coords` VALUES (19843,-9,7,0,'arcadia');
INSERT INTO `coords` VALUES (19844,-8,7,0,'arcadia');
INSERT INTO `coords` VALUES (19845,-9,8,0,'arcadia');
INSERT INTO `coords` VALUES (19846,-8,8,0,'arcadia');
INSERT INTO `coords` VALUES (19847,-7,8,0,'arcadia');
INSERT INTO `coords` VALUES (19848,-8,9,0,'arcadia');
INSERT INTO `coords` VALUES (19849,-9,10,0,'arcadia');
INSERT INTO `coords` VALUES (19850,-9,9,0,'arcadia');
INSERT INTO `coords` VALUES (19851,-8,10,0,'arcadia');
INSERT INTO `coords` VALUES (19852,-7,10,0,'arcadia');
INSERT INTO `coords` VALUES (19853,-6,9,0,'arcadia');
INSERT INTO `coords` VALUES (19854,-6,10,0,'arcadia');
INSERT INTO `coords` VALUES (19855,-5,9,0,'arcadia');
INSERT INTO `coords` VALUES (19856,-5,10,0,'arcadia');
INSERT INTO `coords` VALUES (19857,-4,10,0,'arcadia');
INSERT INTO `coords` VALUES (19858,-3,10,0,'arcadia');
INSERT INTO `coords` VALUES (25894,0,-4,0,'enfers');
INSERT INTO `coords` VALUES (27884,-42,-43,0,'enfers');
INSERT INTO `coords` VALUES (27885,-40,-41,0,'enfers');
INSERT INTO `coords` VALUES (27886,-39,-41,0,'enfers');
INSERT INTO `coords` VALUES (27887,-40,-42,0,'enfers');
INSERT INTO `coords` VALUES (27888,-40,-43,0,'enfers');
INSERT INTO `coords` VALUES (27889,-39,-43,0,'enfers');
INSERT INTO `coords` VALUES (34737,3,-21,-2,'gaia2');
INSERT INTO `coords` VALUES (35004,-5,0,0,'enfers');
INSERT INTO `coords` VALUES (35010,0,0,-1,'arcadia');
INSERT INTO `coords` VALUES (35011,0,1,-1,'arcadia');
INSERT INTO `coords` VALUES (35012,-1,0,-1,'arcadia');
INSERT INTO `coords` VALUES (35013,0,-1,-1,'arcadia');
INSERT INTO `coords` VALUES (35014,1,0,-1,'arcadia');
INSERT INTO `coords` VALUES (35015,1,1,-1,'arcadia');
INSERT INTO `coords` VALUES (35016,0,2,-1,'arcadia');
INSERT INTO `coords` VALUES (35017,-1,1,-1,'arcadia');
INSERT INTO `coords` VALUES (35018,-2,0,-1,'arcadia');
INSERT INTO `coords` VALUES (35019,-1,-1,-1,'arcadia');
INSERT INTO `coords` VALUES (35020,0,-2,-1,'arcadia');
INSERT INTO `coords` VALUES (35021,1,-1,-1,'arcadia');
INSERT INTO `coords` VALUES (35022,2,0,-1,'arcadia');
INSERT INTO `coords` VALUES (35023,2,1,-1,'arcadia');
INSERT INTO `coords` VALUES (35024,1,2,-1,'arcadia');
INSERT INTO `coords` VALUES (35025,0,3,-1,'arcadia');
INSERT INTO `coords` VALUES (35026,-1,2,-1,'arcadia');
INSERT INTO `coords` VALUES (35027,-2,1,-1,'arcadia');
INSERT INTO `coords` VALUES (35028,-3,0,-1,'arcadia');
INSERT INTO `coords` VALUES (35029,-2,-1,-1,'arcadia');
INSERT INTO `coords` VALUES (35030,-1,-2,-1,'arcadia');
INSERT INTO `coords` VALUES (35031,0,-3,-1,'arcadia');
INSERT INTO `coords` VALUES (35032,1,-2,-1,'arcadia');
INSERT INTO `coords` VALUES (35033,2,-1,-1,'arcadia');
INSERT INTO `coords` VALUES (35034,3,0,-1,'arcadia');
INSERT INTO `coords` VALUES (35035,3,1,-1,'arcadia');
INSERT INTO `coords` VALUES (35036,2,2,-1,'arcadia');
INSERT INTO `coords` VALUES (35037,1,3,-1,'arcadia');
INSERT INTO `coords` VALUES (35038,0,4,-1,'arcadia');
INSERT INTO `coords` VALUES (35039,-1,3,-1,'arcadia');
INSERT INTO `coords` VALUES (35040,-2,2,-1,'arcadia');
INSERT INTO `coords` VALUES (35041,-3,1,-1,'arcadia');
INSERT INTO `coords` VALUES (35042,-4,0,-1,'arcadia');
INSERT INTO `coords` VALUES (35043,-3,-1,-1,'arcadia');
INSERT INTO `coords` VALUES (35044,-2,-2,-1,'arcadia');
INSERT INTO `coords` VALUES (35045,-1,-3,-1,'arcadia');
INSERT INTO `coords` VALUES (35046,0,-4,-1,'arcadia');
INSERT INTO `coords` VALUES (35047,1,-3,-1,'arcadia');
INSERT INTO `coords` VALUES (35048,2,-2,-1,'arcadia');
INSERT INTO `coords` VALUES (35049,3,-1,-1,'arcadia');
INSERT INTO `coords` VALUES (35050,4,0,-1,'arcadia');
INSERT INTO `coords` VALUES (35051,4,1,-1,'arcadia');
INSERT INTO `coords` VALUES (35052,3,2,-1,'arcadia');
INSERT INTO `coords` VALUES (35053,2,3,-1,'arcadia');
INSERT INTO `coords` VALUES (35054,1,4,-1,'arcadia');
INSERT INTO `coords` VALUES (35055,0,5,-1,'arcadia');
INSERT INTO `coords` VALUES (35056,-1,4,-1,'arcadia');
INSERT INTO `coords` VALUES (35057,-2,3,-1,'arcadia');
INSERT INTO `coords` VALUES (35058,-3,2,-1,'arcadia');
INSERT INTO `coords` VALUES (35059,-4,1,-1,'arcadia');
INSERT INTO `coords` VALUES (35060,-5,0,-1,'arcadia');
INSERT INTO `coords` VALUES (35061,-4,-1,-1,'arcadia');
INSERT INTO `coords` VALUES (35062,-3,-2,-1,'arcadia');
INSERT INTO `coords` VALUES (35063,-2,-3,-1,'arcadia');
INSERT INTO `coords` VALUES (35064,-1,-4,-1,'arcadia');
INSERT INTO `coords` VALUES (35065,0,-5,-1,'arcadia');
INSERT INTO `coords` VALUES (35066,1,-4,-1,'arcadia');
INSERT INTO `coords` VALUES (35067,2,-3,-1,'arcadia');
INSERT INTO `coords` VALUES (35068,3,-2,-1,'arcadia');
INSERT INTO `coords` VALUES (35069,4,-1,-1,'arcadia');
INSERT INTO `coords` VALUES (35070,5,0,-1,'arcadia');
INSERT INTO `coords` VALUES (35071,5,1,-1,'arcadia');
INSERT INTO `coords` VALUES (35072,4,2,-1,'arcadia');
INSERT INTO `coords` VALUES (35073,3,3,-1,'arcadia');
INSERT INTO `coords` VALUES (35074,2,4,-1,'arcadia');
INSERT INTO `coords` VALUES (35075,1,5,-1,'arcadia');
INSERT INTO `coords` VALUES (35076,0,6,-1,'arcadia');
INSERT INTO `coords` VALUES (35077,-1,5,-1,'arcadia');
INSERT INTO `coords` VALUES (35078,-2,4,-1,'arcadia');
INSERT INTO `coords` VALUES (35079,-3,3,-1,'arcadia');
INSERT INTO `coords` VALUES (35080,-4,2,-1,'arcadia');
INSERT INTO `coords` VALUES (35081,-5,1,-1,'arcadia');
INSERT INTO `coords` VALUES (35082,-6,0,-1,'arcadia');
INSERT INTO `coords` VALUES (35083,-5,-1,-1,'arcadia');
INSERT INTO `coords` VALUES (35084,-4,-2,-1,'arcadia');
INSERT INTO `coords` VALUES (35085,-3,-3,-1,'arcadia');
INSERT INTO `coords` VALUES (35086,-2,-4,-1,'arcadia');
INSERT INTO `coords` VALUES (35087,-1,-5,-1,'arcadia');
INSERT INTO `coords` VALUES (35088,0,-6,-1,'arcadia');
INSERT INTO `coords` VALUES (35089,1,-5,-1,'arcadia');
INSERT INTO `coords` VALUES (35090,2,-4,-1,'arcadia');
INSERT INTO `coords` VALUES (35091,3,-3,-1,'arcadia');
INSERT INTO `coords` VALUES (35092,4,-2,-1,'arcadia');
INSERT INTO `coords` VALUES (35093,5,-1,-1,'arcadia');
INSERT INTO `coords` VALUES (35094,6,0,-1,'arcadia');
INSERT INTO `coords` VALUES (35095,2,5,-1,'arcadia');
INSERT INTO `coords` VALUES (35096,3,4,-1,'arcadia');
INSERT INTO `coords` VALUES (35097,4,3,-1,'arcadia');
INSERT INTO `coords` VALUES (35098,5,2,-1,'arcadia');
INSERT INTO `coords` VALUES (35099,-3,4,-1,'arcadia');
INSERT INTO `coords` VALUES (35100,-4,3,-1,'arcadia');
INSERT INTO `coords` VALUES (35101,-5,2,-1,'arcadia');
INSERT INTO `coords` VALUES (35102,-5,-2,-1,'arcadia');
INSERT INTO `coords` VALUES (35103,-4,-3,-1,'arcadia');
INSERT INTO `coords` VALUES (35104,-3,-4,-1,'arcadia');
INSERT INTO `coords` VALUES (35105,-2,-5,-1,'arcadia');
INSERT INTO `coords` VALUES (35106,3,-4,-1,'arcadia');
INSERT INTO `coords` VALUES (35107,4,-3,-1,'arcadia');
INSERT INTO `coords` VALUES (35108,6,-1,-1,'arcadia');
INSERT INTO `coords` VALUES (35109,5,-2,-1,'arcadia');
INSERT INTO `coords` VALUES (35110,-6,1,-1,'arcadia');
INSERT INTO `coords` VALUES (35111,-2,5,-1,'arcadia');
INSERT INTO `coords` VALUES (35112,-1,6,-1,'arcadia');
INSERT INTO `coords` VALUES (35113,-6,-1,-1,'arcadia');
INSERT INTO `coords` VALUES (35114,1,6,-1,'arcadia');
INSERT INTO `coords` VALUES (35115,6,1,-1,'arcadia');
INSERT INTO `coords` VALUES (35116,2,-5,-1,'arcadia');
INSERT INTO `coords` VALUES (35117,1,-6,-1,'arcadia');
INSERT INTO `coords` VALUES (35118,-1,-6,-1,'arcadia');
INSERT INTO `coords` VALUES (35119,0,7,-1,'arcadia');
INSERT INTO `coords` VALUES (35120,-1,7,-1,'arcadia');
INSERT INTO `coords` VALUES (35121,-2,6,-1,'arcadia');
INSERT INTO `coords` VALUES (35122,-3,5,-1,'arcadia');
INSERT INTO `coords` VALUES (35123,-4,4,-1,'arcadia');
INSERT INTO `coords` VALUES (35124,-5,3,-1,'arcadia');
INSERT INTO `coords` VALUES (35125,-6,2,-1,'arcadia');
INSERT INTO `coords` VALUES (35126,-7,1,-1,'arcadia');
INSERT INTO `coords` VALUES (35127,-7,0,-1,'arcadia');
INSERT INTO `coords` VALUES (35128,-7,-1,-1,'arcadia');
INSERT INTO `coords` VALUES (35129,-6,-2,-1,'arcadia');
INSERT INTO `coords` VALUES (35130,-5,-3,-1,'arcadia');
INSERT INTO `coords` VALUES (35131,-4,-4,-1,'arcadia');
INSERT INTO `coords` VALUES (35132,-3,-5,-1,'arcadia');
INSERT INTO `coords` VALUES (35133,-2,-6,-1,'arcadia');
INSERT INTO `coords` VALUES (35134,-1,-7,-1,'arcadia');
INSERT INTO `coords` VALUES (35135,0,-7,-1,'arcadia');
INSERT INTO `coords` VALUES (35136,1,-7,-1,'arcadia');
INSERT INTO `coords` VALUES (35137,2,-6,-1,'arcadia');
INSERT INTO `coords` VALUES (35138,3,-5,-1,'arcadia');
INSERT INTO `coords` VALUES (35139,4,-4,-1,'arcadia');
INSERT INTO `coords` VALUES (35140,5,-3,-1,'arcadia');
INSERT INTO `coords` VALUES (35141,6,-2,-1,'arcadia');
INSERT INTO `coords` VALUES (35142,7,-1,-1,'arcadia');
INSERT INTO `coords` VALUES (35143,7,0,-1,'arcadia');
INSERT INTO `coords` VALUES (35144,7,1,-1,'arcadia');
INSERT INTO `coords` VALUES (35145,6,2,-1,'arcadia');
INSERT INTO `coords` VALUES (35146,5,3,-1,'arcadia');
INSERT INTO `coords` VALUES (35147,4,4,-1,'arcadia');
INSERT INTO `coords` VALUES (35148,3,5,-1,'arcadia');
INSERT INTO `coords` VALUES (35149,2,6,-1,'arcadia');
INSERT INTO `coords` VALUES (35150,1,7,-1,'arcadia');
INSERT INTO `coords` VALUES (36410,5,-16,-2,'gaia2');
INSERT INTO `coords` VALUES (37475,3,6,-1,'arcadia');
INSERT INTO `coords` VALUES (37476,4,6,-1,'arcadia');
INSERT INTO `coords` VALUES (37477,5,6,-1,'arcadia');
INSERT INTO `coords` VALUES (37478,5,5,-1,'arcadia');
INSERT INTO `coords` VALUES (37479,5,4,-1,'arcadia');
INSERT INTO `coords` VALUES (37480,4,5,-1,'arcadia');
INSERT INTO `coords` VALUES (37481,6,3,-1,'arcadia');
INSERT INTO `coords` VALUES (37482,6,4,-1,'arcadia');
INSERT INTO `coords` VALUES (37483,6,5,-1,'arcadia');
INSERT INTO `coords` VALUES (37484,6,6,-1,'arcadia');
INSERT INTO `coords` VALUES (37485,6,7,-1,'arcadia');
INSERT INTO `coords` VALUES (37486,5,7,-1,'arcadia');
INSERT INTO `coords` VALUES (37487,4,7,-1,'arcadia');
INSERT INTO `coords` VALUES (37488,3,7,-1,'arcadia');
INSERT INTO `coords` VALUES (37489,2,7,-1,'arcadia');
INSERT INTO `coords` VALUES (37490,-6,-3,-1,'arcadia');
INSERT INTO `coords` VALUES (37491,-6,-4,-1,'arcadia');
INSERT INTO `coords` VALUES (37492,-5,-4,-1,'arcadia');
INSERT INTO `coords` VALUES (37493,-4,-5,-1,'arcadia');
INSERT INTO `coords` VALUES (37494,-5,-5,-1,'arcadia');
INSERT INTO `coords` VALUES (37495,-6,-5,-1,'arcadia');
INSERT INTO `coords` VALUES (37496,-6,-6,-1,'arcadia');
INSERT INTO `coords` VALUES (37497,-5,-6,-1,'arcadia');
INSERT INTO `coords` VALUES (37498,-4,-6,-1,'arcadia');
INSERT INTO `coords` VALUES (37499,-3,-6,-1,'arcadia');
INSERT INTO `coords` VALUES (37500,-2,-7,-1,'arcadia');
INSERT INTO `coords` VALUES (37501,-3,-7,-1,'arcadia');
INSERT INTO `coords` VALUES (37502,-4,-7,-1,'arcadia');
INSERT INTO `coords` VALUES (37503,-5,-7,-1,'arcadia');
INSERT INTO `coords` VALUES (37504,-6,-7,-1,'arcadia');
INSERT INTO `coords` VALUES (38239,0,-138,0,'enfers');
INSERT INTO `coords` VALUES (38897,-8,0,0,'enfers');
INSERT INTO `coords` VALUES (38898,-7,0,0,'enfers');
INSERT INTO `coords` VALUES (38901,-6,0,0,'enfers');
INSERT INTO `coords` VALUES (38992,-6,-1,0,'enfers');
INSERT INTO `coords` VALUES (39088,-6,1,0,'enfers');
INSERT INTO `coords` VALUES (44740,0,-18,0,'enfers');
INSERT INTO `coords` VALUES (44902,-15,0,0,'enfers');
INSERT INTO `coords` VALUES (45023,-15,-15,0,'enfers');
INSERT INTO `coords` VALUES (45353,1,-138,0,'enfers');
INSERT INTO `coords` VALUES (45674,-100,0,0,'enfers');
INSERT INTO `coords` VALUES (45677,-7,-6,-1,'arcadia');
INSERT INTO `coords` VALUES (46188,8,-9,0,'gaia');
INSERT INTO `coords` VALUES (46283,-14,0,0,'enfers');
INSERT INTO `coords` VALUES (46328,-13,1,0,'enfers');
INSERT INTO `coords` VALUES (46329,-12,2,0,'enfers');
INSERT INTO `coords` VALUES (46330,-11,2,0,'enfers');
INSERT INTO `coords` VALUES (46331,-10,2,0,'enfers');
INSERT INTO `coords` VALUES (46332,-9,2,0,'enfers');
INSERT INTO `coords` VALUES (46333,-9,3,0,'enfers');
INSERT INTO `coords` VALUES (46334,-8,4,0,'enfers');
INSERT INTO `coords` VALUES (46335,-7,4,0,'enfers');
INSERT INTO `coords` VALUES (46344,-13,0,0,'enfers');
INSERT INTO `coords` VALUES (46345,-12,0,0,'enfers');
INSERT INTO `coords` VALUES (46346,-11,1,0,'enfers');
INSERT INTO `coords` VALUES (46347,-10,1,0,'enfers');
INSERT INTO `coords` VALUES (46348,-9,1,0,'enfers');
INSERT INTO `coords` VALUES (46349,-8,1,0,'enfers');
INSERT INTO `coords` VALUES (46350,-7,1,0,'enfers');
INSERT INTO `coords` VALUES (46351,-7,2,0,'enfers');
INSERT INTO `coords` VALUES (46416,-6,3,0,'enfers');
INSERT INTO `coords` VALUES (46417,-5,3,0,'enfers');
INSERT INTO `coords` VALUES (46418,-4,3,0,'enfers');
INSERT INTO `coords` VALUES (46419,-5,2,0,'enfers');
INSERT INTO `coords` VALUES (46420,-4,1,0,'enfers');
INSERT INTO `coords` VALUES (46421,-3,1,0,'enfers');
INSERT INTO `coords` VALUES (46422,-2,0,0,'enfers');
INSERT INTO `coords` VALUES (46736,0,-210,0,'enfers');
INSERT INTO `coords` VALUES (47221,-140,0,0,'enfers');
INSERT INTO `coords` VALUES (47610,-3,0,0,'enfers');
INSERT INTO `coords` VALUES (47665,-11,0,0,'enfers');
INSERT INTO `coords` VALUES (47666,-10,0,0,'enfers');
INSERT INTO `coords` VALUES (47667,-8,2,0,'enfers');
INSERT INTO `coords` VALUES (47717,-4,2,0,'enfers');
INSERT INTO `coords` VALUES (48276,-4,-1,0,'enfers');
INSERT INTO `coords` VALUES (48531,1,2,0,'gaia2');
INSERT INTO `coords` VALUES (50671,210,0,0,'enfers');
INSERT INTO `coords` VALUES (50758,70,0,0,'enfers');
INSERT INTO `coords` VALUES (51525,1,-2,-1,'gaia2');
INSERT INTO `coords` VALUES (51580,-210,0,0,'enfers');
INSERT INTO `coords` VALUES (51586,-210,-210,0,'enfers');
INSERT INTO `coords` VALUES (51587,6,-8,0,'gaia');
INSERT INTO `coords` VALUES (51588,-4,0,0,'enfers');
INSERT INTO `coords` VALUES (51589,0,2,0,'enfers');
INSERT INTO `coords` VALUES (51590,-4,-4,0,'tutorial');
INSERT INTO `coords` VALUES (51591,-3,-4,0,'tutorial');
INSERT INTO `coords` VALUES (51592,-2,-4,0,'tutorial');
INSERT INTO `coords` VALUES (51593,-1,-4,0,'tutorial');
INSERT INTO `coords` VALUES (51594,0,-4,0,'tutorial');
INSERT INTO `coords` VALUES (51595,1,-4,0,'tutorial');
INSERT INTO `coords` VALUES (51596,2,-4,0,'tutorial');
INSERT INTO `coords` VALUES (51597,3,-4,0,'tutorial');
INSERT INTO `coords` VALUES (51598,4,-4,0,'tutorial');
INSERT INTO `coords` VALUES (51599,-4,-3,0,'tutorial');
INSERT INTO `coords` VALUES (51600,-3,-3,0,'tutorial');
INSERT INTO `coords` VALUES (51601,-2,-3,0,'tutorial');
INSERT INTO `coords` VALUES (51602,-1,-3,0,'tutorial');
INSERT INTO `coords` VALUES (51603,0,-3,0,'tutorial');
INSERT INTO `coords` VALUES (51604,1,-3,0,'tutorial');
INSERT INTO `coords` VALUES (51605,2,-3,0,'tutorial');
INSERT INTO `coords` VALUES (51606,3,-3,0,'tutorial');
INSERT INTO `coords` VALUES (51607,4,-3,0,'tutorial');
INSERT INTO `coords` VALUES (51608,-4,-2,0,'tutorial');
INSERT INTO `coords` VALUES (51609,-3,-2,0,'tutorial');
INSERT INTO `coords` VALUES (51610,-2,-2,0,'tutorial');
INSERT INTO `coords` VALUES (51611,-1,-2,0,'tutorial');
INSERT INTO `coords` VALUES (51612,0,-2,0,'tutorial');
INSERT INTO `coords` VALUES (51613,1,-2,0,'tutorial');
INSERT INTO `coords` VALUES (51614,2,-2,0,'tutorial');
INSERT INTO `coords` VALUES (51615,3,-2,0,'tutorial');
INSERT INTO `coords` VALUES (51616,4,-2,0,'tutorial');
INSERT INTO `coords` VALUES (51617,-4,-1,0,'tutorial');
INSERT INTO `coords` VALUES (51618,-3,-1,0,'tutorial');
INSERT INTO `coords` VALUES (51619,-2,-1,0,'tutorial');
INSERT INTO `coords` VALUES (51620,-1,-1,0,'tutorial');
INSERT INTO `coords` VALUES (51621,0,-1,0,'tutorial');
INSERT INTO `coords` VALUES (51622,1,-1,0,'tutorial');
INSERT INTO `coords` VALUES (51623,2,-1,0,'tutorial');
INSERT INTO `coords` VALUES (51624,3,-1,0,'tutorial');
INSERT INTO `coords` VALUES (51625,4,-1,0,'tutorial');
INSERT INTO `coords` VALUES (51626,-4,0,0,'tutorial');
INSERT INTO `coords` VALUES (51627,-3,0,0,'tutorial');
INSERT INTO `coords` VALUES (51628,-2,0,0,'tutorial');
INSERT INTO `coords` VALUES (51629,-1,0,0,'tutorial');
INSERT INTO `coords` VALUES (51630,0,0,0,'tutorial');
INSERT INTO `coords` VALUES (51631,1,0,0,'tutorial');
INSERT INTO `coords` VALUES (51632,2,0,0,'tutorial');
INSERT INTO `coords` VALUES (51633,3,0,0,'tutorial');
INSERT INTO `coords` VALUES (51634,4,0,0,'tutorial');
INSERT INTO `coords` VALUES (51635,-4,1,0,'tutorial');
INSERT INTO `coords` VALUES (51636,-3,1,0,'tutorial');
INSERT INTO `coords` VALUES (51637,-2,1,0,'tutorial');
INSERT INTO `coords` VALUES (51638,-1,1,0,'tutorial');
INSERT INTO `coords` VALUES (51639,0,1,0,'tutorial');
INSERT INTO `coords` VALUES (51640,1,1,0,'tutorial');
INSERT INTO `coords` VALUES (51641,2,1,0,'tutorial');
INSERT INTO `coords` VALUES (51642,3,1,0,'tutorial');
INSERT INTO `coords` VALUES (51643,4,1,0,'tutorial');
INSERT INTO `coords` VALUES (51644,-4,2,0,'tutorial');
INSERT INTO `coords` VALUES (51645,-3,2,0,'tutorial');
INSERT INTO `coords` VALUES (51646,-2,2,0,'tutorial');
INSERT INTO `coords` VALUES (51647,-1,2,0,'tutorial');
INSERT INTO `coords` VALUES (51648,0,2,0,'tutorial');
INSERT INTO `coords` VALUES (51649,1,2,0,'tutorial');
INSERT INTO `coords` VALUES (51650,2,2,0,'tutorial');
INSERT INTO `coords` VALUES (51651,3,2,0,'tutorial');
INSERT INTO `coords` VALUES (51652,4,2,0,'tutorial');
INSERT INTO `coords` VALUES (51653,-4,3,0,'tutorial');
INSERT INTO `coords` VALUES (51654,-3,3,0,'tutorial');
INSERT INTO `coords` VALUES (51655,-2,3,0,'tutorial');
INSERT INTO `coords` VALUES (51656,-1,3,0,'tutorial');
INSERT INTO `coords` VALUES (51657,0,3,0,'tutorial');
INSERT INTO `coords` VALUES (51658,1,3,0,'tutorial');
INSERT INTO `coords` VALUES (51659,2,3,0,'tutorial');
INSERT INTO `coords` VALUES (51660,3,3,0,'tutorial');
INSERT INTO `coords` VALUES (51661,4,3,0,'tutorial');
INSERT INTO `coords` VALUES (51662,-4,4,0,'tutorial');
INSERT INTO `coords` VALUES (51663,-3,4,0,'tutorial');
INSERT INTO `coords` VALUES (51664,-2,4,0,'tutorial');
INSERT INTO `coords` VALUES (51665,-1,4,0,'tutorial');
INSERT INTO `coords` VALUES (51666,0,4,0,'tutorial');
INSERT INTO `coords` VALUES (51667,1,4,0,'tutorial');
INSERT INTO `coords` VALUES (51668,2,4,0,'tutorial');
INSERT INTO `coords` VALUES (51669,3,4,0,'tutorial');
INSERT INTO `coords` VALUES (51670,4,4,0,'tutorial');
/*!40000 ALTER TABLE `coords` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `craft_recipes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `craft_recipes` DISABLE KEYS */;
/*!40000 ALTER TABLE `craft_recipes` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `craft_recipes_ingredients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `count` int(11) NOT NULL DEFAULT 1,
  `recipe_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_3A88044F59D8A214` (`recipe_id`),
  KEY `IDX_3A88044F126F525E` (`item_id`),
  CONSTRAINT `FK_3A88044F126F525E` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`),
  CONSTRAINT `FK_3A88044F59D8A214` FOREIGN KEY (`recipe_id`) REFERENCES `craft_recipes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `craft_recipes_ingredients` DISABLE KEYS */;
/*!40000 ALTER TABLE `craft_recipes_ingredients` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `craft_recipes_results` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `count` int(11) NOT NULL DEFAULT 1,
  `recipe_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_7684F80159D8A214` (`recipe_id`),
  KEY `IDX_7684F801126F525E` (`item_id`),
  CONSTRAINT `FK_7684F801126F525E` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`),
  CONSTRAINT `FK_7684F80159D8A214` FOREIGN KEY (`recipe_id`) REFERENCES `craft_recipes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `craft_recipes_results` DISABLE KEYS */;
/*!40000 ALTER TABLE `craft_recipes_results` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `doctrine_migration_versions` (
  `version` varchar(191) NOT NULL,
  `executed_at` datetime DEFAULT NULL,
  `execution_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `doctrine_migration_versions` DISABLE KEYS */;
INSERT INTO `doctrine_migration_versions` VALUES ('App\\Migrations\\Version20250427223731','2026-04-19 08:23:02',0);
INSERT INTO `doctrine_migration_versions` VALUES ('App\\Migrations\\Version20251127000000_CreateCompleteTutorialSystem','2026-04-19 08:23:30',337);
INSERT INTO `doctrine_migration_versions` VALUES ('App\\Migrations\\Version20260102000000_AddCraftingTutorial','2026-04-19 08:23:30',6);
INSERT INTO `doctrine_migration_versions` VALUES ('App\\Migrations\\Version20260419130000_AddBonusPointsToPlayers','2026-04-19 13:00:00',0);
INSERT INTO `doctrine_migration_versions` VALUES ('App\\Migrations\\Version20260419180000_AddTutorialColumnsToPlayers','2026-04-19 18:00:00',0);
INSERT INTO `doctrine_migration_versions` VALUES ('App\\Migrations\\Version20260419200000_DropTutorialPlayersRealPlayerIdColumn','2026-04-19 20:00:00',0);
INSERT INTO `doctrine_migration_versions` VALUES ('App\\Migrations\\Version20260419210000_AddRealPlayerIdRefForeignKey','2026-04-19 21:00:00',0);
/*!40000 ALTER TABLE `doctrine_migration_versions` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `forums_cookie` (
  `post_name` varchar(20) NOT NULL,
  `player_id` int(11) NOT NULL,
  PRIMARY KEY (`post_name`,`player_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `forums_cookie` DISABLE KEYS */;
/*!40000 ALTER TABLE `forums_cookie` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `forums_keywords` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `postName` bigint(20) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `forums_keywords` DISABLE KEYS */;
INSERT INTO `forums_keywords` VALUES (1,'message',1741391472036);
/*!40000 ALTER TABLE `forums_keywords` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `private` int(11) NOT NULL DEFAULT 0,
  `enchanted` int(1) NOT NULL DEFAULT 0,
  `vorpal` int(1) NOT NULL DEFAULT 0,
  `cursed` int(1) NOT NULL DEFAULT 0,
  `element` varchar(255) NOT NULL DEFAULT '',
  `spell` varchar(255) DEFAULT NULL,
  `is_deprecated` tinyint(1) NOT NULL DEFAULT 0,
  `is_bankable` tinyint(1) NOT NULL DEFAULT 1,
  `exotique` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=130 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `items` DISABLE KEYS */;
INSERT INTO `items` VALUES (1,'or',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (2,'alcool_tourbe',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (3,'altar',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (4,'arbalete_poing',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (5,'arc',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (6,'armure_boue',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (7,'armure_matelassee',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (8,'baton_marche',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (9,'bottes_marche',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (10,'bouclier_parma',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (11,'canne_a_peche',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (12,'carreau',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (13,'casque_illyrien',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (14,'coffre_bois',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (15,'encre',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (16,'fleche',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (17,'fustibale',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (18,'gladius_entrainement',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (19,'gladius',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (20,'sceptre',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (21,'hache_entrainement',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (22,'lance',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (23,'mur_bois',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (24,'mur_bois_petrifie',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (25,'mur_pierre',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (26,'parchemin_sort',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (27,'parchemin',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (28,'piedestal_pierre',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (29,'javelot_entrainement',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (30,'pioche',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (31,'projectile_magique',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (32,'route',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (33,'pugio',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (34,'savon',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (35,'table_bois',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (36,'torche',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (37,'anneau_horizon',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (38,'anneau_caprice',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (39,'anneau_puissance',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (40,'armure_boue',0,0,0,1,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (41,'bottes_sept_lieux',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (42,'obole_sacree',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (43,'armure_ecailles',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (44,'belier',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (45,'bouclier_clipeus',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (46,'carnyx',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (47,'javelot',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (48,'aulos',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (49,'baton_pellerin',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (50,'bottes_talroval',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (51,'coffre_bois_petrifie',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (52,'cuirasse',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (53,'flagrum',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (54,'statue_ailee',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (55,'targe',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (56,'boleadoras',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (57,'casse_tete',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (58,'encre_tatouage',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (59,'ikula_ceremoniel',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (60,'manteau_feuillage',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (61,'marque_main_blanche',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (62,'robe_mage',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (63,'cymbale',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (64,'armure_hoplitique',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (65,'bouclier_ancile',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (66,'diademe',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (67,'gastraphete',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (68,'lame_benie',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (69,'phorminx',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (70,'piedestal',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (71,'pilum',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (72,'statue_gisant',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (73,'statue_heroique',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (74,'statue_monstrueuse',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (75,'casque',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (76,'coffre_metal',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (77,'cotte_mailles',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (78,'grenade',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (79,'labrys',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (80,'marteau_guerre',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (81,'biere_redoraane',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (82,'conque',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (83,'armet_incruste',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (84,'trident',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (85,'adonis',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (86,'pierre',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (87,'cendre',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (88,'tourbe',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (89,'bois',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (90,'bronze',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (91,'salpetre',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (92,'nickel',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (93,'cuir',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (94,'bois_petrifie',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (95,'pierre_mana',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (96,'nara',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (97,'ivoire',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (98,'lotus_noir',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (99,'houblon',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (100,'lichen_sacre',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (101,'coco',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (102,'astral',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (103,'cornemuse',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (104,'baton_marche',0,1,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (105,'armure_boue',0,1,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (106,'baton_marche',0,0,1,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (107,'baton_marche',0,0,0,1,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (109,'poing',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (110,'parchemin_sort',0,0,0,0,'','dmg1/lame_volante',0,1,NULL);
INSERT INTO `items` VALUES (111,'parchemin_sort',0,0,0,0,'','dmg2/desarmement',0,1,NULL);
INSERT INTO `items` VALUES (112,'parchemin_sort',0,0,0,0,'','soins/imposition_des_mains',0,1,NULL);
INSERT INTO `items` VALUES (113,'parchemin_sort',0,0,0,0,'','special/lame_benie',0,1,NULL);
INSERT INTO `items` VALUES (117,'pugio',0,1,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (121,'pavot',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (123,'echelle',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (124,'menthe',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (125,'armet_incruste',0,1,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (126,'cafe',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (127,'mur_noir',0,0,0,0,'',NULL,0,1,NULL);
INSERT INTO `items` VALUES (128,'parchemin_sort',0,0,0,0,'','dps/poings_pierre',0,1,NULL);
INSERT INTO `items` VALUES (129,'parchemin_sort',0,0,0,0,'','special/attaque_sautee',0,1,NULL);
/*!40000 ALTER TABLE `items` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `items_asks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `price` int(11) NOT NULL,
  `n` int(11) NOT NULL,
  `stock` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `item_id` (`item_id`),
  KEY `player_id` (`player_id`),
  CONSTRAINT `items_asks_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`),
  CONSTRAINT `items_asks_ibfk_2` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `items_asks` DISABLE KEYS */;
/*!40000 ALTER TABLE `items_asks` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `items_bids` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `price` int(11) NOT NULL,
  `n` int(11) NOT NULL,
  `stock` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `item_id` (`item_id`),
  KEY `player_id` (`player_id`),
  CONSTRAINT `items_bids_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`),
  CONSTRAINT `items_bids_ibfk_2` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `items_bids` DISABLE KEYS */;
/*!40000 ALTER TABLE `items_bids` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `items_exchanges` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `player_id` int(11) NOT NULL,
  `target_id` int(11) NOT NULL,
  `player_ok` tinyint(1) NOT NULL DEFAULT 0,
  `target_ok` tinyint(1) NOT NULL DEFAULT 0,
  `update_time` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `items_exchanges_fk_1` (`player_id`),
  KEY `items_exchanges_fk_2` (`target_id`),
  CONSTRAINT `items_exchanges_fk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`),
  CONSTRAINT `items_exchanges_fk_2` FOREIGN KEY (`target_id`) REFERENCES `players` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `items_exchanges` DISABLE KEYS */;
/*!40000 ALTER TABLE `items_exchanges` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `map_dialogs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `coords_id` int(11) NOT NULL,
  `params` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `coords_id` (`coords_id`),
  CONSTRAINT `map_dialogs_ibfk_1` FOREIGN KEY (`coords_id`) REFERENCES `coords` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `map_dialogs` DISABLE KEYS */;
/*!40000 ALTER TABLE `map_dialogs` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `map_elements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `coords_id` int(11) NOT NULL,
  `endTime` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`name`,`coords_id`),
  UNIQUE KEY `id` (`id`),
  KEY `coords_id` (`coords_id`),
  CONSTRAINT `map_elements_ibfk_1` FOREIGN KEY (`coords_id`) REFERENCES `coords` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1135 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `map_elements` DISABLE KEYS */;
INSERT INTO `map_elements` VALUES (1,'boue',27884,0);
INSERT INTO `map_elements` VALUES (2,'boue',35058,0);
INSERT INTO `map_elements` VALUES (3,'boue',35059,0);
INSERT INTO `map_elements` VALUES (4,'boue',35070,0);
INSERT INTO `map_elements` VALUES (5,'boue',35079,0);
INSERT INTO `map_elements` VALUES (6,'boue',35080,0);
INSERT INTO `map_elements` VALUES (7,'boue',35086,0);
INSERT INTO `map_elements` VALUES (8,'boue',35092,0);
INSERT INTO `map_elements` VALUES (9,'boue',35093,0);
INSERT INTO `map_elements` VALUES (10,'boue',35099,0);
INSERT INTO `map_elements` VALUES (11,'boue',35100,0);
INSERT INTO `map_elements` VALUES (12,'boue',35101,0);
INSERT INTO `map_elements` VALUES (13,'boue',35104,0);
INSERT INTO `map_elements` VALUES (14,'boue',35105,0);
INSERT INTO `map_elements` VALUES (15,'boue',35107,0);
INSERT INTO `map_elements` VALUES (16,'boue',35108,0);
INSERT INTO `map_elements` VALUES (17,'boue',35109,0);
INSERT INTO `map_elements` VALUES (18,'boue',35118,0);
INSERT INTO `map_elements` VALUES (19,'diamant',11,0);
INSERT INTO `map_elements` VALUES (20,'diamant',12,0);
INSERT INTO `map_elements` VALUES (21,'diamant',17,0);
INSERT INTO `map_elements` VALUES (22,'diamant',18,0);
INSERT INTO `map_elements` VALUES (23,'diamant',19,0);
INSERT INTO `map_elements` VALUES (24,'diamant',21,0);
INSERT INTO `map_elements` VALUES (25,'diamant',22,0);
INSERT INTO `map_elements` VALUES (26,'diamant',23,0);
INSERT INTO `map_elements` VALUES (27,'diamant',54,0);
INSERT INTO `map_elements` VALUES (28,'diamant',57,0);
INSERT INTO `map_elements` VALUES (29,'diamant',105,0);
INSERT INTO `map_elements` VALUES (30,'diamant',108,0);
INSERT INTO `map_elements` VALUES (31,'diamant',118,0);
INSERT INTO `map_elements` VALUES (32,'diamant',119,0);
INSERT INTO `map_elements` VALUES (33,'diamant',120,0);
INSERT INTO `map_elements` VALUES (34,'diamant',121,0);
INSERT INTO `map_elements` VALUES (35,'diamant',122,0);
INSERT INTO `map_elements` VALUES (36,'diamant',123,0);
INSERT INTO `map_elements` VALUES (37,'diamant',155,0);
INSERT INTO `map_elements` VALUES (38,'diamant',156,0);
INSERT INTO `map_elements` VALUES (39,'diamant',35017,0);
INSERT INTO `map_elements` VALUES (40,'diamant',35027,0);
INSERT INTO `map_elements` VALUES (41,'diamant',35060,0);
INSERT INTO `map_elements` VALUES (42,'diamant',35063,0);
INSERT INTO `map_elements` VALUES (43,'diamant',35085,0);
INSERT INTO `map_elements` VALUES (44,'diamant',35100,0);
INSERT INTO `map_elements` VALUES (45,'diamant',35110,0);
INSERT INTO `map_elements` VALUES (46,'diamant',35113,0);
INSERT INTO `map_elements` VALUES (47,'eau',16595,0);
INSERT INTO `map_elements` VALUES (48,'eau',16596,0);
INSERT INTO `map_elements` VALUES (49,'eau',16597,0);
INSERT INTO `map_elements` VALUES (50,'eau',16598,0);
INSERT INTO `map_elements` VALUES (51,'eau',16599,0);
INSERT INTO `map_elements` VALUES (52,'eau',16600,0);
INSERT INTO `map_elements` VALUES (53,'eau',16601,0);
INSERT INTO `map_elements` VALUES (54,'eau',16602,0);
INSERT INTO `map_elements` VALUES (55,'eau',16604,0);
INSERT INTO `map_elements` VALUES (56,'eau',16605,0);
INSERT INTO `map_elements` VALUES (57,'eau',16610,0);
INSERT INTO `map_elements` VALUES (58,'eau',16613,0);
INSERT INTO `map_elements` VALUES (59,'eau',16657,0);
INSERT INTO `map_elements` VALUES (60,'eau',16658,0);
INSERT INTO `map_elements` VALUES (61,'eau',16659,0);
INSERT INTO `map_elements` VALUES (62,'eau',16660,0);
INSERT INTO `map_elements` VALUES (63,'eau',16663,0);
INSERT INTO `map_elements` VALUES (64,'eau',16664,0);
INSERT INTO `map_elements` VALUES (65,'eau',16665,0);
INSERT INTO `map_elements` VALUES (66,'eau',16669,0);
INSERT INTO `map_elements` VALUES (67,'eau',16670,0);
INSERT INTO `map_elements` VALUES (68,'eau',16672,0);
INSERT INTO `map_elements` VALUES (69,'eau',16673,0);
INSERT INTO `map_elements` VALUES (70,'eau',16676,0);
INSERT INTO `map_elements` VALUES (71,'eau',16677,0);
INSERT INTO `map_elements` VALUES (72,'eau',16686,0);
INSERT INTO `map_elements` VALUES (73,'eau',16689,0);
INSERT INTO `map_elements` VALUES (74,'eau',16690,0);
INSERT INTO `map_elements` VALUES (75,'eau',16692,0);
INSERT INTO `map_elements` VALUES (76,'eau',16704,0);
INSERT INTO `map_elements` VALUES (77,'eau',16705,0);
INSERT INTO `map_elements` VALUES (78,'eau',16706,0);
INSERT INTO `map_elements` VALUES (79,'eau',16707,0);
INSERT INTO `map_elements` VALUES (80,'eau',16708,0);
INSERT INTO `map_elements` VALUES (81,'eau',16710,0);
INSERT INTO `map_elements` VALUES (82,'eau',16711,0);
INSERT INTO `map_elements` VALUES (83,'eau',16712,0);
INSERT INTO `map_elements` VALUES (84,'eau',16713,0);
INSERT INTO `map_elements` VALUES (85,'eau',16714,0);
INSERT INTO `map_elements` VALUES (86,'eau',16715,0);
INSERT INTO `map_elements` VALUES (87,'eau',16717,0);
INSERT INTO `map_elements` VALUES (88,'eau',16718,0);
INSERT INTO `map_elements` VALUES (89,'eau',16719,0);
INSERT INTO `map_elements` VALUES (90,'eau',16720,0);
INSERT INTO `map_elements` VALUES (91,'eau',16721,0);
INSERT INTO `map_elements` VALUES (92,'eau',16722,0);
INSERT INTO `map_elements` VALUES (93,'eau',16746,0);
INSERT INTO `map_elements` VALUES (94,'eau',16751,0);
INSERT INTO `map_elements` VALUES (95,'eau',16756,0);
INSERT INTO `map_elements` VALUES (96,'eau',16758,0);
INSERT INTO `map_elements` VALUES (97,'eau',16759,0);
INSERT INTO `map_elements` VALUES (98,'eau',16764,0);
INSERT INTO `map_elements` VALUES (99,'eau',16765,0);
INSERT INTO `map_elements` VALUES (100,'eau',16769,0);
INSERT INTO `map_elements` VALUES (101,'eau',16770,0);
INSERT INTO `map_elements` VALUES (102,'eau',16771,0);
INSERT INTO `map_elements` VALUES (103,'eau',16790,0);
INSERT INTO `map_elements` VALUES (104,'eau',16791,0);
INSERT INTO `map_elements` VALUES (105,'eau',16794,0);
INSERT INTO `map_elements` VALUES (106,'eau',16795,0);
INSERT INTO `map_elements` VALUES (107,'eau',16796,0);
INSERT INTO `map_elements` VALUES (108,'eau',16797,0);
INSERT INTO `map_elements` VALUES (109,'eau',16802,0);
INSERT INTO `map_elements` VALUES (110,'eau',16803,0);
INSERT INTO `map_elements` VALUES (111,'eau',16804,0);
INSERT INTO `map_elements` VALUES (112,'eau',16806,0);
INSERT INTO `map_elements` VALUES (113,'eau',16807,0);
INSERT INTO `map_elements` VALUES (114,'eau',16808,0);
INSERT INTO `map_elements` VALUES (115,'eau',16809,0);
INSERT INTO `map_elements` VALUES (116,'eau',16811,0);
INSERT INTO `map_elements` VALUES (117,'eau',16812,0);
INSERT INTO `map_elements` VALUES (118,'eau',16813,0);
INSERT INTO `map_elements` VALUES (119,'eau',16814,0);
INSERT INTO `map_elements` VALUES (120,'eau',16815,0);
INSERT INTO `map_elements` VALUES (121,'eau',16816,0);
INSERT INTO `map_elements` VALUES (122,'eau',16817,0);
INSERT INTO `map_elements` VALUES (123,'eau',16853,0);
INSERT INTO `map_elements` VALUES (124,'eau',16855,0);
INSERT INTO `map_elements` VALUES (125,'eau',16856,0);
INSERT INTO `map_elements` VALUES (126,'eau',16859,0);
INSERT INTO `map_elements` VALUES (127,'eau',16864,0);
INSERT INTO `map_elements` VALUES (128,'eau',16866,0);
INSERT INTO `map_elements` VALUES (129,'eau',16868,0);
INSERT INTO `map_elements` VALUES (130,'eau',16876,0);
INSERT INTO `map_elements` VALUES (131,'eau',16878,0);
INSERT INTO `map_elements` VALUES (132,'eau',16879,0);
INSERT INTO `map_elements` VALUES (133,'eau',16881,0);
INSERT INTO `map_elements` VALUES (134,'eau',16882,0);
INSERT INTO `map_elements` VALUES (135,'eau',16883,0);
INSERT INTO `map_elements` VALUES (136,'eau',16884,0);
INSERT INTO `map_elements` VALUES (137,'eau',16885,0);
INSERT INTO `map_elements` VALUES (477,'eau',17275,0);
INSERT INTO `map_elements` VALUES (478,'eau',17277,0);
INSERT INTO `map_elements` VALUES (479,'eau',17281,0);
INSERT INTO `map_elements` VALUES (480,'eau',17285,0);
INSERT INTO `map_elements` VALUES (481,'eau',17286,0);
INSERT INTO `map_elements` VALUES (482,'eau',17287,0);
INSERT INTO `map_elements` VALUES (483,'eau',17327,0);
INSERT INTO `map_elements` VALUES (484,'eau',17328,0);
INSERT INTO `map_elements` VALUES (485,'eau',17329,0);
INSERT INTO `map_elements` VALUES (486,'eau',17366,0);
INSERT INTO `map_elements` VALUES (487,'eau',17367,0);
INSERT INTO `map_elements` VALUES (488,'eau',17369,0);
INSERT INTO `map_elements` VALUES (489,'eau',17372,0);
INSERT INTO `map_elements` VALUES (490,'eau',17373,0);
INSERT INTO `map_elements` VALUES (491,'eau',17374,0);
INSERT INTO `map_elements` VALUES (492,'eau',17375,0);
INSERT INTO `map_elements` VALUES (493,'eau',17376,0);
INSERT INTO `map_elements` VALUES (494,'eau',17422,0);
INSERT INTO `map_elements` VALUES (495,'eau',17430,0);
INSERT INTO `map_elements` VALUES (138,'eau',19770,0);
INSERT INTO `map_elements` VALUES (139,'eau',19771,0);
INSERT INTO `map_elements` VALUES (140,'eau',19772,0);
INSERT INTO `map_elements` VALUES (141,'eau',19773,0);
INSERT INTO `map_elements` VALUES (142,'eau',19774,0);
INSERT INTO `map_elements` VALUES (143,'eau',19775,0);
INSERT INTO `map_elements` VALUES (144,'eau',19777,0);
INSERT INTO `map_elements` VALUES (145,'eau',19778,0);
INSERT INTO `map_elements` VALUES (146,'eau',19780,0);
INSERT INTO `map_elements` VALUES (147,'eau',19781,0);
INSERT INTO `map_elements` VALUES (148,'eau',19782,0);
INSERT INTO `map_elements` VALUES (149,'eau',19783,0);
INSERT INTO `map_elements` VALUES (150,'eau',19784,0);
INSERT INTO `map_elements` VALUES (151,'eau',19785,0);
INSERT INTO `map_elements` VALUES (152,'eau',19788,0);
INSERT INTO `map_elements` VALUES (153,'eau',19789,0);
INSERT INTO `map_elements` VALUES (154,'eau',19790,0);
INSERT INTO `map_elements` VALUES (155,'eau',19791,0);
INSERT INTO `map_elements` VALUES (156,'eau',19792,0);
INSERT INTO `map_elements` VALUES (157,'eau',19793,0);
INSERT INTO `map_elements` VALUES (158,'eau',19798,0);
INSERT INTO `map_elements` VALUES (159,'eau',19799,0);
INSERT INTO `map_elements` VALUES (160,'eau',19800,0);
INSERT INTO `map_elements` VALUES (161,'eau',19801,0);
INSERT INTO `map_elements` VALUES (162,'eau',19802,0);
INSERT INTO `map_elements` VALUES (163,'eau',19803,0);
INSERT INTO `map_elements` VALUES (164,'eau',19804,0);
INSERT INTO `map_elements` VALUES (165,'eau',19805,0);
INSERT INTO `map_elements` VALUES (166,'eau',19806,0);
INSERT INTO `map_elements` VALUES (167,'eau',19807,0);
INSERT INTO `map_elements` VALUES (168,'eau',19808,0);
INSERT INTO `map_elements` VALUES (169,'eau',19809,0);
INSERT INTO `map_elements` VALUES (170,'eau',19810,0);
INSERT INTO `map_elements` VALUES (171,'eau',19811,0);
INSERT INTO `map_elements` VALUES (172,'eau',19812,0);
INSERT INTO `map_elements` VALUES (173,'eau',19813,0);
INSERT INTO `map_elements` VALUES (174,'eau',19814,0);
INSERT INTO `map_elements` VALUES (175,'eau',19815,0);
INSERT INTO `map_elements` VALUES (176,'eau',19816,0);
INSERT INTO `map_elements` VALUES (177,'eau',19817,0);
INSERT INTO `map_elements` VALUES (178,'eau',19818,0);
INSERT INTO `map_elements` VALUES (179,'eau',19819,0);
INSERT INTO `map_elements` VALUES (180,'eau',19820,0);
INSERT INTO `map_elements` VALUES (181,'eau',19821,0);
INSERT INTO `map_elements` VALUES (182,'eau',19822,0);
INSERT INTO `map_elements` VALUES (183,'eau',19823,0);
INSERT INTO `map_elements` VALUES (184,'eau',19824,0);
INSERT INTO `map_elements` VALUES (185,'eau',19825,0);
INSERT INTO `map_elements` VALUES (186,'eau',19826,0);
INSERT INTO `map_elements` VALUES (187,'eau',19827,0);
INSERT INTO `map_elements` VALUES (188,'eau',19828,0);
INSERT INTO `map_elements` VALUES (189,'eau',19829,0);
INSERT INTO `map_elements` VALUES (190,'eau',19830,0);
INSERT INTO `map_elements` VALUES (191,'eau',19831,0);
INSERT INTO `map_elements` VALUES (192,'eau',19832,0);
INSERT INTO `map_elements` VALUES (193,'eau',19833,0);
INSERT INTO `map_elements` VALUES (194,'eau',19834,0);
INSERT INTO `map_elements` VALUES (195,'eau',19835,0);
INSERT INTO `map_elements` VALUES (196,'eau',19836,0);
INSERT INTO `map_elements` VALUES (197,'eau',19837,0);
INSERT INTO `map_elements` VALUES (198,'eau',19838,0);
INSERT INTO `map_elements` VALUES (199,'eau',19839,0);
INSERT INTO `map_elements` VALUES (200,'eau',19841,0);
INSERT INTO `map_elements` VALUES (201,'eau',19842,0);
INSERT INTO `map_elements` VALUES (202,'eau',19843,0);
INSERT INTO `map_elements` VALUES (203,'eau',19844,0);
INSERT INTO `map_elements` VALUES (204,'eau',19845,0);
INSERT INTO `map_elements` VALUES (205,'eau',19846,0);
INSERT INTO `map_elements` VALUES (206,'eau',19847,0);
INSERT INTO `map_elements` VALUES (207,'eau',19848,0);
INSERT INTO `map_elements` VALUES (208,'eau',19849,0);
INSERT INTO `map_elements` VALUES (209,'eau',19850,0);
INSERT INTO `map_elements` VALUES (210,'eau',19851,0);
INSERT INTO `map_elements` VALUES (211,'eau',19852,0);
INSERT INTO `map_elements` VALUES (212,'eau',19853,0);
INSERT INTO `map_elements` VALUES (213,'eau',19854,0);
INSERT INTO `map_elements` VALUES (214,'eau',19855,0);
INSERT INTO `map_elements` VALUES (215,'eau',19856,0);
INSERT INTO `map_elements` VALUES (216,'eau',19857,0);
INSERT INTO `map_elements` VALUES (217,'eau',19858,0);
INSERT INTO `map_elements` VALUES (218,'feu',35035,0);
INSERT INTO `map_elements` VALUES (219,'feu',35048,0);
INSERT INTO `map_elements` VALUES (220,'feu',35050,0);
INSERT INTO `map_elements` VALUES (221,'feu',35051,0);
INSERT INTO `map_elements` VALUES (222,'feu',35052,0);
INSERT INTO `map_elements` VALUES (223,'feu',35070,0);
INSERT INTO `map_elements` VALUES (224,'feu',35071,0);
INSERT INTO `map_elements` VALUES (225,'feu',35094,0);
INSERT INTO `map_elements` VALUES (226,'feu',35096,0);
INSERT INTO `map_elements` VALUES (227,'feu',35097,0);
INSERT INTO `map_elements` VALUES (228,'lave',35036,0);
INSERT INTO `map_elements` VALUES (229,'lave',35051,0);
INSERT INTO `map_elements` VALUES (230,'lave',35052,0);
INSERT INTO `map_elements` VALUES (231,'lave',35071,0);
INSERT INTO `map_elements` VALUES (232,'lave',35072,0);
INSERT INTO `map_elements` VALUES (233,'lave',35073,0);
INSERT INTO `map_elements` VALUES (234,'lave',35094,0);
INSERT INTO `map_elements` VALUES (235,'lave',35095,0);
INSERT INTO `map_elements` VALUES (236,'lave',35097,0);
INSERT INTO `map_elements` VALUES (237,'lave',35098,0);
INSERT INTO `map_elements` VALUES (238,'lave',35115,0);
INSERT INTO `map_elements` VALUES (239,'ronce',12670,0);
INSERT INTO `map_elements` VALUES (240,'ronce',12671,0);
INSERT INTO `map_elements` VALUES (241,'ronce',12705,0);
INSERT INTO `map_elements` VALUES (242,'ronce',12707,0);
INSERT INTO `map_elements` VALUES (243,'ronce',12709,0);
INSERT INTO `map_elements` VALUES (244,'ronce',12711,0);
INSERT INTO `map_elements` VALUES (245,'ronce',12712,0);
INSERT INTO `map_elements` VALUES (246,'ronce',12713,0);
INSERT INTO `map_elements` VALUES (247,'ronce',12714,0);
INSERT INTO `map_elements` VALUES (248,'ronce',12715,0);
INSERT INTO `map_elements` VALUES (249,'ronce',12716,0);
INSERT INTO `map_elements` VALUES (250,'ronce',12717,0);
INSERT INTO `map_elements` VALUES (251,'ronce',12718,0);
INSERT INTO `map_elements` VALUES (252,'ronce',12719,0);
INSERT INTO `map_elements` VALUES (253,'ronce',12720,0);
INSERT INTO `map_elements` VALUES (254,'ronce',12721,0);
INSERT INTO `map_elements` VALUES (255,'ronce',12722,0);
INSERT INTO `map_elements` VALUES (256,'ronce',12723,0);
INSERT INTO `map_elements` VALUES (257,'ronce',12724,0);
INSERT INTO `map_elements` VALUES (258,'ronce',12725,0);
INSERT INTO `map_elements` VALUES (259,'ronce',12726,0);
INSERT INTO `map_elements` VALUES (260,'ronce',12727,0);
INSERT INTO `map_elements` VALUES (261,'ronce',12728,0);
INSERT INTO `map_elements` VALUES (262,'ronce',12729,0);
INSERT INTO `map_elements` VALUES (263,'ronce',12730,0);
INSERT INTO `map_elements` VALUES (264,'ronce',12731,0);
INSERT INTO `map_elements` VALUES (265,'ronce',12732,0);
INSERT INTO `map_elements` VALUES (266,'ronce',12733,0);
INSERT INTO `map_elements` VALUES (267,'ronce',12734,0);
INSERT INTO `map_elements` VALUES (268,'ronce',12735,0);
INSERT INTO `map_elements` VALUES (269,'ronce',12736,0);
INSERT INTO `map_elements` VALUES (270,'ronce',12737,0);
INSERT INTO `map_elements` VALUES (271,'ronce',12738,0);
INSERT INTO `map_elements` VALUES (272,'ronce',12739,0);
INSERT INTO `map_elements` VALUES (273,'ronce',12740,0);
INSERT INTO `map_elements` VALUES (274,'ronce',12741,0);
INSERT INTO `map_elements` VALUES (275,'ronce',12742,0);
INSERT INTO `map_elements` VALUES (276,'ronce',12743,0);
INSERT INTO `map_elements` VALUES (277,'ronce',12744,0);
INSERT INTO `map_elements` VALUES (278,'ronce',12745,0);
INSERT INTO `map_elements` VALUES (279,'ronce',12746,0);
INSERT INTO `map_elements` VALUES (280,'ronce',12747,0);
INSERT INTO `map_elements` VALUES (281,'ronce',12748,0);
INSERT INTO `map_elements` VALUES (282,'ronce',12749,0);
INSERT INTO `map_elements` VALUES (283,'ronce',12750,0);
INSERT INTO `map_elements` VALUES (284,'ronce',12751,0);
INSERT INTO `map_elements` VALUES (285,'ronce',12752,0);
INSERT INTO `map_elements` VALUES (286,'ronce',12753,0);
INSERT INTO `map_elements` VALUES (287,'ronce',12754,0);
INSERT INTO `map_elements` VALUES (288,'ronce',12755,0);
INSERT INTO `map_elements` VALUES (289,'ronce',12756,0);
INSERT INTO `map_elements` VALUES (290,'ronce',12757,0);
INSERT INTO `map_elements` VALUES (291,'ronce',12758,0);
INSERT INTO `map_elements` VALUES (292,'ronce',12759,0);
INSERT INTO `map_elements` VALUES (293,'ronce',12760,0);
INSERT INTO `map_elements` VALUES (294,'ronce',12761,0);
INSERT INTO `map_elements` VALUES (295,'ronce',12762,0);
INSERT INTO `map_elements` VALUES (296,'ronce',12763,0);
INSERT INTO `map_elements` VALUES (297,'ronce',12764,0);
INSERT INTO `map_elements` VALUES (298,'styx',12676,0);
INSERT INTO `map_elements` VALUES (299,'styx',12677,0);
INSERT INTO `map_elements` VALUES (300,'styx',12678,0);
INSERT INTO `map_elements` VALUES (301,'styx',12679,0);
INSERT INTO `map_elements` VALUES (302,'styx',12765,0);
INSERT INTO `map_elements` VALUES (303,'styx',12766,0);
INSERT INTO `map_elements` VALUES (304,'styx',12767,0);
INSERT INTO `map_elements` VALUES (305,'styx',12768,0);
INSERT INTO `map_elements` VALUES (306,'styx',12769,0);
INSERT INTO `map_elements` VALUES (307,'styx',12770,0);
INSERT INTO `map_elements` VALUES (308,'styx',12771,0);
INSERT INTO `map_elements` VALUES (309,'styx',12772,0);
INSERT INTO `map_elements` VALUES (310,'styx',12773,0);
INSERT INTO `map_elements` VALUES (311,'styx',12774,0);
INSERT INTO `map_elements` VALUES (312,'styx',12775,0);
INSERT INTO `map_elements` VALUES (313,'styx',12776,0);
INSERT INTO `map_elements` VALUES (314,'styx',12777,0);
INSERT INTO `map_elements` VALUES (315,'styx',12778,0);
INSERT INTO `map_elements` VALUES (316,'styx',12779,0);
INSERT INTO `map_elements` VALUES (317,'styx',12780,0);
INSERT INTO `map_elements` VALUES (318,'styx',12781,0);
INSERT INTO `map_elements` VALUES (319,'styx',12782,0);
INSERT INTO `map_elements` VALUES (320,'styx',12783,0);
INSERT INTO `map_elements` VALUES (321,'styx',12784,0);
INSERT INTO `map_elements` VALUES (322,'styx',12785,0);
INSERT INTO `map_elements` VALUES (323,'styx',12786,0);
INSERT INTO `map_elements` VALUES (324,'styx',12787,0);
INSERT INTO `map_elements` VALUES (325,'styx',12788,0);
INSERT INTO `map_elements` VALUES (326,'styx',12789,0);
INSERT INTO `map_elements` VALUES (327,'styx',12790,0);
INSERT INTO `map_elements` VALUES (328,'styx',12791,0);
INSERT INTO `map_elements` VALUES (329,'styx',12792,0);
INSERT INTO `map_elements` VALUES (330,'styx',12793,0);
INSERT INTO `map_elements` VALUES (331,'styx',12794,0);
INSERT INTO `map_elements` VALUES (332,'styx',12795,0);
INSERT INTO `map_elements` VALUES (333,'styx',12796,0);
INSERT INTO `map_elements` VALUES (334,'styx',12797,0);
INSERT INTO `map_elements` VALUES (335,'styx',12798,0);
INSERT INTO `map_elements` VALUES (336,'styx',12799,0);
INSERT INTO `map_elements` VALUES (337,'styx',12800,0);
INSERT INTO `map_elements` VALUES (338,'styx',12801,0);
INSERT INTO `map_elements` VALUES (339,'styx',12802,0);
INSERT INTO `map_elements` VALUES (340,'styx',12803,0);
INSERT INTO `map_elements` VALUES (341,'styx',12804,0);
INSERT INTO `map_elements` VALUES (342,'styx',12805,0);
INSERT INTO `map_elements` VALUES (343,'styx',12806,0);
INSERT INTO `map_elements` VALUES (344,'styx',12807,0);
INSERT INTO `map_elements` VALUES (345,'styx',12808,0);
INSERT INTO `map_elements` VALUES (346,'styx',12809,0);
INSERT INTO `map_elements` VALUES (347,'styx',12810,0);
INSERT INTO `map_elements` VALUES (348,'styx',12811,0);
INSERT INTO `map_elements` VALUES (349,'styx',12812,0);
INSERT INTO `map_elements` VALUES (350,'styx',12813,0);
INSERT INTO `map_elements` VALUES (351,'styx',12814,0);
INSERT INTO `map_elements` VALUES (352,'styx',12815,0);
INSERT INTO `map_elements` VALUES (353,'styx',12819,0);
INSERT INTO `map_elements` VALUES (354,'styx',12820,0);
INSERT INTO `map_elements` VALUES (355,'styx',12821,0);
INSERT INTO `map_elements` VALUES (356,'styx',12822,0);
INSERT INTO `map_elements` VALUES (357,'styx',12823,0);
INSERT INTO `map_elements` VALUES (358,'styx',12824,0);
INSERT INTO `map_elements` VALUES (359,'styx',12825,0);
INSERT INTO `map_elements` VALUES (360,'styx',12826,0);
INSERT INTO `map_elements` VALUES (361,'styx',12827,0);
INSERT INTO `map_elements` VALUES (362,'styx',12828,0);
INSERT INTO `map_elements` VALUES (363,'styx',12829,0);
INSERT INTO `map_elements` VALUES (364,'styx',12830,0);
INSERT INTO `map_elements` VALUES (365,'styx',12831,0);
INSERT INTO `map_elements` VALUES (366,'styx',12832,0);
INSERT INTO `map_elements` VALUES (367,'styx',12833,0);
INSERT INTO `map_elements` VALUES (368,'styx',12834,0);
INSERT INTO `map_elements` VALUES (369,'styx',12835,0);
INSERT INTO `map_elements` VALUES (370,'styx',12836,0);
INSERT INTO `map_elements` VALUES (371,'styx',12837,0);
INSERT INTO `map_elements` VALUES (372,'styx',12838,0);
INSERT INTO `map_elements` VALUES (373,'styx',12839,0);
INSERT INTO `map_elements` VALUES (374,'styx',12840,0);
INSERT INTO `map_elements` VALUES (375,'styx',12841,0);
INSERT INTO `map_elements` VALUES (376,'styx',12842,0);
INSERT INTO `map_elements` VALUES (377,'styx',12843,0);
INSERT INTO `map_elements` VALUES (378,'styx',12844,0);
INSERT INTO `map_elements` VALUES (379,'styx',12845,0);
INSERT INTO `map_elements` VALUES (380,'styx',12846,0);
INSERT INTO `map_elements` VALUES (381,'styx',12847,0);
INSERT INTO `map_elements` VALUES (382,'styx',12848,0);
INSERT INTO `map_elements` VALUES (383,'styx',12849,0);
INSERT INTO `map_elements` VALUES (384,'styx',12850,0);
INSERT INTO `map_elements` VALUES (385,'styx',12851,0);
INSERT INTO `map_elements` VALUES (386,'styx',12852,0);
INSERT INTO `map_elements` VALUES (387,'styx',12853,0);
INSERT INTO `map_elements` VALUES (388,'styx',12854,0);
INSERT INTO `map_elements` VALUES (389,'styx',12855,0);
INSERT INTO `map_elements` VALUES (390,'styx',12856,0);
INSERT INTO `map_elements` VALUES (391,'styx',12857,0);
INSERT INTO `map_elements` VALUES (392,'styx',12858,0);
INSERT INTO `map_elements` VALUES (393,'styx',12859,0);
INSERT INTO `map_elements` VALUES (394,'styx',12860,0);
INSERT INTO `map_elements` VALUES (395,'styx',12861,0);
INSERT INTO `map_elements` VALUES (396,'styx',12862,0);
INSERT INTO `map_elements` VALUES (397,'styx',12863,0);
INSERT INTO `map_elements` VALUES (398,'styx',12864,0);
INSERT INTO `map_elements` VALUES (399,'styx',12865,0);
INSERT INTO `map_elements` VALUES (400,'styx',12866,0);
INSERT INTO `map_elements` VALUES (401,'styx',12867,0);
INSERT INTO `map_elements` VALUES (402,'styx',12868,0);
INSERT INTO `map_elements` VALUES (403,'styx',12869,0);
INSERT INTO `map_elements` VALUES (404,'styx',12870,0);
INSERT INTO `map_elements` VALUES (405,'styx',12871,0);
INSERT INTO `map_elements` VALUES (406,'styx',12872,0);
INSERT INTO `map_elements` VALUES (407,'styx',12873,0);
INSERT INTO `map_elements` VALUES (408,'styx',12874,0);
INSERT INTO `map_elements` VALUES (409,'styx',12875,0);
INSERT INTO `map_elements` VALUES (410,'styx',12876,0);
INSERT INTO `map_elements` VALUES (411,'styx',12877,0);
INSERT INTO `map_elements` VALUES (412,'styx',12878,0);
INSERT INTO `map_elements` VALUES (413,'styx',12879,0);
INSERT INTO `map_elements` VALUES (414,'styx',12880,0);
INSERT INTO `map_elements` VALUES (415,'styx',12881,0);
INSERT INTO `map_elements` VALUES (416,'styx',12882,0);
INSERT INTO `map_elements` VALUES (417,'styx',12883,0);
INSERT INTO `map_elements` VALUES (418,'styx',12884,0);
INSERT INTO `map_elements` VALUES (419,'styx',12885,0);
INSERT INTO `map_elements` VALUES (420,'styx',12886,0);
INSERT INTO `map_elements` VALUES (421,'styx',12887,0);
INSERT INTO `map_elements` VALUES (422,'styx',12888,0);
INSERT INTO `map_elements` VALUES (423,'styx',12889,0);
INSERT INTO `map_elements` VALUES (424,'styx',12890,0);
INSERT INTO `map_elements` VALUES (425,'styx',12891,0);
INSERT INTO `map_elements` VALUES (426,'styx',12892,0);
INSERT INTO `map_elements` VALUES (427,'styx',12893,0);
INSERT INTO `map_elements` VALUES (428,'styx',12894,0);
INSERT INTO `map_elements` VALUES (429,'styx',12895,0);
INSERT INTO `map_elements` VALUES (430,'styx',12896,0);
INSERT INTO `map_elements` VALUES (431,'styx',12897,0);
INSERT INTO `map_elements` VALUES (432,'styx',12898,0);
INSERT INTO `map_elements` VALUES (433,'styx',12899,0);
INSERT INTO `map_elements` VALUES (434,'styx',12900,0);
INSERT INTO `map_elements` VALUES (435,'styx',12901,0);
INSERT INTO `map_elements` VALUES (436,'styx',12902,0);
INSERT INTO `map_elements` VALUES (437,'styx',12903,0);
INSERT INTO `map_elements` VALUES (438,'styx',12904,0);
INSERT INTO `map_elements` VALUES (439,'styx',12905,0);
INSERT INTO `map_elements` VALUES (440,'styx',12906,0);
INSERT INTO `map_elements` VALUES (441,'styx',12907,0);
INSERT INTO `map_elements` VALUES (442,'styx',12908,0);
INSERT INTO `map_elements` VALUES (443,'styx',12909,0);
INSERT INTO `map_elements` VALUES (444,'styx',12910,0);
INSERT INTO `map_elements` VALUES (445,'styx',12911,0);
INSERT INTO `map_elements` VALUES (446,'styx',12912,0);
INSERT INTO `map_elements` VALUES (447,'styx',12913,0);
INSERT INTO `map_elements` VALUES (448,'styx',12914,0);
INSERT INTO `map_elements` VALUES (449,'styx',12915,0);
INSERT INTO `map_elements` VALUES (450,'styx',12916,0);
INSERT INTO `map_elements` VALUES (451,'styx',12917,0);
INSERT INTO `map_elements` VALUES (452,'styx',12918,0);
INSERT INTO `map_elements` VALUES (453,'styx',12919,0);
INSERT INTO `map_elements` VALUES (454,'styx',12920,0);
INSERT INTO `map_elements` VALUES (455,'styx',12921,0);
INSERT INTO `map_elements` VALUES (456,'styx',12922,0);
INSERT INTO `map_elements` VALUES (457,'styx',12923,0);
INSERT INTO `map_elements` VALUES (458,'styx',12924,0);
INSERT INTO `map_elements` VALUES (459,'styx',12925,0);
INSERT INTO `map_elements` VALUES (460,'styx',12926,0);
INSERT INTO `map_elements` VALUES (461,'styx',12927,0);
INSERT INTO `map_elements` VALUES (462,'styx',12928,0);
INSERT INTO `map_elements` VALUES (463,'styx',12929,0);
INSERT INTO `map_elements` VALUES (464,'styx',12930,0);
INSERT INTO `map_elements` VALUES (465,'styx',35018,0);
INSERT INTO `map_elements` VALUES (466,'styx',35028,0);
INSERT INTO `map_elements` VALUES (467,'styx',35043,0);
INSERT INTO `map_elements` VALUES (468,'styx',35061,0);
INSERT INTO `map_elements` VALUES (469,'styx',35077,0);
INSERT INTO `map_elements` VALUES (470,'styx',35078,0);
INSERT INTO `map_elements` VALUES (471,'styx',35083,0);
INSERT INTO `map_elements` VALUES (472,'styx',35084,0);
INSERT INTO `map_elements` VALUES (473,'styx',35102,0);
INSERT INTO `map_elements` VALUES (474,'styx',35103,0);
INSERT INTO `map_elements` VALUES (475,'styx',35111,0);
INSERT INTO `map_elements` VALUES (476,'styx',35113,0);
/*!40000 ALTER TABLE `map_elements` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `map_foregrounds` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `coords_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `coords_id` (`coords_id`),
  CONSTRAINT `map_foregrounds_ibfk_3` FOREIGN KEY (`coords_id`) REFERENCES `coords` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `map_foregrounds` DISABLE KEYS */;
INSERT INTO `map_foregrounds` VALUES (1,97,'olympia-00');
INSERT INTO `map_foregrounds` VALUES (2,98,'olympia-01');
INSERT INTO `map_foregrounds` VALUES (3,99,'olympia-02');
INSERT INTO `map_foregrounds` VALUES (4,100,'olympia-03');
INSERT INTO `map_foregrounds` VALUES (5,160,'olympia-00');
INSERT INTO `map_foregrounds` VALUES (6,167,'olympia-01');
INSERT INTO `map_foregrounds` VALUES (7,162,'olympia-02');
INSERT INTO `map_foregrounds` VALUES (8,164,'olympia-03');
INSERT INTO `map_foregrounds` VALUES (9,12697,'porte_des_enfers-00');
INSERT INTO `map_foregrounds` VALUES (10,12698,'porte_des_enfers-01');
INSERT INTO `map_foregrounds` VALUES (11,12680,'porte_des_enfers-02');
INSERT INTO `map_foregrounds` VALUES (12,12681,'porte_des_enfers-03');
INSERT INTO `map_foregrounds` VALUES (13,12704,'gardien_stellaire-00');
INSERT INTO `map_foregrounds` VALUES (14,12705,'gardien_stellaire-01');
INSERT INTO `map_foregrounds` VALUES (15,12706,'gardien_stellaire-00');
INSERT INTO `map_foregrounds` VALUES (16,12707,'gardien_stellaire-01');
INSERT INTO `map_foregrounds` VALUES (17,12708,'gardien_stellaire-00');
INSERT INTO `map_foregrounds` VALUES (18,12709,'gardien_stellaire-01');
INSERT INTO `map_foregrounds` VALUES (19,12710,'gardien_stellaire-00');
INSERT INTO `map_foregrounds` VALUES (20,12711,'gardien_stellaire-01');
INSERT INTO `map_foregrounds` VALUES (21,12826,'asteroide-03');
INSERT INTO `map_foregrounds` VALUES (22,12827,'asteroide-04');
INSERT INTO `map_foregrounds` VALUES (23,12828,'asteroide-05');
INSERT INTO `map_foregrounds` VALUES (24,12816,'asteroide-11');
INSERT INTO `map_foregrounds` VALUES (25,12817,'asteroide-12');
INSERT INTO `map_foregrounds` VALUES (26,12818,'asteroide-13');
INSERT INTO `map_foregrounds` VALUES (27,27885,'triton_statue-00');
INSERT INTO `map_foregrounds` VALUES (28,27886,'triton_statue-01');
INSERT INTO `map_foregrounds` VALUES (29,27887,'triton_statue-02');
INSERT INTO `map_foregrounds` VALUES (30,27888,'triton_statue-04');
INSERT INTO `map_foregrounds` VALUES (31,27889,'triton_statue-05');
INSERT INTO `map_foregrounds` VALUES (32,17110,'marchand');
INSERT INTO `map_foregrounds` VALUES (33,16998,'echelle_haut');
INSERT INTO `map_foregrounds` VALUES (34,17001,'tonneau');
INSERT INTO `map_foregrounds` VALUES (35,17002,'tonneau');
INSERT INTO `map_foregrounds` VALUES (36,17003,'marchand');
INSERT INTO `map_foregrounds` VALUES (37,17206,'tonneau');
/*!40000 ALTER TABLE `map_foregrounds` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `map_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `coords_id` int(11) NOT NULL,
  `n` int(11) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `item_id` (`item_id`),
  KEY `coords_id` (`coords_id`),
  CONSTRAINT `map_items_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`),
  CONSTRAINT `map_items_ibfk_2` FOREIGN KEY (`coords_id`) REFERENCES `coords` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `map_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `map_items` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `map_plants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `coords_id` int(11) NOT NULL,
  `params` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `coords_id` (`coords_id`)
) ENGINE=InnoDB AUTO_INCREMENT=60 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `map_plants` DISABLE KEYS */;
INSERT INTO `map_plants` VALUES (1,'adonis',17229,NULL);
INSERT INTO `map_plants` VALUES (2,'adonis',17230,NULL);
INSERT INTO `map_plants` VALUES (3,'adonis',17231,NULL);
INSERT INTO `map_plants` VALUES (4,'adonis',17232,NULL);
INSERT INTO `map_plants` VALUES (5,'adonis',17233,NULL);
INSERT INTO `map_plants` VALUES (6,'adonis',17234,NULL);
INSERT INTO `map_plants` VALUES (7,'adonis',17235,NULL);
INSERT INTO `map_plants` VALUES (8,'adonis',17236,NULL);
INSERT INTO `map_plants` VALUES (9,'adonis',17237,NULL);
INSERT INTO `map_plants` VALUES (10,'adonis',17225,NULL);
INSERT INTO `map_plants` VALUES (11,'adonis',17224,NULL);
INSERT INTO `map_plants` VALUES (12,'adonis',17223,NULL);
INSERT INTO `map_plants` VALUES (13,'cafe',17269,NULL);
INSERT INTO `map_plants` VALUES (14,'cafe',17270,NULL);
INSERT INTO `map_plants` VALUES (15,'cafe',17271,NULL);
INSERT INTO `map_plants` VALUES (16,'cafe',17272,NULL);
INSERT INTO `map_plants` VALUES (17,'cafe',17282,NULL);
INSERT INTO `map_plants` VALUES (18,'cafe',17291,NULL);
INSERT INTO `map_plants` VALUES (19,'cafe',17292,NULL);
INSERT INTO `map_plants` VALUES (20,'cafe',17293,NULL);
INSERT INTO `map_plants` VALUES (21,'cafe',17294,NULL);
INSERT INTO `map_plants` VALUES (22,'astral',17283,NULL);
INSERT INTO `map_plants` VALUES (23,'astral',17284,NULL);
INSERT INTO `map_plants` VALUES (24,'astral',17273,NULL);
INSERT INTO `map_plants` VALUES (25,'astral',17274,NULL);
INSERT INTO `map_plants` VALUES (26,'astral',17330,NULL);
INSERT INTO `map_plants` VALUES (27,'astral',17331,NULL);
INSERT INTO `map_plants` VALUES (28,'lotus_noir',17242,NULL);
INSERT INTO `map_plants` VALUES (29,'lotus_noir',17241,NULL);
INSERT INTO `map_plants` VALUES (30,'lotus_noir',17227,NULL);
INSERT INTO `map_plants` VALUES (31,'lotus_noir',17226,NULL);
INSERT INTO `map_plants` VALUES (32,'menthe',17280,NULL);
INSERT INTO `map_plants` VALUES (33,'menthe',17279,NULL);
INSERT INTO `map_plants` VALUES (34,'menthe',17278,NULL);
INSERT INTO `map_plants` VALUES (35,'menthe',17263,NULL);
INSERT INTO `map_plants` VALUES (36,'menthe',17264,NULL);
INSERT INTO `map_plants` VALUES (37,'menthe',17265,NULL);
INSERT INTO `map_plants` VALUES (38,'pavot',17290,NULL);
INSERT INTO `map_plants` VALUES (39,'pavot',17332,NULL);
INSERT INTO `map_plants` VALUES (40,'pavot',17335,NULL);
INSERT INTO `map_plants` VALUES (41,'pavot',17334,NULL);
INSERT INTO `map_plants` VALUES (42,'pavot',17333,NULL);
INSERT INTO `map_plants` VALUES (43,'pavot',17266,NULL);
INSERT INTO `map_plants` VALUES (44,'pavot',17267,NULL);
INSERT INTO `map_plants` VALUES (45,'pavot',17296,NULL);
INSERT INTO `map_plants` VALUES (46,'pavot',17297,NULL);
INSERT INTO `map_plants` VALUES (47,'pavot',17368,NULL);
INSERT INTO `map_plants` VALUES (48,'lichen_sacre',17362,NULL);
INSERT INTO `map_plants` VALUES (49,'lichen_sacre',17361,NULL);
INSERT INTO `map_plants` VALUES (50,'lichen_sacre',17358,NULL);
INSERT INTO `map_plants` VALUES (51,'lichen_sacre',17357,NULL);
INSERT INTO `map_plants` VALUES (52,'lichen_sacre',17354,NULL);
INSERT INTO `map_plants` VALUES (53,'lichen_sacre',17353,NULL);
INSERT INTO `map_plants` VALUES (54,'lichen_sacre',17345,NULL);
INSERT INTO `map_plants` VALUES (55,'lichen_sacre',17344,NULL);
INSERT INTO `map_plants` VALUES (56,'lichen_sacre',17343,NULL);
INSERT INTO `map_plants` VALUES (57,'lichen_sacre',17342,NULL);
INSERT INTO `map_plants` VALUES (58,'lichen_sacre',17341,NULL);
INSERT INTO `map_plants` VALUES (59,'lichen_sacre',17340,NULL);
/*!40000 ALTER TABLE `map_plants` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `map_routes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `coords_id` int(11) DEFAULT NULL,
  `player_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `players_map_routes_fk_1` (`player_id`),
  KEY `coords_map_routes_fk_2` (`coords_id`),
  CONSTRAINT `coords_map_routes_fk_2` FOREIGN KEY (`coords_id`) REFERENCES `coords` (`id`),
  CONSTRAINT `players_map_routes_fk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `map_routes` DISABLE KEYS */;
/*!40000 ALTER TABLE `map_routes` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `map_tiles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `coords_id` int(11) NOT NULL,
  `foreground` int(11) NOT NULL DEFAULT 0,
  `player_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `coords_id` (`coords_id`),
  KEY `player_id` (`player_id`),
  CONSTRAINT `map_tiles_ibfk_1` FOREIGN KEY (`coords_id`) REFERENCES `coords` (`id`),
  CONSTRAINT `map_tiles_ibfk_2` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1041 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `map_tiles` DISABLE KEYS */;
INSERT INTO `map_tiles` VALUES (1,'carreaux',1,0,NULL);
INSERT INTO `map_tiles` VALUES (2,'carreaux',2,0,NULL);
INSERT INTO `map_tiles` VALUES (3,'carreaux',3,0,NULL);
INSERT INTO `map_tiles` VALUES (4,'carreaux',4,0,NULL);
INSERT INTO `map_tiles` VALUES (5,'carreaux',5,0,NULL);
INSERT INTO `map_tiles` VALUES (6,'carreaux',6,0,NULL);
INSERT INTO `map_tiles` VALUES (7,'carreaux',7,0,NULL);
INSERT INTO `map_tiles` VALUES (8,'carreaux',8,0,NULL);
INSERT INTO `map_tiles` VALUES (9,'carreaux',9,0,NULL);
INSERT INTO `map_tiles` VALUES (10,'carreaux',10,0,NULL);
INSERT INTO `map_tiles` VALUES (11,'carreaux',11,0,NULL);
INSERT INTO `map_tiles` VALUES (12,'carreaux',12,0,NULL);
INSERT INTO `map_tiles` VALUES (13,'carreaux',13,0,NULL);
INSERT INTO `map_tiles` VALUES (14,'carreaux',14,0,NULL);
INSERT INTO `map_tiles` VALUES (15,'carreaux',15,0,NULL);
INSERT INTO `map_tiles` VALUES (16,'carreaux',16,0,NULL);
INSERT INTO `map_tiles` VALUES (17,'carreaux',17,0,NULL);
INSERT INTO `map_tiles` VALUES (18,'carreaux',18,0,NULL);
INSERT INTO `map_tiles` VALUES (19,'carreaux',19,0,NULL);
INSERT INTO `map_tiles` VALUES (20,'carreaux',20,0,NULL);
INSERT INTO `map_tiles` VALUES (21,'carreaux',21,0,NULL);
INSERT INTO `map_tiles` VALUES (22,'carreaux',22,0,NULL);
INSERT INTO `map_tiles` VALUES (23,'carreaux',23,0,NULL);
INSERT INTO `map_tiles` VALUES (24,'falaise',24,0,NULL);
INSERT INTO `map_tiles` VALUES (25,'falaise',25,0,NULL);
INSERT INTO `map_tiles` VALUES (26,'falaise',26,0,NULL);
INSERT INTO `map_tiles` VALUES (27,'falaise',27,0,NULL);
INSERT INTO `map_tiles` VALUES (28,'falaise',28,0,NULL);
INSERT INTO `map_tiles` VALUES (29,'carreaux',29,0,NULL);
INSERT INTO `map_tiles` VALUES (30,'carreaux',30,0,NULL);
INSERT INTO `map_tiles` VALUES (31,'carreaux',31,0,NULL);
INSERT INTO `map_tiles` VALUES (32,'carreaux',32,0,NULL);
INSERT INTO `map_tiles` VALUES (33,'carreaux',33,0,NULL);
INSERT INTO `map_tiles` VALUES (34,'falaise',34,0,NULL);
INSERT INTO `map_tiles` VALUES (35,'falaise',35,0,NULL);
INSERT INTO `map_tiles` VALUES (36,'carreaux',36,0,NULL);
INSERT INTO `map_tiles` VALUES (37,'carreaux',37,0,NULL);
INSERT INTO `map_tiles` VALUES (38,'carreaux',38,0,NULL);
INSERT INTO `map_tiles` VALUES (39,'carreaux',39,0,NULL);
INSERT INTO `map_tiles` VALUES (40,'carreaux',40,0,NULL);
INSERT INTO `map_tiles` VALUES (41,'carreaux',41,0,NULL);
INSERT INTO `map_tiles` VALUES (42,'carreaux',42,0,NULL);
INSERT INTO `map_tiles` VALUES (43,'carreaux',43,0,NULL);
INSERT INTO `map_tiles` VALUES (44,'carreaux',44,0,NULL);
INSERT INTO `map_tiles` VALUES (45,'falaise',45,0,NULL);
INSERT INTO `map_tiles` VALUES (46,'falaise',46,0,NULL);
INSERT INTO `map_tiles` VALUES (47,'falaise',47,0,NULL);
INSERT INTO `map_tiles` VALUES (48,'falaise',48,0,NULL);
INSERT INTO `map_tiles` VALUES (49,'falaise',49,0,NULL);
INSERT INTO `map_tiles` VALUES (50,'falaise',50,0,NULL);
INSERT INTO `map_tiles` VALUES (51,'falaise',51,0,NULL);
INSERT INTO `map_tiles` VALUES (52,'falaise',52,0,NULL);
INSERT INTO `map_tiles` VALUES (53,'carreaux',53,0,NULL);
INSERT INTO `map_tiles` VALUES (54,'carreaux',54,0,NULL);
INSERT INTO `map_tiles` VALUES (55,'falaise',55,0,NULL);
INSERT INTO `map_tiles` VALUES (56,'falaise',56,0,NULL);
INSERT INTO `map_tiles` VALUES (57,'carreaux',57,0,NULL);
INSERT INTO `map_tiles` VALUES (58,'falaise',58,0,NULL);
INSERT INTO `map_tiles` VALUES (59,'carreaux',59,0,NULL);
INSERT INTO `map_tiles` VALUES (60,'carreaux',101,0,NULL);
INSERT INTO `map_tiles` VALUES (61,'carreaux',102,0,NULL);
INSERT INTO `map_tiles` VALUES (62,'carreaux',103,0,NULL);
INSERT INTO `map_tiles` VALUES (63,'carreaux',104,0,NULL);
INSERT INTO `map_tiles` VALUES (64,'carreaux',105,0,NULL);
INSERT INTO `map_tiles` VALUES (65,'carreaux',106,0,NULL);
INSERT INTO `map_tiles` VALUES (66,'carreaux',107,0,NULL);
INSERT INTO `map_tiles` VALUES (67,'carreaux',108,0,NULL);
INSERT INTO `map_tiles` VALUES (68,'carreaux',109,0,NULL);
INSERT INTO `map_tiles` VALUES (69,'carreaux',110,0,NULL);
INSERT INTO `map_tiles` VALUES (70,'carreaux',111,0,NULL);
INSERT INTO `map_tiles` VALUES (71,'carreaux',112,0,NULL);
INSERT INTO `map_tiles` VALUES (72,'carreaux',113,0,NULL);
INSERT INTO `map_tiles` VALUES (73,'carreaux',114,0,NULL);
INSERT INTO `map_tiles` VALUES (74,'carreaux',115,0,NULL);
INSERT INTO `map_tiles` VALUES (75,'carreaux',116,0,NULL);
INSERT INTO `map_tiles` VALUES (76,'carreaux',117,0,NULL);
INSERT INTO `map_tiles` VALUES (77,'carreaux',118,0,NULL);
INSERT INTO `map_tiles` VALUES (78,'carreaux',119,0,NULL);
INSERT INTO `map_tiles` VALUES (79,'carreaux',120,0,NULL);
INSERT INTO `map_tiles` VALUES (80,'carreaux',121,0,NULL);
INSERT INTO `map_tiles` VALUES (81,'carreaux',122,0,NULL);
INSERT INTO `map_tiles` VALUES (82,'carreaux',123,0,NULL);
INSERT INTO `map_tiles` VALUES (83,'carreaux',124,0,NULL);
INSERT INTO `map_tiles` VALUES (84,'falaise',125,0,NULL);
INSERT INTO `map_tiles` VALUES (85,'falaise',126,0,NULL);
INSERT INTO `map_tiles` VALUES (86,'falaise',127,0,NULL);
INSERT INTO `map_tiles` VALUES (87,'falaise',128,0,NULL);
INSERT INTO `map_tiles` VALUES (88,'falaise',129,0,NULL);
INSERT INTO `map_tiles` VALUES (89,'carreaux',130,0,NULL);
INSERT INTO `map_tiles` VALUES (90,'carreaux',131,0,NULL);
INSERT INTO `map_tiles` VALUES (91,'carreaux',132,0,NULL);
INSERT INTO `map_tiles` VALUES (92,'carreaux',133,0,NULL);
INSERT INTO `map_tiles` VALUES (93,'carreaux',134,0,NULL);
INSERT INTO `map_tiles` VALUES (94,'falaise',135,0,NULL);
INSERT INTO `map_tiles` VALUES (95,'falaise',136,0,NULL);
INSERT INTO `map_tiles` VALUES (96,'carreaux',137,0,NULL);
INSERT INTO `map_tiles` VALUES (97,'carreaux',138,0,NULL);
INSERT INTO `map_tiles` VALUES (98,'carreaux',139,0,NULL);
INSERT INTO `map_tiles` VALUES (99,'carreaux',140,0,NULL);
INSERT INTO `map_tiles` VALUES (100,'carreaux',141,0,NULL);
INSERT INTO `map_tiles` VALUES (101,'carreaux',142,0,NULL);
INSERT INTO `map_tiles` VALUES (102,'carreaux',143,0,NULL);
INSERT INTO `map_tiles` VALUES (103,'carreaux',144,0,NULL);
INSERT INTO `map_tiles` VALUES (104,'carreaux',145,0,NULL);
INSERT INTO `map_tiles` VALUES (105,'falaise',146,0,NULL);
INSERT INTO `map_tiles` VALUES (106,'falaise',147,0,NULL);
INSERT INTO `map_tiles` VALUES (107,'falaise',148,0,NULL);
INSERT INTO `map_tiles` VALUES (108,'falaise',149,0,NULL);
INSERT INTO `map_tiles` VALUES (109,'falaise',150,0,NULL);
INSERT INTO `map_tiles` VALUES (110,'falaise',151,0,NULL);
INSERT INTO `map_tiles` VALUES (111,'falaise',152,0,NULL);
INSERT INTO `map_tiles` VALUES (112,'falaise',153,0,NULL);
INSERT INTO `map_tiles` VALUES (113,'carreaux',154,0,NULL);
INSERT INTO `map_tiles` VALUES (114,'carreaux',155,0,NULL);
INSERT INTO `map_tiles` VALUES (115,'carreaux',156,0,NULL);
INSERT INTO `map_tiles` VALUES (116,'falaise',157,0,NULL);
INSERT INTO `map_tiles` VALUES (117,'falaise',158,0,NULL);
INSERT INTO `map_tiles` VALUES (118,'falaise',159,0,NULL);
INSERT INTO `map_tiles` VALUES (119,'escalier_vers_le_bas',12671,0,NULL);
INSERT INTO `map_tiles` VALUES (120,'route',12672,0,NULL);
INSERT INTO `map_tiles` VALUES (121,'route',12673,0,NULL);
INSERT INTO `map_tiles` VALUES (122,'route',12674,0,NULL);
INSERT INTO `map_tiles` VALUES (123,'route',12675,0,NULL);
INSERT INTO `map_tiles` VALUES (124,'pit',12676,0,NULL);
INSERT INTO `map_tiles` VALUES (125,'pit',12677,0,NULL);
INSERT INTO `map_tiles` VALUES (126,'pit',12678,0,NULL);
INSERT INTO `map_tiles` VALUES (127,'pit',12679,0,NULL);
INSERT INTO `map_tiles` VALUES (128,'route',12680,0,NULL);
INSERT INTO `map_tiles` VALUES (129,'route',12681,0,NULL);
INSERT INTO `map_tiles` VALUES (130,'route',12682,0,NULL);
INSERT INTO `map_tiles` VALUES (131,'route',12683,0,NULL);
INSERT INTO `map_tiles` VALUES (132,'route',12684,0,NULL);
INSERT INTO `map_tiles` VALUES (133,'route',12685,0,NULL);
INSERT INTO `map_tiles` VALUES (134,'route',12686,0,NULL);
INSERT INTO `map_tiles` VALUES (135,'route',12687,0,NULL);
INSERT INTO `map_tiles` VALUES (136,'route',12688,0,NULL);
INSERT INTO `map_tiles` VALUES (137,'route',12689,0,NULL);
INSERT INTO `map_tiles` VALUES (138,'route',12690,0,NULL);
INSERT INTO `map_tiles` VALUES (139,'route',12691,0,NULL);
INSERT INTO `map_tiles` VALUES (140,'route',12692,0,NULL);
INSERT INTO `map_tiles` VALUES (141,'route',12693,0,NULL);
INSERT INTO `map_tiles` VALUES (142,'route',12694,0,NULL);
INSERT INTO `map_tiles` VALUES (143,'route',12695,0,NULL);
INSERT INTO `map_tiles` VALUES (144,'route',12696,0,NULL);
INSERT INTO `map_tiles` VALUES (145,'route',12697,0,NULL);
INSERT INTO `map_tiles` VALUES (146,'route',12698,0,NULL);
INSERT INTO `map_tiles` VALUES (147,'route',12699,0,NULL);
INSERT INTO `map_tiles` VALUES (148,'route',12700,0,NULL);
INSERT INTO `map_tiles` VALUES (149,'route',12701,0,NULL);
INSERT INTO `map_tiles` VALUES (150,'route',12702,0,NULL);
INSERT INTO `map_tiles` VALUES (151,'route',12703,0,NULL);
INSERT INTO `map_tiles` VALUES (152,'carreaux',15463,0,NULL);
INSERT INTO `map_tiles` VALUES (153,'carreaux',15472,0,NULL);
INSERT INTO `map_tiles` VALUES (154,'carreaux',15457,0,NULL);
INSERT INTO `map_tiles` VALUES (155,'carreaux',15469,0,NULL);
INSERT INTO `map_tiles` VALUES (156,'carreaux',15470,0,NULL);
INSERT INTO `map_tiles` VALUES (157,'desert_de_l_egeon',15519,0,NULL);
INSERT INTO `map_tiles` VALUES (158,'desert_de_l_egeon',15520,0,NULL);
INSERT INTO `map_tiles` VALUES (159,'desert_de_l_egeon',15521,0,NULL);
INSERT INTO `map_tiles` VALUES (160,'desert_de_l_egeon',15522,0,NULL);
INSERT INTO `map_tiles` VALUES (161,'desert_de_l_egeon',15523,0,NULL);
INSERT INTO `map_tiles` VALUES (162,'desert_de_l_egeon',15524,0,NULL);
INSERT INTO `map_tiles` VALUES (163,'desert_de_l_egeon',15525,0,NULL);
INSERT INTO `map_tiles` VALUES (164,'desert_de_l_egeon',15526,0,NULL);
INSERT INTO `map_tiles` VALUES (165,'desert_de_l_egeon',15527,0,NULL);
INSERT INTO `map_tiles` VALUES (166,'desert_de_l_egeon',15528,0,NULL);
INSERT INTO `map_tiles` VALUES (167,'desert_de_l_egeon',15529,0,NULL);
INSERT INTO `map_tiles` VALUES (168,'desert_de_l_egeon',15530,0,NULL);
INSERT INTO `map_tiles` VALUES (169,'desert_de_l_egeon',15531,0,NULL);
INSERT INTO `map_tiles` VALUES (170,'desert_de_l_egeon',15532,0,NULL);
INSERT INTO `map_tiles` VALUES (171,'desert_de_l_egeon',15533,0,NULL);
INSERT INTO `map_tiles` VALUES (172,'desert_de_l_egeon',15534,0,NULL);
INSERT INTO `map_tiles` VALUES (173,'desert_de_l_egeon',15535,0,NULL);
INSERT INTO `map_tiles` VALUES (174,'desert_de_l_egeon',15536,0,NULL);
INSERT INTO `map_tiles` VALUES (175,'desert_de_l_egeon',15537,0,NULL);
INSERT INTO `map_tiles` VALUES (176,'desert_de_l_egeon',15538,0,NULL);
INSERT INTO `map_tiles` VALUES (177,'desert_de_l_egeon',15539,0,NULL);
INSERT INTO `map_tiles` VALUES (178,'desert_de_l_egeon',15540,0,NULL);
INSERT INTO `map_tiles` VALUES (179,'desert_de_l_egeon',15541,0,NULL);
INSERT INTO `map_tiles` VALUES (180,'desert_de_l_egeon',15542,0,NULL);
INSERT INTO `map_tiles` VALUES (181,'desert_de_l_egeon',15543,0,NULL);
INSERT INTO `map_tiles` VALUES (182,'desert_de_l_egeon',15544,0,NULL);
INSERT INTO `map_tiles` VALUES (183,'desert_de_l_egeon',15545,0,NULL);
INSERT INTO `map_tiles` VALUES (184,'desert_de_l_egeon',15546,0,NULL);
INSERT INTO `map_tiles` VALUES (185,'desert_de_l_egeon',15547,0,NULL);
INSERT INTO `map_tiles` VALUES (186,'desert_de_l_egeon',15548,0,NULL);
INSERT INTO `map_tiles` VALUES (187,'desert_de_l_egeon',15549,0,NULL);
INSERT INTO `map_tiles` VALUES (188,'desert_de_l_egeon',15550,0,NULL);
INSERT INTO `map_tiles` VALUES (189,'desert_de_l_egeon',15551,0,NULL);
INSERT INTO `map_tiles` VALUES (190,'desert_de_l_egeon',15552,0,NULL);
INSERT INTO `map_tiles` VALUES (191,'desert_de_l_egeon',15553,0,NULL);
INSERT INTO `map_tiles` VALUES (192,'desert_de_l_egeon',15554,0,NULL);
INSERT INTO `map_tiles` VALUES (193,'desert_de_l_egeon',15555,0,NULL);
INSERT INTO `map_tiles` VALUES (194,'desert_de_l_egeon',15556,0,NULL);
INSERT INTO `map_tiles` VALUES (195,'desert_de_l_egeon',15557,0,NULL);
INSERT INTO `map_tiles` VALUES (196,'desert_de_l_egeon',15558,0,NULL);
INSERT INTO `map_tiles` VALUES (197,'desert_de_l_egeon',15559,0,NULL);
INSERT INTO `map_tiles` VALUES (198,'desert_de_l_egeon',15560,0,NULL);
INSERT INTO `map_tiles` VALUES (199,'desert_de_l_egeon',15561,0,NULL);
INSERT INTO `map_tiles` VALUES (200,'desert_de_l_egeon',15562,0,NULL);
INSERT INTO `map_tiles` VALUES (201,'desert_de_l_egeon',16595,0,NULL);
INSERT INTO `map_tiles` VALUES (202,'desert_de_l_egeon',16598,0,NULL);
INSERT INTO `map_tiles` VALUES (203,'desert_de_l_egeon',16601,0,NULL);
INSERT INTO `map_tiles` VALUES (204,'desert_de_l_egeon',16602,0,NULL);
INSERT INTO `map_tiles` VALUES (205,'desert_de_l_egeon',16603,0,NULL);
INSERT INTO `map_tiles` VALUES (206,'desert_de_l_egeon',16604,0,NULL);
INSERT INTO `map_tiles` VALUES (207,'desert_de_l_egeon',16605,0,NULL);
INSERT INTO `map_tiles` VALUES (208,'desert_de_l_egeon',16606,0,NULL);
INSERT INTO `map_tiles` VALUES (209,'desert_de_l_egeon',16607,0,NULL);
INSERT INTO `map_tiles` VALUES (210,'desert_de_l_egeon',16608,0,NULL);
INSERT INTO `map_tiles` VALUES (211,'desert_de_l_egeon',16609,0,NULL);
INSERT INTO `map_tiles` VALUES (212,'desert_de_l_egeon',16610,0,NULL);
INSERT INTO `map_tiles` VALUES (213,'desert_de_l_egeon',16611,0,NULL);
INSERT INTO `map_tiles` VALUES (214,'desert_de_l_egeon',16612,0,NULL);
INSERT INTO `map_tiles` VALUES (215,'desert_de_l_egeon',16613,0,NULL);
INSERT INTO `map_tiles` VALUES (216,'desert_de_l_egeon',16614,0,NULL);
INSERT INTO `map_tiles` VALUES (217,'desert_de_l_egeon',16615,0,NULL);
INSERT INTO `map_tiles` VALUES (218,'desert_de_l_egeon',16616,0,NULL);
INSERT INTO `map_tiles` VALUES (219,'desert_de_l_egeon',16617,0,NULL);
INSERT INTO `map_tiles` VALUES (220,'desert_de_l_egeon',16618,0,NULL);
INSERT INTO `map_tiles` VALUES (221,'desert_de_l_egeon',16619,0,NULL);
INSERT INTO `map_tiles` VALUES (222,'desert_de_l_egeon',16620,0,NULL);
INSERT INTO `map_tiles` VALUES (223,'desert_de_l_egeon',16621,0,NULL);
INSERT INTO `map_tiles` VALUES (224,'desert_de_l_egeon',16622,0,NULL);
INSERT INTO `map_tiles` VALUES (225,'desert_de_l_egeon',16623,0,NULL);
INSERT INTO `map_tiles` VALUES (226,'desert_de_l_egeon',16624,0,NULL);
INSERT INTO `map_tiles` VALUES (227,'desert_de_l_egeon',16625,0,NULL);
INSERT INTO `map_tiles` VALUES (228,'desert_de_l_egeon',16626,0,NULL);
INSERT INTO `map_tiles` VALUES (229,'desert_de_l_egeon',16627,0,NULL);
INSERT INTO `map_tiles` VALUES (230,'desert_de_l_egeon',16628,0,NULL);
INSERT INTO `map_tiles` VALUES (231,'desert_de_l_egeon',16629,0,NULL);
INSERT INTO `map_tiles` VALUES (232,'desert_de_l_egeon',16630,0,NULL);
INSERT INTO `map_tiles` VALUES (233,'desert_de_l_egeon',16631,0,NULL);
INSERT INTO `map_tiles` VALUES (234,'desert_de_l_egeon',16632,0,NULL);
INSERT INTO `map_tiles` VALUES (235,'desert_de_l_egeon',16633,0,NULL);
INSERT INTO `map_tiles` VALUES (236,'desert_de_l_egeon',16634,0,NULL);
INSERT INTO `map_tiles` VALUES (237,'desert_de_l_egeon',16635,0,NULL);
INSERT INTO `map_tiles` VALUES (238,'desert_de_l_egeon',16636,0,NULL);
INSERT INTO `map_tiles` VALUES (239,'desert_de_l_egeon',16637,0,NULL);
INSERT INTO `map_tiles` VALUES (240,'desert_de_l_egeon',15576,0,NULL);
INSERT INTO `map_tiles` VALUES (241,'desert_de_l_egeon',15573,0,NULL);
INSERT INTO `map_tiles` VALUES (242,'desert_de_l_egeon',15573,0,NULL);
INSERT INTO `map_tiles` VALUES (243,'desert_de_l_egeon',15567,0,NULL);
INSERT INTO `map_tiles` VALUES (244,'desert_de_l_egeon',16638,0,NULL);
INSERT INTO `map_tiles` VALUES (245,'desert_de_l_egeon',15564,0,NULL);
INSERT INTO `map_tiles` VALUES (246,'desert_de_l_egeon',16639,0,NULL);
INSERT INTO `map_tiles` VALUES (247,'desert_de_l_egeon',16640,0,NULL);
INSERT INTO `map_tiles` VALUES (248,'desert_de_l_egeon',15565,0,NULL);
INSERT INTO `map_tiles` VALUES (249,'desert_de_l_egeon',15566,0,NULL);
INSERT INTO `map_tiles` VALUES (250,'desert_de_l_egeon',16641,0,NULL);
INSERT INTO `map_tiles` VALUES (251,'desert_de_l_egeon',16642,0,NULL);
INSERT INTO `map_tiles` VALUES (252,'desert_de_l_egeon',16643,0,NULL);
INSERT INTO `map_tiles` VALUES (253,'desert_de_l_egeon',16644,0,NULL);
INSERT INTO `map_tiles` VALUES (254,'desert_de_l_egeon',16645,0,NULL);
INSERT INTO `map_tiles` VALUES (255,'desert_de_l_egeon',16646,0,NULL);
INSERT INTO `map_tiles` VALUES (256,'desert_de_l_egeon',16647,0,NULL);
INSERT INTO `map_tiles` VALUES (257,'desert_de_l_egeon',16648,0,NULL);
INSERT INTO `map_tiles` VALUES (258,'desert_de_l_egeon',16649,0,NULL);
INSERT INTO `map_tiles` VALUES (259,'desert_de_l_egeon',16650,0,NULL);
INSERT INTO `map_tiles` VALUES (260,'desert_de_l_egeon',16651,0,NULL);
INSERT INTO `map_tiles` VALUES (261,'desert_de_l_egeon',16652,0,NULL);
INSERT INTO `map_tiles` VALUES (262,'desert_de_l_egeon',16653,0,NULL);
INSERT INTO `map_tiles` VALUES (263,'desert_de_l_egeon',16654,0,NULL);
INSERT INTO `map_tiles` VALUES (264,'desert_de_l_egeon',16655,0,NULL);
INSERT INTO `map_tiles` VALUES (265,'desert_de_l_egeon',16656,0,NULL);
INSERT INTO `map_tiles` VALUES (266,'desert_de_l_egeon',16660,0,NULL);
INSERT INTO `map_tiles` VALUES (267,'desert_de_l_egeon',16661,0,NULL);
INSERT INTO `map_tiles` VALUES (268,'desert_de_l_egeon',16662,0,NULL);
INSERT INTO `map_tiles` VALUES (269,'desert_de_l_egeon',16663,0,NULL);
INSERT INTO `map_tiles` VALUES (270,'desert_de_l_egeon',16665,0,NULL);
INSERT INTO `map_tiles` VALUES (271,'desert_de_l_egeon',16666,0,NULL);
INSERT INTO `map_tiles` VALUES (272,'desert_de_l_egeon',16667,0,NULL);
INSERT INTO `map_tiles` VALUES (273,'desert_de_l_egeon',16668,0,NULL);
INSERT INTO `map_tiles` VALUES (274,'desert_de_l_egeon',16669,0,NULL);
INSERT INTO `map_tiles` VALUES (275,'desert_de_l_egeon',16671,0,NULL);
INSERT INTO `map_tiles` VALUES (276,'desert_de_l_egeon',16672,0,NULL);
INSERT INTO `map_tiles` VALUES (277,'desert_de_l_egeon',16673,0,NULL);
INSERT INTO `map_tiles` VALUES (278,'desert_de_l_egeon',16674,0,NULL);
INSERT INTO `map_tiles` VALUES (279,'desert_de_l_egeon',16675,0,NULL);
INSERT INTO `map_tiles` VALUES (280,'desert_de_l_egeon',16676,0,NULL);
INSERT INTO `map_tiles` VALUES (281,'desert_de_l_egeon',16677,0,NULL);
INSERT INTO `map_tiles` VALUES (282,'desert_de_l_egeon',16678,0,NULL);
INSERT INTO `map_tiles` VALUES (283,'desert_de_l_egeon',16679,0,NULL);
INSERT INTO `map_tiles` VALUES (284,'desert_de_l_egeon',16680,0,NULL);
INSERT INTO `map_tiles` VALUES (285,'desert_de_l_egeon',16681,0,NULL);
INSERT INTO `map_tiles` VALUES (286,'desert_de_l_egeon',16682,0,NULL);
INSERT INTO `map_tiles` VALUES (287,'desert_de_l_egeon',16683,0,NULL);
INSERT INTO `map_tiles` VALUES (288,'desert_de_l_egeon',16684,0,NULL);
INSERT INTO `map_tiles` VALUES (289,'desert_de_l_egeon',16685,0,NULL);
INSERT INTO `map_tiles` VALUES (290,'desert_de_l_egeon',16686,0,NULL);
INSERT INTO `map_tiles` VALUES (291,'desert_de_l_egeon',16687,0,NULL);
INSERT INTO `map_tiles` VALUES (292,'desert_de_l_egeon',16688,0,NULL);
INSERT INTO `map_tiles` VALUES (293,'desert_de_l_egeon',16689,0,NULL);
INSERT INTO `map_tiles` VALUES (294,'desert_de_l_egeon',16690,0,NULL);
INSERT INTO `map_tiles` VALUES (295,'desert_de_l_egeon',16692,0,NULL);
INSERT INTO `map_tiles` VALUES (296,'desert_de_l_egeon',16704,0,NULL);
INSERT INTO `map_tiles` VALUES (297,'desert_de_l_egeon',16706,0,NULL);
INSERT INTO `map_tiles` VALUES (298,'desert_de_l_egeon',16708,0,NULL);
INSERT INTO `map_tiles` VALUES (299,'desert_de_l_egeon',16709,0,NULL);
INSERT INTO `map_tiles` VALUES (300,'desert_de_l_egeon',16710,0,NULL);
INSERT INTO `map_tiles` VALUES (301,'desert_de_l_egeon',16712,0,NULL);
INSERT INTO `map_tiles` VALUES (302,'desert_de_l_egeon',16713,0,NULL);
INSERT INTO `map_tiles` VALUES (303,'desert_de_l_egeon',16714,0,NULL);
INSERT INTO `map_tiles` VALUES (304,'desert_de_l_egeon',16715,0,NULL);
INSERT INTO `map_tiles` VALUES (305,'desert_de_l_egeon',16716,0,NULL);
INSERT INTO `map_tiles` VALUES (306,'desert_de_l_egeon',16717,0,NULL);
INSERT INTO `map_tiles` VALUES (307,'desert_de_l_egeon',16719,0,NULL);
INSERT INTO `map_tiles` VALUES (308,'desert_de_l_egeon',16720,0,NULL);
INSERT INTO `map_tiles` VALUES (309,'desert_de_l_egeon',16722,0,NULL);
INSERT INTO `map_tiles` VALUES (310,'desert_de_l_egeon',16723,0,NULL);
INSERT INTO `map_tiles` VALUES (311,'desert_de_l_egeon',16724,0,NULL);
INSERT INTO `map_tiles` VALUES (312,'desert_de_l_egeon',16725,0,NULL);
INSERT INTO `map_tiles` VALUES (313,'desert_de_l_egeon',16726,0,NULL);
INSERT INTO `map_tiles` VALUES (314,'desert_de_l_egeon',16727,0,NULL);
INSERT INTO `map_tiles` VALUES (315,'desert_de_l_egeon',16728,0,NULL);
INSERT INTO `map_tiles` VALUES (316,'desert_de_l_egeon',16729,0,NULL);
INSERT INTO `map_tiles` VALUES (317,'desert_de_l_egeon',16730,0,NULL);
INSERT INTO `map_tiles` VALUES (318,'desert_de_l_egeon',16730,0,NULL);
INSERT INTO `map_tiles` VALUES (319,'desert_de_l_egeon',16731,0,NULL);
INSERT INTO `map_tiles` VALUES (320,'desert_de_l_egeon',16732,0,NULL);
INSERT INTO `map_tiles` VALUES (321,'desert_de_l_egeon',16733,0,NULL);
INSERT INTO `map_tiles` VALUES (322,'desert_de_l_egeon',16734,0,NULL);
INSERT INTO `map_tiles` VALUES (323,'desert_de_l_egeon',16735,0,NULL);
INSERT INTO `map_tiles` VALUES (324,'desert_de_l_egeon',16736,0,NULL);
INSERT INTO `map_tiles` VALUES (325,'desert_de_l_egeon',16737,0,NULL);
INSERT INTO `map_tiles` VALUES (326,'desert_de_l_egeon',16738,0,NULL);
INSERT INTO `map_tiles` VALUES (327,'desert_de_l_egeon',16739,0,NULL);
INSERT INTO `map_tiles` VALUES (328,'desert_de_l_egeon',16740,0,NULL);
INSERT INTO `map_tiles` VALUES (329,'desert_de_l_egeon',16741,0,NULL);
INSERT INTO `map_tiles` VALUES (330,'desert_de_l_egeon',16742,0,NULL);
INSERT INTO `map_tiles` VALUES (331,'desert_de_l_egeon',16743,0,NULL);
INSERT INTO `map_tiles` VALUES (332,'desert_de_l_egeon',16744,0,NULL);
INSERT INTO `map_tiles` VALUES (333,'desert_de_l_egeon',16745,0,NULL);
INSERT INTO `map_tiles` VALUES (334,'desert_de_l_egeon',16745,0,NULL);
INSERT INTO `map_tiles` VALUES (335,'desert_de_l_egeon',16746,0,NULL);
INSERT INTO `map_tiles` VALUES (336,'desert_de_l_egeon',16747,0,NULL);
INSERT INTO `map_tiles` VALUES (337,'desert_de_l_egeon',16748,0,NULL);
INSERT INTO `map_tiles` VALUES (338,'desert_de_l_egeon',16749,0,NULL);
INSERT INTO `map_tiles` VALUES (339,'desert_de_l_egeon',16750,0,NULL);
INSERT INTO `map_tiles` VALUES (340,'desert_de_l_egeon',16751,0,NULL);
INSERT INTO `map_tiles` VALUES (341,'desert_de_l_egeon',16752,0,NULL);
INSERT INTO `map_tiles` VALUES (342,'desert_de_l_egeon',16753,0,NULL);
INSERT INTO `map_tiles` VALUES (343,'desert_de_l_egeon',16754,0,NULL);
INSERT INTO `map_tiles` VALUES (344,'desert_de_l_egeon',16755,0,NULL);
INSERT INTO `map_tiles` VALUES (345,'desert_de_l_egeon',16756,0,NULL);
INSERT INTO `map_tiles` VALUES (346,'desert_de_l_egeon',16757,0,NULL);
INSERT INTO `map_tiles` VALUES (347,'desert_de_l_egeon',16758,0,NULL);
INSERT INTO `map_tiles` VALUES (348,'desert_de_l_egeon',16759,0,NULL);
INSERT INTO `map_tiles` VALUES (349,'desert_de_l_egeon',16760,0,NULL);
INSERT INTO `map_tiles` VALUES (350,'desert_de_l_egeon',15568,0,NULL);
INSERT INTO `map_tiles` VALUES (351,'desert_de_l_egeon',16761,0,NULL);
INSERT INTO `map_tiles` VALUES (352,'desert_de_l_egeon',16762,0,NULL);
INSERT INTO `map_tiles` VALUES (353,'desert_de_l_egeon',16763,0,NULL);
INSERT INTO `map_tiles` VALUES (354,'desert_de_l_egeon',16765,0,NULL);
INSERT INTO `map_tiles` VALUES (355,'desert_de_l_egeon',16767,0,NULL);
INSERT INTO `map_tiles` VALUES (356,'desert_de_l_egeon',16768,0,NULL);
INSERT INTO `map_tiles` VALUES (357,'desert_de_l_egeon',16769,0,NULL);
INSERT INTO `map_tiles` VALUES (358,'desert_de_l_egeon',16770,0,NULL);
INSERT INTO `map_tiles` VALUES (359,'desert_de_l_egeon',16771,0,NULL);
INSERT INTO `map_tiles` VALUES (360,'desert_de_l_egeon',16772,0,NULL);
INSERT INTO `map_tiles` VALUES (361,'desert_de_l_egeon',16777,0,NULL);
INSERT INTO `map_tiles` VALUES (362,'desert_de_l_egeon',16779,0,NULL);
INSERT INTO `map_tiles` VALUES (363,'desert_de_l_egeon',16780,0,NULL);
INSERT INTO `map_tiles` VALUES (364,'desert_de_l_egeon',16781,0,NULL);
INSERT INTO `map_tiles` VALUES (365,'desert_de_l_egeon',16782,0,NULL);
INSERT INTO `map_tiles` VALUES (366,'desert_de_l_egeon',16783,0,NULL);
INSERT INTO `map_tiles` VALUES (367,'desert_de_l_egeon',16784,0,NULL);
INSERT INTO `map_tiles` VALUES (368,'desert_de_l_egeon',16785,0,NULL);
INSERT INTO `map_tiles` VALUES (369,'desert_de_l_egeon',16786,0,NULL);
INSERT INTO `map_tiles` VALUES (370,'desert_de_l_egeon',16787,0,NULL);
INSERT INTO `map_tiles` VALUES (371,'desert_de_l_egeon',16788,0,NULL);
INSERT INTO `map_tiles` VALUES (372,'desert_de_l_egeon',16789,0,NULL);
INSERT INTO `map_tiles` VALUES (373,'desert_de_l_egeon',16790,0,NULL);
INSERT INTO `map_tiles` VALUES (374,'desert_de_l_egeon',16791,0,NULL);
INSERT INTO `map_tiles` VALUES (375,'desert_de_l_egeon',16792,0,NULL);
INSERT INTO `map_tiles` VALUES (376,'desert_de_l_egeon',16793,0,NULL);
INSERT INTO `map_tiles` VALUES (377,'desert_de_l_egeon',16794,0,NULL);
INSERT INTO `map_tiles` VALUES (378,'desert_de_l_egeon',16795,0,NULL);
INSERT INTO `map_tiles` VALUES (379,'desert_de_l_egeon',16796,0,NULL);
INSERT INTO `map_tiles` VALUES (380,'desert_de_l_egeon',16798,0,NULL);
INSERT INTO `map_tiles` VALUES (381,'desert_de_l_egeon',16799,0,NULL);
INSERT INTO `map_tiles` VALUES (382,'desert_de_l_egeon',16799,0,NULL);
INSERT INTO `map_tiles` VALUES (383,'desert_de_l_egeon',16800,0,NULL);
INSERT INTO `map_tiles` VALUES (384,'desert_de_l_egeon',16801,0,NULL);
INSERT INTO `map_tiles` VALUES (385,'desert_de_l_egeon',16802,0,NULL);
INSERT INTO `map_tiles` VALUES (386,'desert_de_l_egeon',16803,0,NULL);
INSERT INTO `map_tiles` VALUES (387,'desert_de_l_egeon',16804,0,NULL);
INSERT INTO `map_tiles` VALUES (388,'desert_de_l_egeon',16805,0,NULL);
INSERT INTO `map_tiles` VALUES (389,'desert_de_l_egeon',16807,0,NULL);
INSERT INTO `map_tiles` VALUES (390,'desert_de_l_egeon',16809,0,NULL);
INSERT INTO `map_tiles` VALUES (391,'desert_de_l_egeon',16810,0,NULL);
INSERT INTO `map_tiles` VALUES (392,'desert_de_l_egeon',16817,0,NULL);
INSERT INTO `map_tiles` VALUES (393,'desert_de_l_egeon',16818,0,NULL);
INSERT INTO `map_tiles` VALUES (394,'desert_de_l_egeon',16819,0,NULL);
INSERT INTO `map_tiles` VALUES (395,'desert_de_l_egeon',16820,0,NULL);
INSERT INTO `map_tiles` VALUES (396,'desert_de_l_egeon',16821,0,NULL);
INSERT INTO `map_tiles` VALUES (397,'desert_de_l_egeon',16822,0,NULL);
INSERT INTO `map_tiles` VALUES (398,'desert_de_l_egeon',16823,0,NULL);
INSERT INTO `map_tiles` VALUES (399,'desert_de_l_egeon',16821,0,NULL);
INSERT INTO `map_tiles` VALUES (400,'desert_de_l_egeon',16824,0,NULL);
INSERT INTO `map_tiles` VALUES (401,'desert_de_l_egeon',16825,0,NULL);
INSERT INTO `map_tiles` VALUES (402,'desert_de_l_egeon',16826,0,NULL);
INSERT INTO `map_tiles` VALUES (403,'desert_de_l_egeon',16827,0,NULL);
INSERT INTO `map_tiles` VALUES (404,'desert_de_l_egeon',16827,0,NULL);
INSERT INTO `map_tiles` VALUES (405,'desert_de_l_egeon',16827,0,NULL);
INSERT INTO `map_tiles` VALUES (406,'desert_de_l_egeon',16828,0,NULL);
INSERT INTO `map_tiles` VALUES (407,'desert_de_l_egeon',16829,0,NULL);
INSERT INTO `map_tiles` VALUES (408,'desert_de_l_egeon',16830,0,NULL);
INSERT INTO `map_tiles` VALUES (409,'desert_de_l_egeon',16830,0,NULL);
INSERT INTO `map_tiles` VALUES (410,'desert_de_l_egeon',16831,0,NULL);
INSERT INTO `map_tiles` VALUES (411,'desert_de_l_egeon',16832,0,NULL);
INSERT INTO `map_tiles` VALUES (412,'desert_de_l_egeon',16818,0,NULL);
INSERT INTO `map_tiles` VALUES (413,'desert_de_l_egeon',16833,0,NULL);
INSERT INTO `map_tiles` VALUES (414,'desert_de_l_egeon',16834,0,NULL);
INSERT INTO `map_tiles` VALUES (415,'desert_de_l_egeon',16834,0,NULL);
INSERT INTO `map_tiles` VALUES (416,'desert_de_l_egeon',16834,0,NULL);
INSERT INTO `map_tiles` VALUES (417,'desert_de_l_egeon',16835,0,NULL);
INSERT INTO `map_tiles` VALUES (418,'desert_de_l_egeon',16834,0,NULL);
INSERT INTO `map_tiles` VALUES (419,'desert_de_l_egeon',16836,0,NULL);
INSERT INTO `map_tiles` VALUES (420,'desert_de_l_egeon',16837,0,NULL);
INSERT INTO `map_tiles` VALUES (421,'desert_de_l_egeon',16838,0,NULL);
INSERT INTO `map_tiles` VALUES (422,'desert_de_l_egeon',16838,0,NULL);
INSERT INTO `map_tiles` VALUES (423,'desert_de_l_egeon',16839,0,NULL);
INSERT INTO `map_tiles` VALUES (424,'desert_de_l_egeon',16840,0,NULL);
INSERT INTO `map_tiles` VALUES (425,'desert_de_l_egeon',16840,0,NULL);
INSERT INTO `map_tiles` VALUES (426,'desert_de_l_egeon',16841,0,NULL);
INSERT INTO `map_tiles` VALUES (427,'desert_de_l_egeon',16842,0,NULL);
INSERT INTO `map_tiles` VALUES (428,'desert_de_l_egeon',16843,0,NULL);
INSERT INTO `map_tiles` VALUES (429,'desert_de_l_egeon',16844,0,NULL);
INSERT INTO `map_tiles` VALUES (430,'desert_de_l_egeon',16845,0,NULL);
INSERT INTO `map_tiles` VALUES (431,'desert_de_l_egeon',16846,0,NULL);
INSERT INTO `map_tiles` VALUES (432,'desert_de_l_egeon',16847,0,NULL);
INSERT INTO `map_tiles` VALUES (433,'desert_de_l_egeon',16848,0,NULL);
INSERT INTO `map_tiles` VALUES (434,'desert_de_l_egeon',16849,0,NULL);
INSERT INTO `map_tiles` VALUES (435,'desert_de_l_egeon',16850,0,NULL);
INSERT INTO `map_tiles` VALUES (436,'desert_de_l_egeon',16851,0,NULL);
INSERT INTO `map_tiles` VALUES (437,'desert_de_l_egeon',16852,0,NULL);
INSERT INTO `map_tiles` VALUES (438,'desert_de_l_egeon',16853,0,NULL);
INSERT INTO `map_tiles` VALUES (439,'desert_de_l_egeon',16854,0,NULL);
INSERT INTO `map_tiles` VALUES (440,'desert_de_l_egeon',16855,0,NULL);
INSERT INTO `map_tiles` VALUES (441,'desert_de_l_egeon',16856,0,NULL);
INSERT INTO `map_tiles` VALUES (442,'desert_de_l_egeon',16857,0,NULL);
INSERT INTO `map_tiles` VALUES (443,'desert_de_l_egeon',16858,0,NULL);
INSERT INTO `map_tiles` VALUES (444,'desert_de_l_egeon',16859,0,NULL);
INSERT INTO `map_tiles` VALUES (445,'desert_de_l_egeon',16860,0,NULL);
INSERT INTO `map_tiles` VALUES (446,'desert_de_l_egeon',16861,0,NULL);
INSERT INTO `map_tiles` VALUES (447,'desert_de_l_egeon',16862,0,NULL);
INSERT INTO `map_tiles` VALUES (448,'desert_de_l_egeon',16863,0,NULL);
INSERT INTO `map_tiles` VALUES (449,'desert_de_l_egeon',16864,0,NULL);
INSERT INTO `map_tiles` VALUES (450,'desert_de_l_egeon',16862,0,NULL);
INSERT INTO `map_tiles` VALUES (451,'desert_de_l_egeon',16865,0,NULL);
INSERT INTO `map_tiles` VALUES (452,'desert_de_l_egeon',16864,0,NULL);
INSERT INTO `map_tiles` VALUES (453,'desert_de_l_egeon',16866,0,NULL);
INSERT INTO `map_tiles` VALUES (454,'desert_de_l_egeon',16867,0,NULL);
INSERT INTO `map_tiles` VALUES (455,'desert_de_l_egeon',16868,0,NULL);
INSERT INTO `map_tiles` VALUES (456,'desert_de_l_egeon',16869,0,NULL);
INSERT INTO `map_tiles` VALUES (457,'desert_de_l_egeon',16870,0,NULL);
INSERT INTO `map_tiles` VALUES (458,'desert_de_l_egeon',16617,0,NULL);
INSERT INTO `map_tiles` VALUES (459,'desert_de_l_egeon',16871,0,NULL);
INSERT INTO `map_tiles` VALUES (460,'desert_de_l_egeon',16872,0,NULL);
INSERT INTO `map_tiles` VALUES (461,'desert_de_l_egeon',16872,0,NULL);
INSERT INTO `map_tiles` VALUES (462,'desert_de_l_egeon',16873,0,NULL);
INSERT INTO `map_tiles` VALUES (463,'desert_de_l_egeon',16874,0,NULL);
INSERT INTO `map_tiles` VALUES (464,'desert_de_l_egeon',16875,0,NULL);
INSERT INTO `map_tiles` VALUES (465,'desert_de_l_egeon',16876,0,NULL);
INSERT INTO `map_tiles` VALUES (466,'desert_de_l_egeon',16877,0,NULL);
INSERT INTO `map_tiles` VALUES (467,'desert_de_l_egeon',16878,0,NULL);
INSERT INTO `map_tiles` VALUES (468,'desert_de_l_egeon',16879,0,NULL);
INSERT INTO `map_tiles` VALUES (469,'desert_de_l_egeon',16880,0,NULL);
INSERT INTO `map_tiles` VALUES (470,'desert_de_l_egeon',16881,0,NULL);
INSERT INTO `map_tiles` VALUES (471,'desert_de_l_egeon',16882,0,NULL);
INSERT INTO `map_tiles` VALUES (472,'desert_de_l_egeon',16883,0,NULL);
INSERT INTO `map_tiles` VALUES (473,'desert_de_l_egeon',16885,0,NULL);
INSERT INTO `map_tiles` VALUES (474,'carreaux',15504,0,NULL);
INSERT INTO `map_tiles` VALUES (475,'carreaux',15505,0,NULL);
INSERT INTO `map_tiles` VALUES (476,'carreaux',15506,0,NULL);
INSERT INTO `map_tiles` VALUES (477,'carreaux',15507,0,NULL);
INSERT INTO `map_tiles` VALUES (478,'carreaux',15460,0,NULL);
INSERT INTO `map_tiles` VALUES (479,'carreaux',15459,0,NULL);
INSERT INTO `map_tiles` VALUES (480,'carreaux',15458,0,NULL);
INSERT INTO `map_tiles` VALUES (481,'carreaux',15503,0,NULL);
INSERT INTO `map_tiles` VALUES (482,'carreaux',15518,0,NULL);
INSERT INTO `map_tiles` VALUES (483,'carreaux',15461,0,NULL);
INSERT INTO `map_tiles` VALUES (484,'carreaux',15462,0,NULL);
INSERT INTO `map_tiles` VALUES (485,'carreaux',15464,0,NULL);
INSERT INTO `map_tiles` VALUES (486,'carreaux',15465,0,NULL);
INSERT INTO `map_tiles` VALUES (487,'carreaux',15508,0,NULL);
INSERT INTO `map_tiles` VALUES (488,'carreaux',15509,0,NULL);
INSERT INTO `map_tiles` VALUES (489,'carreaux',15466,0,NULL);
INSERT INTO `map_tiles` VALUES (490,'carreaux',15510,0,NULL);
INSERT INTO `map_tiles` VALUES (491,'carreaux',15467,0,NULL);
INSERT INTO `map_tiles` VALUES (492,'carreaux',15468,0,NULL);
INSERT INTO `map_tiles` VALUES (493,'carreaux',15511,0,NULL);
INSERT INTO `map_tiles` VALUES (494,'carreaux',15477,0,NULL);
INSERT INTO `map_tiles` VALUES (495,'carreaux',15512,0,NULL);
INSERT INTO `map_tiles` VALUES (496,'carreaux',15513,0,NULL);
INSERT INTO `map_tiles` VALUES (497,'carreaux',15514,0,NULL);
INSERT INTO `map_tiles` VALUES (498,'carreaux',15476,0,NULL);
INSERT INTO `map_tiles` VALUES (499,'carreaux',15475,0,NULL);
INSERT INTO `map_tiles` VALUES (500,'carreaux',15515,0,NULL);
INSERT INTO `map_tiles` VALUES (501,'carreaux',15471,0,NULL);
INSERT INTO `map_tiles` VALUES (502,'carreaux',15474,0,NULL);
INSERT INTO `map_tiles` VALUES (503,'carreaux',15516,0,NULL);
INSERT INTO `map_tiles` VALUES (504,'carreaux',15517,0,NULL);
INSERT INTO `map_tiles` VALUES (505,'carreaux',15473,0,NULL);
INSERT INTO `map_tiles` VALUES (506,'caverne',35010,0,NULL);
INSERT INTO `map_tiles` VALUES (507,'caverne',35011,0,NULL);
INSERT INTO `map_tiles` VALUES (508,'caverne',35012,0,NULL);
INSERT INTO `map_tiles` VALUES (509,'caverne',35013,0,NULL);
INSERT INTO `map_tiles` VALUES (510,'caverne',35014,0,NULL);
INSERT INTO `map_tiles` VALUES (511,'caverne',35015,0,NULL);
INSERT INTO `map_tiles` VALUES (512,'caverne',35016,0,NULL);
INSERT INTO `map_tiles` VALUES (513,'caverne',35017,0,NULL);
INSERT INTO `map_tiles` VALUES (514,'caverne',35018,0,NULL);
INSERT INTO `map_tiles` VALUES (515,'caverne',35019,0,NULL);
INSERT INTO `map_tiles` VALUES (516,'caverne',35020,0,NULL);
INSERT INTO `map_tiles` VALUES (517,'caverne',35021,0,NULL);
INSERT INTO `map_tiles` VALUES (518,'caverne',35022,0,NULL);
INSERT INTO `map_tiles` VALUES (519,'caverne',35024,0,NULL);
INSERT INTO `map_tiles` VALUES (520,'caverne',35026,0,NULL);
INSERT INTO `map_tiles` VALUES (521,'caverne',35027,0,NULL);
INSERT INTO `map_tiles` VALUES (522,'caverne',35028,0,NULL);
INSERT INTO `map_tiles` VALUES (523,'caverne',35029,0,NULL);
INSERT INTO `map_tiles` VALUES (524,'caverne',35030,0,NULL);
INSERT INTO `map_tiles` VALUES (525,'caverne',35031,0,NULL);
INSERT INTO `map_tiles` VALUES (526,'caverne',35033,0,NULL);
INSERT INTO `map_tiles` VALUES (527,'caverne',35034,0,NULL);
INSERT INTO `map_tiles` VALUES (528,'caverne',35036,0,NULL);
INSERT INTO `map_tiles` VALUES (529,'caverne',35037,0,NULL);
INSERT INTO `map_tiles` VALUES (530,'caverne',35039,0,NULL);
INSERT INTO `map_tiles` VALUES (531,'caverne',35040,0,NULL);
INSERT INTO `map_tiles` VALUES (532,'caverne',35041,0,NULL);
INSERT INTO `map_tiles` VALUES (533,'caverne',35042,0,NULL);
INSERT INTO `map_tiles` VALUES (534,'caverne',35043,0,NULL);
INSERT INTO `map_tiles` VALUES (535,'caverne',35044,0,NULL);
INSERT INTO `map_tiles` VALUES (536,'caverne',35045,0,NULL);
INSERT INTO `map_tiles` VALUES (537,'caverne',35046,0,NULL);
INSERT INTO `map_tiles` VALUES (538,'caverne',35047,0,NULL);
INSERT INTO `map_tiles` VALUES (539,'caverne',35050,0,NULL);
INSERT INTO `map_tiles` VALUES (540,'caverne',35053,0,NULL);
INSERT INTO `map_tiles` VALUES (541,'caverne',35055,0,NULL);
INSERT INTO `map_tiles` VALUES (542,'caverne',35057,0,NULL);
INSERT INTO `map_tiles` VALUES (543,'caverne',35058,0,NULL);
INSERT INTO `map_tiles` VALUES (544,'caverne',35059,0,NULL);
INSERT INTO `map_tiles` VALUES (545,'caverne',35060,0,NULL);
INSERT INTO `map_tiles` VALUES (546,'caverne',35061,0,NULL);
INSERT INTO `map_tiles` VALUES (547,'caverne',35062,0,NULL);
INSERT INTO `map_tiles` VALUES (548,'caverne',35063,0,NULL);
INSERT INTO `map_tiles` VALUES (549,'caverne',35065,0,NULL);
INSERT INTO `map_tiles` VALUES (550,'caverne',35066,0,NULL);
INSERT INTO `map_tiles` VALUES (551,'caverne',35067,0,NULL);
INSERT INTO `map_tiles` VALUES (552,'caverne',35069,0,NULL);
INSERT INTO `map_tiles` VALUES (553,'caverne',35070,0,NULL);
INSERT INTO `map_tiles` VALUES (554,'caverne',35073,0,NULL);
INSERT INTO `map_tiles` VALUES (555,'caverne',35074,0,NULL);
INSERT INTO `map_tiles` VALUES (556,'caverne',35075,0,NULL);
INSERT INTO `map_tiles` VALUES (557,'caverne',35076,0,NULL);
INSERT INTO `map_tiles` VALUES (558,'caverne',35077,0,NULL);
INSERT INTO `map_tiles` VALUES (559,'caverne',35078,0,NULL);
INSERT INTO `map_tiles` VALUES (560,'caverne',35079,0,NULL);
INSERT INTO `map_tiles` VALUES (561,'caverne',35080,0,NULL);
INSERT INTO `map_tiles` VALUES (562,'caverne',35081,0,NULL);
INSERT INTO `map_tiles` VALUES (563,'caverne',35082,0,NULL);
INSERT INTO `map_tiles` VALUES (564,'caverne',35083,0,NULL);
INSERT INTO `map_tiles` VALUES (565,'caverne',35084,0,NULL);
INSERT INTO `map_tiles` VALUES (566,'caverne',35085,0,NULL);
INSERT INTO `map_tiles` VALUES (567,'caverne',35086,0,NULL);
INSERT INTO `map_tiles` VALUES (568,'caverne',35089,0,NULL);
INSERT INTO `map_tiles` VALUES (569,'caverne',35090,0,NULL);
INSERT INTO `map_tiles` VALUES (570,'caverne',35091,0,NULL);
INSERT INTO `map_tiles` VALUES (571,'caverne',35092,0,NULL);
INSERT INTO `map_tiles` VALUES (572,'caverne',35093,0,NULL);
INSERT INTO `map_tiles` VALUES (573,'caverne',35096,0,NULL);
INSERT INTO `map_tiles` VALUES (574,'caverne',35099,0,NULL);
INSERT INTO `map_tiles` VALUES (575,'caverne',35100,0,NULL);
INSERT INTO `map_tiles` VALUES (576,'caverne',35101,0,NULL);
INSERT INTO `map_tiles` VALUES (577,'caverne',35102,0,NULL);
INSERT INTO `map_tiles` VALUES (578,'caverne',35103,0,NULL);
INSERT INTO `map_tiles` VALUES (579,'caverne',35104,0,NULL);
INSERT INTO `map_tiles` VALUES (580,'caverne',35105,0,NULL);
INSERT INTO `map_tiles` VALUES (581,'caverne',35107,0,NULL);
INSERT INTO `map_tiles` VALUES (582,'caverne',35108,0,NULL);
INSERT INTO `map_tiles` VALUES (583,'caverne',35109,0,NULL);
INSERT INTO `map_tiles` VALUES (584,'caverne',35110,0,NULL);
INSERT INTO `map_tiles` VALUES (585,'caverne',35111,0,NULL);
INSERT INTO `map_tiles` VALUES (586,'caverne',35113,0,NULL);
INSERT INTO `map_tiles` VALUES (587,'caverne',35114,0,NULL);
INSERT INTO `map_tiles` VALUES (588,'caverne',35116,0,NULL);
INSERT INTO `map_tiles` VALUES (589,'caverne',35118,0,NULL);
INSERT INTO `map_tiles` VALUES (590,'caverne',35038,0,NULL);
INSERT INTO `map_tiles` VALUES (591,'rune1',35025,0,NULL);
INSERT INTO `map_tiles` VALUES (592,'caverne',35121,0,NULL);
INSERT INTO `map_tiles` VALUES (593,'caverne',35122,0,NULL);
INSERT INTO `map_tiles` VALUES (594,'caverne',35123,0,NULL);
INSERT INTO `map_tiles` VALUES (595,'caverne',35124,0,NULL);
INSERT INTO `map_tiles` VALUES (596,'caverne',35125,0,NULL);
INSERT INTO `map_tiles` VALUES (597,'caverne',35126,0,NULL);
INSERT INTO `map_tiles` VALUES (598,'caverne',35127,0,NULL);
INSERT INTO `map_tiles` VALUES (599,'caverne',35128,0,NULL);
INSERT INTO `map_tiles` VALUES (600,'caverne',35129,0,NULL);
INSERT INTO `map_tiles` VALUES (601,'caverne',35130,0,NULL);
INSERT INTO `map_tiles` VALUES (602,'caverne',35131,0,NULL);
INSERT INTO `map_tiles` VALUES (603,'caverne',35132,0,NULL);
INSERT INTO `map_tiles` VALUES (604,'caverne',35133,0,NULL);
INSERT INTO `map_tiles` VALUES (605,'caverne',35134,0,NULL);
INSERT INTO `map_tiles` VALUES (606,'caverne',35135,0,NULL);
INSERT INTO `map_tiles` VALUES (607,'caverne',35136,0,NULL);
INSERT INTO `map_tiles` VALUES (608,'caverne',35137,0,NULL);
INSERT INTO `map_tiles` VALUES (609,'caverne',35138,0,NULL);
INSERT INTO `map_tiles` VALUES (610,'caverne',35117,0,NULL);
INSERT INTO `map_tiles` VALUES (611,'caverne',35023,0,NULL);
INSERT INTO `map_tiles` VALUES (612,'caverne',35035,0,NULL);
INSERT INTO `map_tiles` VALUES (613,'caverne',35052,0,NULL);
INSERT INTO `map_tiles` VALUES (614,'caverne',35072,0,NULL);
INSERT INTO `map_tiles` VALUES (615,'caverne',35051,0,NULL);
INSERT INTO `map_tiles` VALUES (616,'caverne',35097,0,NULL);
INSERT INTO `map_tiles` VALUES (617,'caverne',35098,0,NULL);
INSERT INTO `map_tiles` VALUES (618,'caverne',35071,0,NULL);
INSERT INTO `map_tiles` VALUES (619,'caverne',35115,0,NULL);
INSERT INTO `map_tiles` VALUES (620,'caverne',35094,0,NULL);
INSERT INTO `map_tiles` VALUES (621,'caverne',35032,0,NULL);
INSERT INTO `map_tiles` VALUES (622,'caverne',35048,0,NULL);
INSERT INTO `map_tiles` VALUES (623,'caverne',35068,0,NULL);
INSERT INTO `map_tiles` VALUES (624,'caverne',35049,0,NULL);
INSERT INTO `map_tiles` VALUES (625,'caverne',35095,0,NULL);
INSERT INTO `map_tiles` VALUES (626,'caverne',35112,0,NULL);
INSERT INTO `map_tiles` VALUES (627,'caverne',35120,0,NULL);
INSERT INTO `map_tiles` VALUES (628,'caverne',35119,0,NULL);
INSERT INTO `map_tiles` VALUES (629,'caverne',35150,0,NULL);
INSERT INTO `map_tiles` VALUES (630,'caverne',35149,0,NULL);
INSERT INTO `map_tiles` VALUES (631,'caverne',35148,0,NULL);
INSERT INTO `map_tiles` VALUES (632,'caverne',35147,0,NULL);
INSERT INTO `map_tiles` VALUES (633,'caverne',35146,0,NULL);
INSERT INTO `map_tiles` VALUES (634,'caverne',35145,0,NULL);
INSERT INTO `map_tiles` VALUES (635,'caverne',35144,0,NULL);
INSERT INTO `map_tiles` VALUES (636,'caverne',35143,0,NULL);
INSERT INTO `map_tiles` VALUES (637,'caverne',35142,0,NULL);
INSERT INTO `map_tiles` VALUES (638,'caverne',35141,0,NULL);
INSERT INTO `map_tiles` VALUES (639,'caverne',35140,0,NULL);
INSERT INTO `map_tiles` VALUES (640,'caverne',35139,0,NULL);
INSERT INTO `map_tiles` VALUES (641,'caverne',35106,0,NULL);
INSERT INTO `map_tiles` VALUES (642,'fefnir',35096,0,NULL);
INSERT INTO `map_tiles` VALUES (643,'fefnir',35095,0,NULL);
INSERT INTO `map_tiles` VALUES (644,'caverne',35064,0,NULL);
INSERT INTO `map_tiles` VALUES (645,'caverne',35087,0,NULL);
INSERT INTO `map_tiles` VALUES (646,'caverne',35088,0,NULL);
INSERT INTO `map_tiles` VALUES (647,'caverne',35025,0,NULL);
INSERT INTO `map_tiles` VALUES (648,'rune10',35025,0,NULL);
INSERT INTO `map_tiles` VALUES (649,'caverne',35054,0,NULL);
INSERT INTO `map_tiles` VALUES (650,'caverne',35056,0,NULL);
INSERT INTO `map_tiles` VALUES (651,'rune15',35056,0,NULL);
INSERT INTO `map_tiles` VALUES (652,'rune9',35054,0,NULL);
INSERT INTO `map_tiles` VALUES (653,'desert_de_l_egeon',37475,0,NULL);
INSERT INTO `map_tiles` VALUES (654,'desert_de_l_egeon',37481,0,NULL);
INSERT INTO `map_tiles` VALUES (655,'desert_de_l_egeon',37482,0,NULL);
INSERT INTO `map_tiles` VALUES (656,'desert_de_l_egeon',37483,0,NULL);
INSERT INTO `map_tiles` VALUES (657,'desert_de_l_egeon',37484,0,NULL);
INSERT INTO `map_tiles` VALUES (658,'desert_de_l_egeon',37485,0,NULL);
INSERT INTO `map_tiles` VALUES (659,'desert_de_l_egeon',37486,0,NULL);
INSERT INTO `map_tiles` VALUES (660,'desert_de_l_egeon',37487,0,NULL);
INSERT INTO `map_tiles` VALUES (661,'desert_de_l_egeon',37488,0,NULL);
INSERT INTO `map_tiles` VALUES (662,'desert_de_l_egeon',37489,0,NULL);
INSERT INTO `map_tiles` VALUES (663,'desert_de_l_egeon',37476,0,NULL);
INSERT INTO `map_tiles` VALUES (664,'desert_de_l_egeon',37477,0,NULL);
INSERT INTO `map_tiles` VALUES (665,'lac_thetis',37490,0,NULL);
INSERT INTO `map_tiles` VALUES (666,'lac_thetis',37491,0,NULL);
INSERT INTO `map_tiles` VALUES (667,'lac_thetis',37492,0,NULL);
INSERT INTO `map_tiles` VALUES (668,'lac_thetis',37493,0,NULL);
INSERT INTO `map_tiles` VALUES (669,'lac_thetis',37494,0,NULL);
INSERT INTO `map_tiles` VALUES (670,'lac_thetis',37495,0,NULL);
INSERT INTO `map_tiles` VALUES (671,'lac_thetis',37496,0,NULL);
INSERT INTO `map_tiles` VALUES (672,'lac_thetis',37497,0,NULL);
INSERT INTO `map_tiles` VALUES (673,'lac_thetis',37498,0,NULL);
INSERT INTO `map_tiles` VALUES (674,'lac_thetis',37499,0,NULL);
INSERT INTO `map_tiles` VALUES (675,'lac_thetis',37500,0,NULL);
INSERT INTO `map_tiles` VALUES (676,'lac_thetis',37501,0,NULL);
INSERT INTO `map_tiles` VALUES (677,'lac_thetis',37502,0,NULL);
INSERT INTO `map_tiles` VALUES (678,'lac_thetis',37503,0,NULL);
INSERT INTO `map_tiles` VALUES (679,'lac_thetis',37504,0,NULL);
INSERT INTO `map_tiles` VALUES (680,'desert_de_l_egeon',37480,0,NULL);
INSERT INTO `map_tiles` VALUES (681,'desert_de_l_egeon',37478,0,NULL);
INSERT INTO `map_tiles` VALUES (682,'desert_de_l_egeon',37479,0,NULL);
INSERT INTO `map_tiles` VALUES (683,'carreaux',16999,0,NULL);
INSERT INTO `map_tiles` VALUES (684,'carreaux',17000,0,NULL);
INSERT INTO `map_tiles` VALUES (685,'carreaux',17001,0,NULL);
INSERT INTO `map_tiles` VALUES (686,'carreaux',17002,0,NULL);
INSERT INTO `map_tiles` VALUES (687,'carreaux',17003,0,NULL);
INSERT INTO `map_tiles` VALUES (688,'carreaux',17004,0,NULL);
INSERT INTO `map_tiles` VALUES (689,'carreaux',17005,0,NULL);
INSERT INTO `map_tiles` VALUES (690,'carreaux',17006,0,NULL);
INSERT INTO `map_tiles` VALUES (691,'carreaux',17007,0,NULL);
INSERT INTO `map_tiles` VALUES (692,'carreaux',17008,0,NULL);
INSERT INTO `map_tiles` VALUES (693,'carreaux',17009,0,NULL);
INSERT INTO `map_tiles` VALUES (694,'carreaux',15318,0,NULL);
INSERT INTO `map_tiles` VALUES (695,'carreaux',17010,0,NULL);
INSERT INTO `map_tiles` VALUES (696,'carreaux',17011,0,NULL);
INSERT INTO `map_tiles` VALUES (697,'carreaux',17012,0,NULL);
INSERT INTO `map_tiles` VALUES (698,'carreaux',17013,0,NULL);
INSERT INTO `map_tiles` VALUES (699,'carreaux',17014,0,NULL);
INSERT INTO `map_tiles` VALUES (700,'carreaux',17015,0,NULL);
INSERT INTO `map_tiles` VALUES (701,'carreaux',17016,0,NULL);
INSERT INTO `map_tiles` VALUES (702,'carreaux',17017,0,NULL);
INSERT INTO `map_tiles` VALUES (703,'carreaux',17018,0,NULL);
INSERT INTO `map_tiles` VALUES (704,'carreaux',17019,0,NULL);
INSERT INTO `map_tiles` VALUES (705,'carreaux',17020,0,NULL);
INSERT INTO `map_tiles` VALUES (706,'carreaux',17021,0,NULL);
INSERT INTO `map_tiles` VALUES (707,'pit',16998,0,NULL);
INSERT INTO `map_tiles` VALUES (708,'route',17037,0,NULL);
INSERT INTO `map_tiles` VALUES (709,'route',17098,0,NULL);
INSERT INTO `map_tiles` VALUES (710,'route',17099,0,NULL);
INSERT INTO `map_tiles` VALUES (711,'route',17100,0,NULL);
INSERT INTO `map_tiles` VALUES (712,'route',17101,0,NULL);
INSERT INTO `map_tiles` VALUES (713,'route',17102,0,NULL);
INSERT INTO `map_tiles` VALUES (714,'route',17103,0,NULL);
INSERT INTO `map_tiles` VALUES (715,'route',17104,0,NULL);
INSERT INTO `map_tiles` VALUES (716,'route',17105,0,NULL);
INSERT INTO `map_tiles` VALUES (717,'route',17106,0,NULL);
INSERT INTO `map_tiles` VALUES (718,'route',17107,0,NULL);
INSERT INTO `map_tiles` VALUES (719,'route',17108,0,NULL);
INSERT INTO `map_tiles` VALUES (720,'route',17109,0,NULL);
INSERT INTO `map_tiles` VALUES (721,'route',17110,0,NULL);
INSERT INTO `map_tiles` VALUES (722,'route',17111,0,NULL);
INSERT INTO `map_tiles` VALUES (723,'route',17112,0,NULL);
INSERT INTO `map_tiles` VALUES (724,'route',17113,0,NULL);
INSERT INTO `map_tiles` VALUES (725,'route',17114,0,NULL);
INSERT INTO `map_tiles` VALUES (726,'route',17115,0,NULL);
INSERT INTO `map_tiles` VALUES (727,'route',17116,0,NULL);
INSERT INTO `map_tiles` VALUES (728,'route',17117,0,NULL);
INSERT INTO `map_tiles` VALUES (729,'route',17118,0,NULL);
INSERT INTO `map_tiles` VALUES (730,'route',17119,0,NULL);
INSERT INTO `map_tiles` VALUES (731,'route',17120,0,NULL);
INSERT INTO `map_tiles` VALUES (732,'route',17121,0,NULL);
INSERT INTO `map_tiles` VALUES (733,'route',17122,0,NULL);
INSERT INTO `map_tiles` VALUES (734,'route',17123,0,NULL);
INSERT INTO `map_tiles` VALUES (735,'route',17124,0,NULL);
INSERT INTO `map_tiles` VALUES (736,'route',17125,0,NULL);
INSERT INTO `map_tiles` VALUES (737,'route',17126,0,NULL);
INSERT INTO `map_tiles` VALUES (738,'route',17127,0,NULL);
INSERT INTO `map_tiles` VALUES (739,'route',17128,0,NULL);
INSERT INTO `map_tiles` VALUES (740,'route',17129,0,NULL);
INSERT INTO `map_tiles` VALUES (741,'route',17130,0,NULL);
INSERT INTO `map_tiles` VALUES (742,'route',17131,0,NULL);
INSERT INTO `map_tiles` VALUES (743,'route',17132,0,NULL);
INSERT INTO `map_tiles` VALUES (744,'route',17133,0,NULL);
INSERT INTO `map_tiles` VALUES (745,'route',17134,0,NULL);
INSERT INTO `map_tiles` VALUES (746,'route',17135,0,NULL);
INSERT INTO `map_tiles` VALUES (747,'route',17136,0,NULL);
INSERT INTO `map_tiles` VALUES (748,'route',17144,0,NULL);
INSERT INTO `map_tiles` VALUES (749,'route',17145,0,NULL);
INSERT INTO `map_tiles` VALUES (750,'route',17146,0,NULL);
INSERT INTO `map_tiles` VALUES (751,'route',17147,0,NULL);
INSERT INTO `map_tiles` VALUES (752,'carreaux',17149,0,NULL);
INSERT INTO `map_tiles` VALUES (753,'carreaux',17150,0,NULL);
INSERT INTO `map_tiles` VALUES (754,'carreaux',17151,0,NULL);
INSERT INTO `map_tiles` VALUES (755,'carreaux',17152,0,NULL);
INSERT INTO `map_tiles` VALUES (756,'carreaux',17153,0,NULL);
INSERT INTO `map_tiles` VALUES (757,'carreaux',17154,0,NULL);
INSERT INTO `map_tiles` VALUES (758,'carreaux',17155,0,NULL);
INSERT INTO `map_tiles` VALUES (759,'carreaux',17156,0,NULL);
INSERT INTO `map_tiles` VALUES (760,'carreaux',17157,0,NULL);
INSERT INTO `map_tiles` VALUES (761,'carreaux',17158,0,NULL);
INSERT INTO `map_tiles` VALUES (762,'carreaux',17159,0,NULL);
INSERT INTO `map_tiles` VALUES (763,'carreaux',17160,0,NULL);
INSERT INTO `map_tiles` VALUES (764,'carreaux',17161,0,NULL);
INSERT INTO `map_tiles` VALUES (765,'carreaux',17162,0,NULL);
INSERT INTO `map_tiles` VALUES (766,'carreaux',17163,0,NULL);
INSERT INTO `map_tiles` VALUES (767,'carreaux',17164,0,NULL);
INSERT INTO `map_tiles` VALUES (768,'carreaux',17165,0,NULL);
INSERT INTO `map_tiles` VALUES (769,'carreaux',17166,0,NULL);
INSERT INTO `map_tiles` VALUES (770,'carreaux',17167,0,NULL);
INSERT INTO `map_tiles` VALUES (771,'carreaux',17168,0,NULL);
INSERT INTO `map_tiles` VALUES (772,'carreaux',17169,0,NULL);
INSERT INTO `map_tiles` VALUES (773,'carreaux',17170,0,NULL);
INSERT INTO `map_tiles` VALUES (774,'carreaux',17171,0,NULL);
INSERT INTO `map_tiles` VALUES (775,'carreaux',17172,0,NULL);
INSERT INTO `map_tiles` VALUES (776,'carreaux',17173,0,NULL);
INSERT INTO `map_tiles` VALUES (777,'carreaux',17174,0,NULL);
INSERT INTO `map_tiles` VALUES (778,'carreaux',17175,0,NULL);
INSERT INTO `map_tiles` VALUES (779,'carreaux',17176,0,NULL);
INSERT INTO `map_tiles` VALUES (780,'carreaux',17177,0,NULL);
INSERT INTO `map_tiles` VALUES (781,'carreaux',17178,0,NULL);
INSERT INTO `map_tiles` VALUES (782,'carreaux',17179,0,NULL);
INSERT INTO `map_tiles` VALUES (783,'carreaux',17180,0,NULL);
INSERT INTO `map_tiles` VALUES (784,'carreaux',17181,0,NULL);
INSERT INTO `map_tiles` VALUES (785,'carreaux',17182,0,NULL);
INSERT INTO `map_tiles` VALUES (786,'carreaux',17183,0,NULL);
INSERT INTO `map_tiles` VALUES (787,'carreaux',17184,0,NULL);
INSERT INTO `map_tiles` VALUES (788,'carreaux',17185,0,NULL);
INSERT INTO `map_tiles` VALUES (789,'carreaux',17186,0,NULL);
INSERT INTO `map_tiles` VALUES (790,'carreaux',17187,0,NULL);
INSERT INTO `map_tiles` VALUES (791,'carreaux',17188,0,NULL);
INSERT INTO `map_tiles` VALUES (792,'carreaux',17189,0,NULL);
INSERT INTO `map_tiles` VALUES (793,'carreaux',17190,0,NULL);
INSERT INTO `map_tiles` VALUES (794,'carreaux',17191,0,NULL);
INSERT INTO `map_tiles` VALUES (795,'carreaux',17192,0,NULL);
INSERT INTO `map_tiles` VALUES (796,'carreaux',17193,0,NULL);
INSERT INTO `map_tiles` VALUES (797,'carreaux',17194,0,NULL);
INSERT INTO `map_tiles` VALUES (798,'carreaux',17195,0,NULL);
INSERT INTO `map_tiles` VALUES (799,'carreaux',17196,0,NULL);
INSERT INTO `map_tiles` VALUES (800,'carreaux',17197,0,NULL);
INSERT INTO `map_tiles` VALUES (801,'carreaux',17198,0,NULL);
INSERT INTO `map_tiles` VALUES (802,'carreaux',17199,0,NULL);
INSERT INTO `map_tiles` VALUES (803,'carreaux',17200,0,NULL);
INSERT INTO `map_tiles` VALUES (804,'carreaux',17201,0,NULL);
INSERT INTO `map_tiles` VALUES (805,'carreaux',17202,0,NULL);
INSERT INTO `map_tiles` VALUES (806,'carreaux',17203,0,NULL);
INSERT INTO `map_tiles` VALUES (807,'carreaux',17204,0,NULL);
INSERT INTO `map_tiles` VALUES (808,'carreaux',17205,0,NULL);
INSERT INTO `map_tiles` VALUES (809,'carreaux',17206,0,NULL);
INSERT INTO `map_tiles` VALUES (810,'carreaux',17207,0,NULL);
INSERT INTO `map_tiles` VALUES (811,'carreaux',17208,0,NULL);
INSERT INTO `map_tiles` VALUES (812,'carreaux',17209,0,NULL);
INSERT INTO `map_tiles` VALUES (813,'carreaux',17210,0,NULL);
INSERT INTO `map_tiles` VALUES (814,'carreaux',17211,0,NULL);
INSERT INTO `map_tiles` VALUES (815,'carreaux',17212,0,NULL);
INSERT INTO `map_tiles` VALUES (816,'carreaux',17213,0,NULL);
INSERT INTO `map_tiles` VALUES (817,'carreaux',17214,0,NULL);
INSERT INTO `map_tiles` VALUES (818,'carreaux',17215,0,NULL);
INSERT INTO `map_tiles` VALUES (819,'carreaux',17216,0,NULL);
INSERT INTO `map_tiles` VALUES (820,'eryn_dolen',17223,0,NULL);
INSERT INTO `map_tiles` VALUES (821,'eryn_dolen',17224,0,NULL);
INSERT INTO `map_tiles` VALUES (822,'eryn_dolen',17225,0,NULL);
INSERT INTO `map_tiles` VALUES (823,'eryn_dolen',17226,0,NULL);
INSERT INTO `map_tiles` VALUES (824,'eryn_dolen',17227,0,NULL);
INSERT INTO `map_tiles` VALUES (825,'eryn_dolen',17229,0,NULL);
INSERT INTO `map_tiles` VALUES (826,'eryn_dolen',17219,0,NULL);
INSERT INTO `map_tiles` VALUES (827,'eryn_dolen',17230,0,NULL);
INSERT INTO `map_tiles` VALUES (828,'eryn_dolen',17231,0,NULL);
INSERT INTO `map_tiles` VALUES (829,'eryn_dolen',17232,0,NULL);
INSERT INTO `map_tiles` VALUES (830,'eryn_dolen',17233,0,NULL);
INSERT INTO `map_tiles` VALUES (831,'eryn_dolen',17220,0,NULL);
INSERT INTO `map_tiles` VALUES (832,'eryn_dolen',17234,0,NULL);
INSERT INTO `map_tiles` VALUES (833,'eryn_dolen',17235,0,NULL);
INSERT INTO `map_tiles` VALUES (834,'eryn_dolen',17236,0,NULL);
INSERT INTO `map_tiles` VALUES (835,'eryn_dolen',17237,0,NULL);
INSERT INTO `map_tiles` VALUES (836,'eryn_dolen',17241,0,NULL);
INSERT INTO `map_tiles` VALUES (837,'eryn_dolen',17242,0,NULL);
INSERT INTO `map_tiles` VALUES (838,'lac_pegasus',17243,0,NULL);
INSERT INTO `map_tiles` VALUES (839,'lac_pegasus',17244,0,NULL);
INSERT INTO `map_tiles` VALUES (840,'lac_pegasus',17245,0,NULL);
INSERT INTO `map_tiles` VALUES (841,'lac_pegasus',17246,0,NULL);
INSERT INTO `map_tiles` VALUES (842,'lac_pegasus',17218,0,NULL);
INSERT INTO `map_tiles` VALUES (843,'lac_pegasus',17247,0,NULL);
INSERT INTO `map_tiles` VALUES (844,'lac_pegasus',17248,0,NULL);
INSERT INTO `map_tiles` VALUES (845,'lac_pegasus',17249,0,NULL);
INSERT INTO `map_tiles` VALUES (846,'lac_pegasus',17250,0,NULL);
INSERT INTO `map_tiles` VALUES (847,'lac_pegasus',17251,0,NULL);
INSERT INTO `map_tiles` VALUES (848,'lac_pegasus',17252,0,NULL);
INSERT INTO `map_tiles` VALUES (849,'lac_pegasus',17253,0,NULL);
INSERT INTO `map_tiles` VALUES (850,'lac_pegasus',17254,0,NULL);
INSERT INTO `map_tiles` VALUES (851,'lac_pegasus',17255,0,NULL);
INSERT INTO `map_tiles` VALUES (852,'lac_pegasus',17217,0,NULL);
INSERT INTO `map_tiles` VALUES (853,'lac_pegasus',17256,0,NULL);
INSERT INTO `map_tiles` VALUES (854,'lac_pegasus',17257,0,NULL);
INSERT INTO `map_tiles` VALUES (855,'lac_pegasus',17258,0,NULL);
INSERT INTO `map_tiles` VALUES (856,'lac_pegasus',17259,0,NULL);
INSERT INTO `map_tiles` VALUES (857,'lac_pegasus',17260,0,NULL);
INSERT INTO `map_tiles` VALUES (858,'lac_pegasus',17261,0,NULL);
INSERT INTO `map_tiles` VALUES (859,'lac_pegasus',17262,0,NULL);
INSERT INTO `map_tiles` VALUES (860,'eryn_dolen',17263,0,NULL);
INSERT INTO `map_tiles` VALUES (861,'eryn_dolen',17264,0,NULL);
INSERT INTO `map_tiles` VALUES (862,'eryn_dolen',17265,0,NULL);
INSERT INTO `map_tiles` VALUES (863,'eryn_dolen',17266,0,NULL);
INSERT INTO `map_tiles` VALUES (864,'eryn_dolen',17267,0,NULL);
INSERT INTO `map_tiles` VALUES (865,'eryn_dolen',17269,0,NULL);
INSERT INTO `map_tiles` VALUES (866,'eryn_dolen',17270,0,NULL);
INSERT INTO `map_tiles` VALUES (867,'eryn_dolen',17271,0,NULL);
INSERT INTO `map_tiles` VALUES (868,'eryn_dolen',17272,0,NULL);
INSERT INTO `map_tiles` VALUES (869,'eryn_dolen',17273,0,NULL);
INSERT INTO `map_tiles` VALUES (870,'eryn_dolen',17274,0,NULL);
INSERT INTO `map_tiles` VALUES (871,'eryn_dolen',17275,0,NULL);
INSERT INTO `map_tiles` VALUES (872,'eryn_dolen',17277,0,NULL);
INSERT INTO `map_tiles` VALUES (873,'eryn_dolen',17278,0,NULL);
INSERT INTO `map_tiles` VALUES (874,'eryn_dolen',17279,0,NULL);
INSERT INTO `map_tiles` VALUES (875,'eryn_dolen',17280,0,NULL);
INSERT INTO `map_tiles` VALUES (876,'eryn_dolen',17281,0,NULL);
INSERT INTO `map_tiles` VALUES (877,'eryn_dolen',17282,0,NULL);
INSERT INTO `map_tiles` VALUES (878,'eryn_dolen',17283,0,NULL);
INSERT INTO `map_tiles` VALUES (879,'eryn_dolen',17284,0,NULL);
INSERT INTO `map_tiles` VALUES (880,'eryn_dolen',17285,0,NULL);
INSERT INTO `map_tiles` VALUES (881,'eryn_dolen',17286,0,NULL);
INSERT INTO `map_tiles` VALUES (882,'eryn_dolen',17287,0,NULL);
INSERT INTO `map_tiles` VALUES (883,'eryn_dolen',17289,0,NULL);
INSERT INTO `map_tiles` VALUES (884,'eryn_dolen',17290,0,NULL);
INSERT INTO `map_tiles` VALUES (885,'eryn_dolen',17291,0,NULL);
INSERT INTO `map_tiles` VALUES (886,'eryn_dolen',17292,0,NULL);
INSERT INTO `map_tiles` VALUES (887,'eryn_dolen',17293,0,NULL);
INSERT INTO `map_tiles` VALUES (888,'eryn_dolen',17294,0,NULL);
INSERT INTO `map_tiles` VALUES (889,'eryn_dolen',17296,0,NULL);
INSERT INTO `map_tiles` VALUES (890,'eryn_dolen',17297,0,NULL);
INSERT INTO `map_tiles` VALUES (891,'lac_pegasus',17298,0,NULL);
INSERT INTO `map_tiles` VALUES (892,'lac_pegasus',17299,0,NULL);
INSERT INTO `map_tiles` VALUES (893,'lac_pegasus',17300,0,NULL);
INSERT INTO `map_tiles` VALUES (894,'lac_pegasus',17301,0,NULL);
INSERT INTO `map_tiles` VALUES (895,'lac_pegasus',17302,0,NULL);
INSERT INTO `map_tiles` VALUES (896,'lac_pegasus',17303,0,NULL);
INSERT INTO `map_tiles` VALUES (897,'lac_pegasus',17304,0,NULL);
INSERT INTO `map_tiles` VALUES (898,'lac_pegasus',17305,0,NULL);
INSERT INTO `map_tiles` VALUES (899,'lac_pegasus',17306,0,NULL);
INSERT INTO `map_tiles` VALUES (900,'lac_pegasus',17307,0,NULL);
INSERT INTO `map_tiles` VALUES (901,'lac_pegasus',17308,0,NULL);
INSERT INTO `map_tiles` VALUES (902,'lac_pegasus',17309,0,NULL);
INSERT INTO `map_tiles` VALUES (903,'lac_pegasus',17310,0,NULL);
INSERT INTO `map_tiles` VALUES (904,'lac_pegasus',17311,0,NULL);
INSERT INTO `map_tiles` VALUES (905,'lac_pegasus',17312,0,NULL);
INSERT INTO `map_tiles` VALUES (906,'lac_pegasus',17276,0,NULL);
INSERT INTO `map_tiles` VALUES (907,'lac_pegasus',17288,0,NULL);
INSERT INTO `map_tiles` VALUES (908,'lac_pegasus',17313,0,NULL);
INSERT INTO `map_tiles` VALUES (909,'lac_pegasus',17314,0,NULL);
INSERT INTO `map_tiles` VALUES (910,'lac_pegasus',17315,0,NULL);
INSERT INTO `map_tiles` VALUES (911,'lac_pegasus',17316,0,NULL);
INSERT INTO `map_tiles` VALUES (912,'lac_pegasus',17268,0,NULL);
INSERT INTO `map_tiles` VALUES (913,'lac_pegasus',17295,0,NULL);
INSERT INTO `map_tiles` VALUES (914,'lac_pegasus',17317,0,NULL);
INSERT INTO `map_tiles` VALUES (915,'lac_pegasus',17318,0,NULL);
INSERT INTO `map_tiles` VALUES (916,'lac_pegasus',17319,0,NULL);
INSERT INTO `map_tiles` VALUES (917,'lac_pegasus',17320,0,NULL);
INSERT INTO `map_tiles` VALUES (918,'lac_pegasus',17321,0,NULL);
INSERT INTO `map_tiles` VALUES (919,'lac_pegasus',17322,0,NULL);
INSERT INTO `map_tiles` VALUES (920,'lac_pegasus',17323,0,NULL);
INSERT INTO `map_tiles` VALUES (921,'lac_pegasus',17324,0,NULL);
INSERT INTO `map_tiles` VALUES (922,'lac_pegasus',17325,0,NULL);
INSERT INTO `map_tiles` VALUES (923,'lac_pegasus',17326,0,NULL);
INSERT INTO `map_tiles` VALUES (924,'eryn_dolen',17327,0,NULL);
INSERT INTO `map_tiles` VALUES (925,'eryn_dolen',17328,0,NULL);
INSERT INTO `map_tiles` VALUES (926,'eryn_dolen',17329,0,NULL);
INSERT INTO `map_tiles` VALUES (927,'eryn_dolen',17330,0,NULL);
INSERT INTO `map_tiles` VALUES (928,'eryn_dolen',17331,0,NULL);
INSERT INTO `map_tiles` VALUES (929,'eryn_dolen',17332,0,NULL);
INSERT INTO `map_tiles` VALUES (930,'eryn_dolen',17333,0,NULL);
INSERT INTO `map_tiles` VALUES (931,'eryn_dolen',17334,0,NULL);
INSERT INTO `map_tiles` VALUES (932,'eryn_dolen',17335,0,NULL);
INSERT INTO `map_tiles` VALUES (933,'eryn_dolen',17336,0,NULL);
INSERT INTO `map_tiles` VALUES (934,'eryn_dolen',17337,0,NULL);
INSERT INTO `map_tiles` VALUES (935,'eryn_dolen',17338,0,NULL);
INSERT INTO `map_tiles` VALUES (936,'eryn_dolen',17339,0,NULL);
INSERT INTO `map_tiles` VALUES (937,'eryn_dolen',17340,0,NULL);
INSERT INTO `map_tiles` VALUES (938,'eryn_dolen',17341,0,NULL);
INSERT INTO `map_tiles` VALUES (939,'eryn_dolen',17342,0,NULL);
INSERT INTO `map_tiles` VALUES (940,'eryn_dolen',17343,0,NULL);
INSERT INTO `map_tiles` VALUES (941,'eryn_dolen',17344,0,NULL);
INSERT INTO `map_tiles` VALUES (942,'eryn_dolen',17345,0,NULL);
INSERT INTO `map_tiles` VALUES (943,'eryn_dolen',17346,0,NULL);
INSERT INTO `map_tiles` VALUES (944,'eryn_dolen',17347,0,NULL);
INSERT INTO `map_tiles` VALUES (945,'eryn_dolen',17348,0,NULL);
INSERT INTO `map_tiles` VALUES (946,'eryn_dolen',17349,0,NULL);
INSERT INTO `map_tiles` VALUES (947,'eryn_dolen',17350,0,NULL);
INSERT INTO `map_tiles` VALUES (948,'eryn_dolen',17351,0,NULL);
INSERT INTO `map_tiles` VALUES (949,'eryn_dolen',17352,0,NULL);
INSERT INTO `map_tiles` VALUES (950,'eryn_dolen',17353,0,NULL);
INSERT INTO `map_tiles` VALUES (951,'eryn_dolen',17354,0,NULL);
INSERT INTO `map_tiles` VALUES (952,'eryn_dolen',17355,0,NULL);
INSERT INTO `map_tiles` VALUES (953,'eryn_dolen',17356,0,NULL);
INSERT INTO `map_tiles` VALUES (954,'eryn_dolen',17357,0,NULL);
INSERT INTO `map_tiles` VALUES (955,'eryn_dolen',17358,0,NULL);
INSERT INTO `map_tiles` VALUES (956,'eryn_dolen',17359,0,NULL);
INSERT INTO `map_tiles` VALUES (957,'eryn_dolen',17360,0,NULL);
INSERT INTO `map_tiles` VALUES (958,'eryn_dolen',17361,0,NULL);
INSERT INTO `map_tiles` VALUES (959,'eryn_dolen',17362,0,NULL);
INSERT INTO `map_tiles` VALUES (960,'eryn_dolen',17363,0,NULL);
INSERT INTO `map_tiles` VALUES (961,'eryn_dolen',17364,0,NULL);
INSERT INTO `map_tiles` VALUES (962,'eryn_dolen',17365,0,NULL);
INSERT INTO `map_tiles` VALUES (963,'eryn_dolen',17366,0,NULL);
INSERT INTO `map_tiles` VALUES (964,'eryn_dolen',17367,0,NULL);
INSERT INTO `map_tiles` VALUES (965,'eryn_dolen',17368,0,NULL);
INSERT INTO `map_tiles` VALUES (966,'eryn_dolen',17369,0,NULL);
INSERT INTO `map_tiles` VALUES (967,'eryn_dolen',17370,0,NULL);
INSERT INTO `map_tiles` VALUES (968,'eryn_dolen',17371,0,NULL);
INSERT INTO `map_tiles` VALUES (969,'eryn_dolen',17372,0,NULL);
INSERT INTO `map_tiles` VALUES (970,'eryn_dolen',17373,0,NULL);
INSERT INTO `map_tiles` VALUES (971,'eryn_dolen',17374,0,NULL);
INSERT INTO `map_tiles` VALUES (972,'eryn_dolen',17375,0,NULL);
INSERT INTO `map_tiles` VALUES (973,'eryn_dolen',17376,0,NULL);
INSERT INTO `map_tiles` VALUES (974,'eryn_dolen',17238,0,NULL);
INSERT INTO `map_tiles` VALUES (975,'eryn_dolen',17239,0,NULL);
INSERT INTO `map_tiles` VALUES (976,'eryn_dolen',17240,0,NULL);
INSERT INTO `map_tiles` VALUES (977,'eryn_dolen',17222,0,NULL);
INSERT INTO `map_tiles` VALUES (978,'eryn_dolen',17221,0,NULL);
INSERT INTO `map_tiles` VALUES (979,'eryn_dolen',17228,0,NULL);
INSERT INTO `map_tiles` VALUES (980,'lac_cenedril',17377,0,NULL);
INSERT INTO `map_tiles` VALUES (981,'lac_cenedril',17378,0,NULL);
INSERT INTO `map_tiles` VALUES (982,'lac_cenedril',17379,0,NULL);
INSERT INTO `map_tiles` VALUES (983,'lac_cenedril',17380,0,NULL);
INSERT INTO `map_tiles` VALUES (984,'lac_cenedril',17381,0,NULL);
INSERT INTO `map_tiles` VALUES (985,'lac_cenedril',17382,0,NULL);
INSERT INTO `map_tiles` VALUES (986,'lac_cenedril',17383,0,NULL);
INSERT INTO `map_tiles` VALUES (987,'lac_cenedril',17384,0,NULL);
INSERT INTO `map_tiles` VALUES (988,'lac_cenedril',17385,0,NULL);
INSERT INTO `map_tiles` VALUES (989,'lac_cenedril',17386,0,NULL);
INSERT INTO `map_tiles` VALUES (990,'lac_cenedril',17387,0,NULL);
INSERT INTO `map_tiles` VALUES (991,'lac_cenedril',17388,0,NULL);
INSERT INTO `map_tiles` VALUES (992,'lac_cenedril',17389,0,NULL);
INSERT INTO `map_tiles` VALUES (993,'lac_cenedril',17390,0,NULL);
INSERT INTO `map_tiles` VALUES (994,'lac_cenedril',17391,0,NULL);
INSERT INTO `map_tiles` VALUES (995,'lac_cenedril',17392,0,NULL);
INSERT INTO `map_tiles` VALUES (996,'lac_cenedril',17393,0,NULL);
INSERT INTO `map_tiles` VALUES (997,'lac_cenedril',17394,0,NULL);
INSERT INTO `map_tiles` VALUES (998,'lac_cenedril',17395,0,NULL);
INSERT INTO `map_tiles` VALUES (999,'lac_cenedril',17396,0,NULL);
INSERT INTO `map_tiles` VALUES (1000,'lac_cenedril',17397,0,NULL);
INSERT INTO `map_tiles` VALUES (1001,'lac_cenedril',17398,0,NULL);
INSERT INTO `map_tiles` VALUES (1002,'lac_cenedril',17399,0,NULL);
INSERT INTO `map_tiles` VALUES (1003,'lac_cenedril',17400,0,NULL);
INSERT INTO `map_tiles` VALUES (1004,'lac_cenedril',17401,0,NULL);
INSERT INTO `map_tiles` VALUES (1005,'lac_cenedril',17402,0,NULL);
INSERT INTO `map_tiles` VALUES (1006,'lac_cenedril',17403,0,NULL);
INSERT INTO `map_tiles` VALUES (1007,'lac_cenedril',17404,0,NULL);
INSERT INTO `map_tiles` VALUES (1008,'lac_cenedril',17405,0,NULL);
INSERT INTO `map_tiles` VALUES (1009,'lac_cenedril',17406,0,NULL);
INSERT INTO `map_tiles` VALUES (1010,'lac_cenedril',17407,0,NULL);
INSERT INTO `map_tiles` VALUES (1011,'lac_cenedril',17408,0,NULL);
INSERT INTO `map_tiles` VALUES (1012,'lac_cenedril',17409,0,NULL);
INSERT INTO `map_tiles` VALUES (1013,'lac_cenedril',17410,0,NULL);
INSERT INTO `map_tiles` VALUES (1014,'lac_cenedril',17411,0,NULL);
INSERT INTO `map_tiles` VALUES (1015,'lac_cenedril',17412,0,NULL);
INSERT INTO `map_tiles` VALUES (1016,'lac_cenedril',17413,0,NULL);
INSERT INTO `map_tiles` VALUES (1017,'lac_cenedril',17414,0,NULL);
INSERT INTO `map_tiles` VALUES (1018,'lac_cenedril',17415,0,NULL);
INSERT INTO `map_tiles` VALUES (1019,'lac_cenedril',17416,0,NULL);
INSERT INTO `map_tiles` VALUES (1020,'lac_cenedril',17417,0,NULL);
INSERT INTO `map_tiles` VALUES (1021,'eryn_dolen',17418,0,NULL);
INSERT INTO `map_tiles` VALUES (1022,'eryn_dolen',17419,0,NULL);
INSERT INTO `map_tiles` VALUES (1023,'eryn_dolen',17420,0,NULL);
INSERT INTO `map_tiles` VALUES (1024,'eryn_dolen',17421,0,NULL);
INSERT INTO `map_tiles` VALUES (1025,'eryn_dolen',17422,0,NULL);
INSERT INTO `map_tiles` VALUES (1026,'eryn_dolen',17423,0,NULL);
INSERT INTO `map_tiles` VALUES (1027,'eryn_dolen',17424,0,NULL);
INSERT INTO `map_tiles` VALUES (1028,'eryn_dolen',17425,0,NULL);
INSERT INTO `map_tiles` VALUES (1029,'eryn_dolen',17426,0,NULL);
INSERT INTO `map_tiles` VALUES (1030,'eryn_dolen',17427,0,NULL);
INSERT INTO `map_tiles` VALUES (1031,'eryn_dolen',17428,0,NULL);
INSERT INTO `map_tiles` VALUES (1032,'eryn_dolen',17429,0,NULL);
INSERT INTO `map_tiles` VALUES (1033,'eryn_dolen',17430,0,NULL);
INSERT INTO `map_tiles` VALUES (1034,'eryn_dolen',17431,0,NULL);
INSERT INTO `map_tiles` VALUES (1035,'eryn_dolen',17432,0,NULL);
INSERT INTO `map_tiles` VALUES (1036,'eryn_dolen',17433,0,NULL);
INSERT INTO `map_tiles` VALUES (1037,'eryn_dolen',17434,0,NULL);
INSERT INTO `map_tiles` VALUES (1038,'eryn_dolen',17435,0,NULL);
INSERT INTO `map_tiles` VALUES (1039,'eryn_dolen',17436,0,NULL);
INSERT INTO `map_tiles` VALUES (1040,'eryn_dolen',17437,0,NULL);
/*!40000 ALTER TABLE `map_tiles` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `map_triggers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `coords_id` int(11) NOT NULL,
  `params` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `coords_id` (`coords_id`),
  CONSTRAINT `map_triggers_ibfk_2` FOREIGN KEY (`coords_id`) REFERENCES `coords` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=180 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `map_triggers` DISABLE KEYS */;
INSERT INTO `map_triggers` VALUES (1,'forbidden',60,'');
INSERT INTO `map_triggers` VALUES (2,'forbidden',61,'');
INSERT INTO `map_triggers` VALUES (3,'forbidden',62,'');
INSERT INTO `map_triggers` VALUES (4,'forbidden',63,'');
INSERT INTO `map_triggers` VALUES (5,'forbidden',64,'');
INSERT INTO `map_triggers` VALUES (6,'forbidden',65,'');
INSERT INTO `map_triggers` VALUES (7,'forbidden',24,'');
INSERT INTO `map_triggers` VALUES (8,'forbidden',25,'');
INSERT INTO `map_triggers` VALUES (9,'forbidden',66,'');
INSERT INTO `map_triggers` VALUES (10,'forbidden',67,'');
INSERT INTO `map_triggers` VALUES (11,'forbidden',68,'');
INSERT INTO `map_triggers` VALUES (12,'forbidden',26,'');
INSERT INTO `map_triggers` VALUES (13,'forbidden',27,'');
INSERT INTO `map_triggers` VALUES (14,'forbidden',28,'');
INSERT INTO `map_triggers` VALUES (15,'forbidden',69,'');
INSERT INTO `map_triggers` VALUES (16,'forbidden',70,'');
INSERT INTO `map_triggers` VALUES (17,'forbidden',71,'');
INSERT INTO `map_triggers` VALUES (18,'forbidden',50,'');
INSERT INTO `map_triggers` VALUES (19,'forbidden',72,'');
INSERT INTO `map_triggers` VALUES (20,'forbidden',49,'');
INSERT INTO `map_triggers` VALUES (21,'forbidden',73,'');
INSERT INTO `map_triggers` VALUES (22,'forbidden',74,'');
INSERT INTO `map_triggers` VALUES (23,'forbidden',75,'');
INSERT INTO `map_triggers` VALUES (24,'forbidden',76,'');
INSERT INTO `map_triggers` VALUES (25,'forbidden',77,'');
INSERT INTO `map_triggers` VALUES (26,'forbidden',78,'');
INSERT INTO `map_triggers` VALUES (27,'forbidden',79,'');
INSERT INTO `map_triggers` VALUES (28,'forbidden',80,'');
INSERT INTO `map_triggers` VALUES (29,'forbidden',81,'');
INSERT INTO `map_triggers` VALUES (30,'forbidden',82,'');
INSERT INTO `map_triggers` VALUES (31,'forbidden',83,'');
INSERT INTO `map_triggers` VALUES (32,'forbidden',84,'');
INSERT INTO `map_triggers` VALUES (33,'forbidden',85,'');
INSERT INTO `map_triggers` VALUES (34,'forbidden',86,'');
INSERT INTO `map_triggers` VALUES (35,'forbidden',87,'');
INSERT INTO `map_triggers` VALUES (36,'forbidden',88,'');
INSERT INTO `map_triggers` VALUES (37,'forbidden',89,'');
INSERT INTO `map_triggers` VALUES (38,'forbidden',90,'');
INSERT INTO `map_triggers` VALUES (39,'forbidden',51,'');
INSERT INTO `map_triggers` VALUES (40,'forbidden',52,'');
INSERT INTO `map_triggers` VALUES (41,'forbidden',91,'');
INSERT INTO `map_triggers` VALUES (42,'forbidden',48,'');
INSERT INTO `map_triggers` VALUES (43,'forbidden',92,'');
INSERT INTO `map_triggers` VALUES (44,'forbidden',47,'');
INSERT INTO `map_triggers` VALUES (45,'forbidden',93,'');
INSERT INTO `map_triggers` VALUES (46,'forbidden',94,'');
INSERT INTO `map_triggers` VALUES (47,'forbidden',95,'');
INSERT INTO `map_triggers` VALUES (48,'forbidden',96,'');
INSERT INTO `map_triggers` VALUES (49,'rez',136,'');
INSERT INTO `map_triggers` VALUES (50,'rez',135,'');
INSERT INTO `map_triggers` VALUES (51,'rez',160,'');
INSERT INTO `map_triggers` VALUES (52,'rez',161,'');
INSERT INTO `map_triggers` VALUES (53,'rez',162,'');
INSERT INTO `map_triggers` VALUES (54,'rez',163,'');
INSERT INTO `map_triggers` VALUES (55,'rez',164,'');
INSERT INTO `map_triggers` VALUES (56,'rez',165,'');
INSERT INTO `map_triggers` VALUES (57,'rez',166,'');
INSERT INTO `map_triggers` VALUES (58,'rez',149,'');
INSERT INTO `map_triggers` VALUES (59,'rez',167,'');
INSERT INTO `map_triggers` VALUES (60,'rez',148,'');
INSERT INTO `map_triggers` VALUES (61,'forbidden',168,'');
INSERT INTO `map_triggers` VALUES (62,'forbidden',169,'');
INSERT INTO `map_triggers` VALUES (63,'forbidden',170,'');
INSERT INTO `map_triggers` VALUES (64,'forbidden',171,'');
INSERT INTO `map_triggers` VALUES (65,'forbidden',172,'');
INSERT INTO `map_triggers` VALUES (66,'forbidden',125,'');
INSERT INTO `map_triggers` VALUES (67,'forbidden',126,'');
INSERT INTO `map_triggers` VALUES (68,'forbidden',173,'');
INSERT INTO `map_triggers` VALUES (69,'forbidden',174,'');
INSERT INTO `map_triggers` VALUES (70,'forbidden',175,'');
INSERT INTO `map_triggers` VALUES (71,'forbidden',127,'');
INSERT INTO `map_triggers` VALUES (72,'forbidden',128,'');
INSERT INTO `map_triggers` VALUES (73,'forbidden',129,'');
INSERT INTO `map_triggers` VALUES (74,'forbidden',176,'');
INSERT INTO `map_triggers` VALUES (75,'forbidden',177,'');
INSERT INTO `map_triggers` VALUES (76,'forbidden',178,'');
INSERT INTO `map_triggers` VALUES (77,'forbidden',146,'');
INSERT INTO `map_triggers` VALUES (78,'forbidden',179,'');
INSERT INTO `map_triggers` VALUES (79,'forbidden',147,'');
INSERT INTO `map_triggers` VALUES (80,'forbidden',180,'');
INSERT INTO `map_triggers` VALUES (81,'forbidden',181,'');
INSERT INTO `map_triggers` VALUES (82,'forbidden',182,'');
INSERT INTO `map_triggers` VALUES (83,'forbidden',183,'');
INSERT INTO `map_triggers` VALUES (84,'forbidden',184,'');
INSERT INTO `map_triggers` VALUES (85,'forbidden',185,'');
INSERT INTO `map_triggers` VALUES (86,'forbidden',186,'');
INSERT INTO `map_triggers` VALUES (87,'forbidden',187,'');
INSERT INTO `map_triggers` VALUES (88,'forbidden',188,'');
INSERT INTO `map_triggers` VALUES (89,'forbidden',189,'');
INSERT INTO `map_triggers` VALUES (90,'forbidden',190,'');
INSERT INTO `map_triggers` VALUES (91,'forbidden',191,'');
INSERT INTO `map_triggers` VALUES (92,'forbidden',192,'');
INSERT INTO `map_triggers` VALUES (93,'forbidden',193,'');
INSERT INTO `map_triggers` VALUES (94,'forbidden',194,'');
INSERT INTO `map_triggers` VALUES (95,'forbidden',195,'');
INSERT INTO `map_triggers` VALUES (96,'forbidden',196,'');
INSERT INTO `map_triggers` VALUES (97,'forbidden',197,'');
INSERT INTO `map_triggers` VALUES (98,'forbidden',108,'');
INSERT INTO `map_triggers` VALUES (99,'forbidden',121,'');
INSERT INTO `map_triggers` VALUES (100,'forbidden',122,'');
INSERT INTO `map_triggers` VALUES (101,'forbidden',123,'');
INSERT INTO `map_triggers` VALUES (102,'forbidden',120,'');
INSERT INTO `map_triggers` VALUES (103,'forbidden',119,'');
INSERT INTO `map_triggers` VALUES (104,'forbidden',118,'');
INSERT INTO `map_triggers` VALUES (105,'forbidden',105,'');
INSERT INTO `map_triggers` VALUES (106,'forbidden',198,'');
INSERT INTO `map_triggers` VALUES (107,'forbidden',152,'');
INSERT INTO `map_triggers` VALUES (108,'forbidden',153,'');
INSERT INTO `map_triggers` VALUES (109,'forbidden',199,'');
INSERT INTO `map_triggers` VALUES (110,'forbidden',151,'');
INSERT INTO `map_triggers` VALUES (111,'forbidden',200,'');
INSERT INTO `map_triggers` VALUES (112,'forbidden',150,'');
INSERT INTO `map_triggers` VALUES (113,'forbidden',201,'');
INSERT INTO `map_triggers` VALUES (114,'forbidden',202,'');
INSERT INTO `map_triggers` VALUES (115,'forbidden',203,'');
INSERT INTO `map_triggers` VALUES (116,'forbidden',204,'');
INSERT INTO `map_triggers` VALUES (117,'rez',12680,'');
INSERT INTO `map_triggers` VALUES (118,'rez',12681,'');
INSERT INTO `map_triggers` VALUES (119,'rez',12697,'');
INSERT INTO `map_triggers` VALUES (120,'rez',12698,'');
INSERT INTO `map_triggers` VALUES (121,'tp',12671,'x,y,-1,nidhogg');
INSERT INTO `map_triggers` VALUES (122,'forbidden',17362,'');
INSERT INTO `map_triggers` VALUES (123,'forbidden',17361,'');
INSERT INTO `map_triggers` VALUES (124,'forbidden',17358,'');
INSERT INTO `map_triggers` VALUES (125,'forbidden',17357,'');
INSERT INTO `map_triggers` VALUES (126,'forbidden',17354,'');
INSERT INTO `map_triggers` VALUES (127,'forbidden',17353,'');
INSERT INTO `map_triggers` VALUES (128,'forbidden',17345,'');
INSERT INTO `map_triggers` VALUES (129,'forbidden',17344,'');
INSERT INTO `map_triggers` VALUES (130,'forbidden',17343,'');
INSERT INTO `map_triggers` VALUES (131,'forbidden',17342,'');
INSERT INTO `map_triggers` VALUES (132,'forbidden',17341,'');
INSERT INTO `map_triggers` VALUES (133,'forbidden',17340,'');
INSERT INTO `map_triggers` VALUES (134,'forbidden',17335,'');
INSERT INTO `map_triggers` VALUES (135,'forbidden',17332,'');
INSERT INTO `map_triggers` VALUES (136,'forbidden',17290,'');
INSERT INTO `map_triggers` VALUES (137,'forbidden',17278,'');
INSERT INTO `map_triggers` VALUES (138,'forbidden',17279,'');
INSERT INTO `map_triggers` VALUES (139,'forbidden',17280,'');
INSERT INTO `map_triggers` VALUES (140,'forbidden',17241,'');
INSERT INTO `map_triggers` VALUES (141,'forbidden',17242,'');
INSERT INTO `map_triggers` VALUES (142,'forbidden',17235,'');
INSERT INTO `map_triggers` VALUES (143,'forbidden',17234,'');
INSERT INTO `map_triggers` VALUES (144,'forbidden',17233,'');
INSERT INTO `map_triggers` VALUES (145,'forbidden',17232,'');
INSERT INTO `map_triggers` VALUES (146,'forbidden',17231,'');
INSERT INTO `map_triggers` VALUES (147,'forbidden',17230,'');
INSERT INTO `map_triggers` VALUES (148,'forbidden',17229,'');
INSERT INTO `map_triggers` VALUES (149,'forbidden',17225,'');
INSERT INTO `map_triggers` VALUES (150,'forbidden',17226,'');
INSERT INTO `map_triggers` VALUES (151,'forbidden',17227,'');
INSERT INTO `map_triggers` VALUES (152,'forbidden',17263,'');
INSERT INTO `map_triggers` VALUES (153,'forbidden',17264,'');
INSERT INTO `map_triggers` VALUES (154,'forbidden',17265,'');
INSERT INTO `map_triggers` VALUES (155,'forbidden',17267,'');
INSERT INTO `map_triggers` VALUES (156,'forbidden',17296,'');
INSERT INTO `map_triggers` VALUES (157,'forbidden',17367,'');
INSERT INTO `map_triggers` VALUES (158,'forbidden',17366,'');
INSERT INTO `map_triggers` VALUES (159,'forbidden',17372,'');
INSERT INTO `map_triggers` VALUES (160,'forbidden',17294,'');
INSERT INTO `map_triggers` VALUES (161,'forbidden',17269,'');
INSERT INTO `map_triggers` VALUES (162,'forbidden',17270,'');
INSERT INTO `map_triggers` VALUES (163,'forbidden',17271,'');
INSERT INTO `map_triggers` VALUES (164,'forbidden',17272,'');
INSERT INTO `map_triggers` VALUES (165,'forbidden',17282,'');
INSERT INTO `map_triggers` VALUES (166,'forbidden',17375,'');
INSERT INTO `map_triggers` VALUES (167,'forbidden',17374,'');
INSERT INTO `map_triggers` VALUES (168,'forbidden',17373,'');
INSERT INTO `map_triggers` VALUES (169,'forbidden',17376,'');
INSERT INTO `map_triggers` VALUES (170,'forbidden',17327,'');
INSERT INTO `map_triggers` VALUES (171,'forbidden',17283,'');
INSERT INTO `map_triggers` VALUES (172,'forbidden',17284,'');
INSERT INTO `map_triggers` VALUES (173,'forbidden',17273,'');
INSERT INTO `map_triggers` VALUES (174,'forbidden',17274,'');
INSERT INTO `map_triggers` VALUES (175,'forbidden',17287,'');
INSERT INTO `map_triggers` VALUES (176,'forbidden',17331,'');
INSERT INTO `map_triggers` VALUES (177,'forbidden',17330,'');
INSERT INTO `map_triggers` VALUES (178,'forbidden',17329,'');
INSERT INTO `map_triggers` VALUES (179,'forbidden',17328,'');
/*!40000 ALTER TABLE `map_triggers` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `map_walls` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `player_id` int(11) DEFAULT NULL,
  `coords_id` int(11) NOT NULL,
  `damages` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `player_id` (`player_id`),
  KEY `coords_id` (`coords_id`),
  CONSTRAINT `map_walls_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`),
  CONSTRAINT `map_walls_ibfk_2` FOREIGN KEY (`coords_id`) REFERENCES `coords` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=418 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `map_walls` DISABLE KEYS */;
INSERT INTO `map_walls` VALUES (116,'gaia',NULL,59,0);
INSERT INTO `map_walls` VALUES (117,'gaia',NULL,111,0);
INSERT INTO `map_walls` VALUES (118,'pilier',NULL,15504,0);
INSERT INTO `map_walls` VALUES (119,'pilier',NULL,15506,0);
INSERT INTO `map_walls` VALUES (120,'pilier',NULL,15508,0);
INSERT INTO `map_walls` VALUES (121,'pilier',NULL,15510,0);
INSERT INTO `map_walls` VALUES (122,'pilier',NULL,15512,0);
INSERT INTO `map_walls` VALUES (123,'pilier',NULL,15514,0);
INSERT INTO `map_walls` VALUES (124,'pilier',NULL,15516,0);
INSERT INTO `map_walls` VALUES (125,'pilier',NULL,15518,0);
INSERT INTO `map_walls` VALUES (126,'cocotier3',NULL,16609,0);
INSERT INTO `map_walls` VALUES (127,'cocotier3',NULL,16830,0);
INSERT INTO `map_walls` VALUES (128,'cocotier3',NULL,16827,0);
INSERT INTO `map_walls` VALUES (129,'cocotier3',NULL,16772,0);
INSERT INTO `map_walls` VALUES (130,'cocotier3',NULL,16641,0);
INSERT INTO `map_walls` VALUES (131,'cocotier3',NULL,16630,0);
INSERT INTO `map_walls` VALUES (132,'cocotier3',NULL,16666,0);
INSERT INTO `map_walls` VALUES (133,'cocotier2',NULL,16837,0);
INSERT INTO `map_walls` VALUES (134,'cocotier2',NULL,16741,0);
INSERT INTO `map_walls` VALUES (135,'cocotier2',NULL,16648,0);
INSERT INTO `map_walls` VALUES (136,'cocotier2',NULL,16627,0);
INSERT INTO `map_walls` VALUES (137,'cocotier2',NULL,16787,0);
INSERT INTO `map_walls` VALUES (138,'cocotier2',NULL,16871,0);
INSERT INTO `map_walls` VALUES (139,'cocotier2',NULL,16860,0);
INSERT INTO `map_walls` VALUES (140,'cocotier2',NULL,16831,0);
INSERT INTO `map_walls` VALUES (141,'cocotier2',NULL,16838,0);
INSERT INTO `map_walls` VALUES (142,'cocotier1',NULL,16863,0);
INSERT INTO `map_walls` VALUES (143,'cocotier1',NULL,16614,0);
INSERT INTO `map_walls` VALUES (144,'cocotier1',NULL,16777,0);
INSERT INTO `map_walls` VALUES (145,'cocotier1',NULL,16678,0);
INSERT INTO `map_walls` VALUES (146,'cocotier1',NULL,15565,0);
INSERT INTO `map_walls` VALUES (147,'cocotier1',NULL,16647,0);
INSERT INTO `map_walls` VALUES (148,'cocotier1',NULL,16762,0);
INSERT INTO `map_walls` VALUES (149,'cocotier1',NULL,16749,0);
INSERT INTO `map_walls` VALUES (150,'cocotier1',NULL,16653,0);
INSERT INTO `map_walls` VALUES (151,'cocotier1',NULL,16744,0);
INSERT INTO `map_walls` VALUES (152,'cocotier1',NULL,16622,0);
INSERT INTO `map_walls` VALUES (153,'cocotier1',NULL,16637,0);
INSERT INTO `map_walls` VALUES (154,'pierre1',NULL,16670,0);
INSERT INTO `map_walls` VALUES (155,'pierre1',NULL,16808,0);
INSERT INTO `map_walls` VALUES (156,'pierre2',NULL,16721,0);
INSERT INTO `map_walls` VALUES (157,'pierre1',NULL,19784,0);
INSERT INTO `map_walls` VALUES (158,'pierre1',NULL,19850,0);
INSERT INTO `map_walls` VALUES (159,'pierre1',NULL,19818,0);
INSERT INTO `map_walls` VALUES (160,'pierre1',NULL,19819,0);
INSERT INTO `map_walls` VALUES (161,'cocotier3',NULL,16843,0);
INSERT INTO `map_walls` VALUES (162,'cocotier3',NULL,16651,0);
INSERT INTO `map_walls` VALUES (163,'cocotier3',NULL,16681,0);
INSERT INTO `map_walls` VALUES (164,'cocotier3',NULL,16783,0);
INSERT INTO `map_walls` VALUES (165,'mur_pierre_bleue',NULL,35138,0);
INSERT INTO `map_walls` VALUES (166,'mur_pierre_bleue',NULL,35137,0);
INSERT INTO `map_walls` VALUES (167,'mur_pierre_bleue',NULL,35136,0);
INSERT INTO `map_walls` VALUES (168,'mur_pierre_bleue',NULL,35135,0);
INSERT INTO `map_walls` VALUES (169,'mur_pierre_bleue',NULL,35134,0);
INSERT INTO `map_walls` VALUES (170,'mur_pierre_bleue',NULL,35133,0);
INSERT INTO `map_walls` VALUES (171,'mur_pierre_bleue',NULL,35132,0);
INSERT INTO `map_walls` VALUES (172,'mur_pierre_bleue',NULL,35131,0);
INSERT INTO `map_walls` VALUES (173,'mur_pierre_bleue',NULL,35130,0);
INSERT INTO `map_walls` VALUES (174,'mur_pierre_bleue',NULL,35129,0);
INSERT INTO `map_walls` VALUES (175,'mur_pierre_bleue',NULL,35128,0);
INSERT INTO `map_walls` VALUES (176,'mur_pierre_bleue',NULL,35127,0);
INSERT INTO `map_walls` VALUES (177,'mur_pierre_bleue',NULL,35126,0);
INSERT INTO `map_walls` VALUES (178,'mur_pierre_bleue',NULL,35125,0);
INSERT INTO `map_walls` VALUES (179,'mur_pierre_bleue',NULL,35124,0);
INSERT INTO `map_walls` VALUES (180,'mur_pierre_bleue',NULL,35123,0);
INSERT INTO `map_walls` VALUES (181,'mur_pierre_bleue',NULL,35122,0);
INSERT INTO `map_walls` VALUES (182,'mur_pierre_bleue',NULL,35121,0);
INSERT INTO `map_walls` VALUES (183,'statues6',NULL,35114,0);
INSERT INTO `map_walls` VALUES (184,'statue_heroique',NULL,35118,0);
INSERT INTO `map_walls` VALUES (185,'statue_heroique',NULL,35117,0);
INSERT INTO `map_walls` VALUES (186,'pierre_noire2',NULL,35043,0);
INSERT INTO `map_walls` VALUES (187,'pierre_noire2',NULL,35036,0);
INSERT INTO `map_walls` VALUES (188,'pierre_noire2',NULL,35052,0);
INSERT INTO `map_walls` VALUES (189,'pierre_noire2',NULL,35058,0);
INSERT INTO `map_walls` VALUES (190,'pierre_noire2',NULL,35077,0);
INSERT INTO `map_walls` VALUES (191,'pierre_noire2',NULL,35076,0);
INSERT INTO `map_walls` VALUES (192,'pierre_noire2',NULL,35105,0);
INSERT INTO `map_walls` VALUES (193,'pierre1',NULL,35014,0);
INSERT INTO `map_walls` VALUES (194,'pierre1',NULL,35042,0);
INSERT INTO `map_walls` VALUES (195,'pierre1',NULL,35091,0);
INSERT INTO `map_walls` VALUES (196,'pierre1',NULL,35092,0);
INSERT INTO `map_walls` VALUES (197,'pierre2',NULL,35067,0);
INSERT INTO `map_walls` VALUES (198,'pierre2',NULL,35022,0);
INSERT INTO `map_walls` VALUES (199,'pierre2',NULL,35040,0);
INSERT INTO `map_walls` VALUES (200,'pierre2',NULL,35062,0);
INSERT INTO `map_walls` VALUES (201,'mur_pierre',NULL,35139,0);
INSERT INTO `map_walls` VALUES (202,'mur_pierre',NULL,35140,0);
INSERT INTO `map_walls` VALUES (203,'mur_pierre',NULL,35141,0);
INSERT INTO `map_walls` VALUES (204,'mur_pierre',NULL,35142,0);
INSERT INTO `map_walls` VALUES (205,'mur_pierre',NULL,35143,0);
INSERT INTO `map_walls` VALUES (206,'mur_pierre',NULL,35144,0);
INSERT INTO `map_walls` VALUES (207,'mur_pierre',NULL,35145,0);
INSERT INTO `map_walls` VALUES (208,'mur_pierre',NULL,35146,0);
INSERT INTO `map_walls` VALUES (209,'mur_pierre',NULL,35147,0);
INSERT INTO `map_walls` VALUES (210,'mur_pierre',NULL,35148,0);
INSERT INTO `map_walls` VALUES (211,'mur_pierre',NULL,35149,0);
INSERT INTO `map_walls` VALUES (212,'mur_pierre',NULL,35150,0);
INSERT INTO `map_walls` VALUES (213,'mur_pierre',NULL,35119,0);
INSERT INTO `map_walls` VALUES (214,'mur_pierre',NULL,35120,0);
INSERT INTO `map_walls` VALUES (215,'statues6',NULL,35112,0);
INSERT INTO `map_walls` VALUES (216,'pierre1',NULL,35030,0);
INSERT INTO `map_walls` VALUES (217,'pierre3',NULL,35066,0);
INSERT INTO `map_walls` VALUES (218,'pierre3',NULL,35098,0);
INSERT INTO `map_walls` VALUES (219,'statues5',NULL,37488,0);
INSERT INTO `map_walls` VALUES (220,'statues5',NULL,37482,0);
INSERT INTO `map_walls` VALUES (221,'pilier',NULL,37485,0);
INSERT INTO `map_walls` VALUES (222,'pilier',NULL,37487,0);
INSERT INTO `map_walls` VALUES (223,'pilier',NULL,37483,0);
INSERT INTO `map_walls` VALUES (224,'pilier',NULL,37504,0);
INSERT INTO `map_walls` VALUES (225,'pilier',NULL,37495,0);
INSERT INTO `map_walls` VALUES (226,'pilier',NULL,37502,0);
INSERT INTO `map_walls` VALUES (227,'statue_heroique',NULL,37491,0);
INSERT INTO `map_walls` VALUES (228,'statue_heroique',NULL,37501,0);
INSERT INTO `map_walls` VALUES (229,'table_bois',NULL,38901,0);
INSERT INTO `map_walls` VALUES (230,'coffre_bois',NULL,38897,0);
INSERT INTO `map_walls` VALUES (231,'mur_pierre_bleue',NULL,17022,0);
INSERT INTO `map_walls` VALUES (232,'mur_pierre_bleue',NULL,17023,0);
INSERT INTO `map_walls` VALUES (233,'mur_pierre_bleue',NULL,17024,0);
INSERT INTO `map_walls` VALUES (234,'mur_pierre_bleue',NULL,17025,0);
INSERT INTO `map_walls` VALUES (235,'mur_pierre_bleue',NULL,17026,0);
INSERT INTO `map_walls` VALUES (236,'mur_pierre_bleue',NULL,17027,0);
INSERT INTO `map_walls` VALUES (237,'mur_pierre_bleue',NULL,17028,0);
INSERT INTO `map_walls` VALUES (238,'mur_pierre_bleue',NULL,17029,0);
INSERT INTO `map_walls` VALUES (239,'mur_pierre_bleue',NULL,17030,0);
INSERT INTO `map_walls` VALUES (240,'mur_pierre_bleue',NULL,17031,0);
INSERT INTO `map_walls` VALUES (241,'mur_pierre_bleue',NULL,17032,0);
INSERT INTO `map_walls` VALUES (242,'mur_pierre_bleue',NULL,17033,0);
INSERT INTO `map_walls` VALUES (243,'mur_pierre_bleue',NULL,17034,0);
INSERT INTO `map_walls` VALUES (244,'mur_pierre_bleue',NULL,17035,0);
INSERT INTO `map_walls` VALUES (245,'mur_pierre_bleue',NULL,17036,0);
INSERT INTO `map_walls` VALUES (246,'mur_pierre_bleue',NULL,17038,0);
INSERT INTO `map_walls` VALUES (247,'mur_pierre_bleue',NULL,17039,0);
INSERT INTO `map_walls` VALUES (248,'mur_pierre_bleue',NULL,17040,0);
INSERT INTO `map_walls` VALUES (249,'mur_pierre_bleue',NULL,17041,0);
INSERT INTO `map_walls` VALUES (250,'mur_pierre_bleue',NULL,17042,0);
INSERT INTO `map_walls` VALUES (251,'mur_pierre_bleue',NULL,17043,0);
INSERT INTO `map_walls` VALUES (252,'mur_pierre_bleue',NULL,17044,0);
INSERT INTO `map_walls` VALUES (253,'mur_pierre_bleue',NULL,17045,0);
INSERT INTO `map_walls` VALUES (254,'table_bois',NULL,17013,0);
INSERT INTO `map_walls` VALUES (255,'mur_pierre_bleue',NULL,17046,0);
INSERT INTO `map_walls` VALUES (256,'mur_pierre_bleue',NULL,17047,0);
INSERT INTO `map_walls` VALUES (257,'mur_pierre_bleue',NULL,17048,0);
INSERT INTO `map_walls` VALUES (258,'mur_pierre_bleue',NULL,17049,0);
INSERT INTO `map_walls` VALUES (259,'mur_pierre_bleue',NULL,17050,0);
INSERT INTO `map_walls` VALUES (260,'mur_pierre_bleue',NULL,17051,0);
INSERT INTO `map_walls` VALUES (261,'mur_pierre_bleue',NULL,17052,0);
INSERT INTO `map_walls` VALUES (262,'mur_pierre_bleue',NULL,17053,0);
INSERT INTO `map_walls` VALUES (263,'mur_pierre_bleue',NULL,17054,0);
INSERT INTO `map_walls` VALUES (264,'mur_pierre_bleue',NULL,17055,0);
INSERT INTO `map_walls` VALUES (265,'mur_pierre_bleue',NULL,17056,0);
INSERT INTO `map_walls` VALUES (266,'mur_pierre_bleue',NULL,17057,0);
INSERT INTO `map_walls` VALUES (267,'mur_pierre_bleue',NULL,17058,0);
INSERT INTO `map_walls` VALUES (268,'mur_pierre_bleue',NULL,17059,0);
INSERT INTO `map_walls` VALUES (269,'mur_pierre_bleue',NULL,17060,0);
INSERT INTO `map_walls` VALUES (270,'mur_pierre_bleue',NULL,17061,0);
INSERT INTO `map_walls` VALUES (271,'mur_pierre_bleue',NULL,17062,0);
INSERT INTO `map_walls` VALUES (272,'mur_pierre_bleue',NULL,17063,0);
INSERT INTO `map_walls` VALUES (273,'mur_pierre_bleue',NULL,17064,0);
INSERT INTO `map_walls` VALUES (274,'mur_pierre_bleue',NULL,17065,0);
INSERT INTO `map_walls` VALUES (275,'mur_pierre_bleue',NULL,17066,0);
INSERT INTO `map_walls` VALUES (276,'mur_pierre_bleue',NULL,17067,0);
INSERT INTO `map_walls` VALUES (277,'mur_pierre_bleue',NULL,17068,0);
INSERT INTO `map_walls` VALUES (278,'mur_pierre_bleue',NULL,17069,0);
INSERT INTO `map_walls` VALUES (279,'mur_pierre_bleue',NULL,17070,0);
INSERT INTO `map_walls` VALUES (280,'mur_pierre_bleue',NULL,17071,0);
INSERT INTO `map_walls` VALUES (281,'mur_pierre_bleue',NULL,17072,0);
INSERT INTO `map_walls` VALUES (282,'mur_pierre_bleue',NULL,17073,0);
INSERT INTO `map_walls` VALUES (283,'mur_pierre_bleue',NULL,17074,0);
INSERT INTO `map_walls` VALUES (284,'mur_pierre_bleue',NULL,17075,0);
INSERT INTO `map_walls` VALUES (285,'mur_pierre_bleue',NULL,17076,0);
INSERT INTO `map_walls` VALUES (286,'mur_pierre_bleue',NULL,17077,0);
INSERT INTO `map_walls` VALUES (287,'mur_pierre_bleue',NULL,17078,0);
INSERT INTO `map_walls` VALUES (288,'mur_pierre_bleue',NULL,17079,0);
INSERT INTO `map_walls` VALUES (289,'mur_pierre_bleue',NULL,17080,0);
INSERT INTO `map_walls` VALUES (290,'mur_pierre_bleue',NULL,17081,0);
INSERT INTO `map_walls` VALUES (291,'mur_pierre_bleue',NULL,17082,0);
INSERT INTO `map_walls` VALUES (292,'mur_pierre_bleue',NULL,17083,0);
INSERT INTO `map_walls` VALUES (293,'mur_pierre_bleue',NULL,17084,0);
INSERT INTO `map_walls` VALUES (294,'mur_pierre_bleue',NULL,17085,0);
INSERT INTO `map_walls` VALUES (295,'mur_pierre_bleue',NULL,17086,0);
INSERT INTO `map_walls` VALUES (296,'mur_pierre_bleue',NULL,17087,0);
INSERT INTO `map_walls` VALUES (297,'mur_pierre_bleue',NULL,17088,0);
INSERT INTO `map_walls` VALUES (298,'mur_pierre_bleue',NULL,17089,0);
INSERT INTO `map_walls` VALUES (299,'mur_pierre_bleue',NULL,17090,0);
INSERT INTO `map_walls` VALUES (300,'mur_pierre_bleue',NULL,17091,0);
INSERT INTO `map_walls` VALUES (301,'mur_pierre_bleue',NULL,17092,0);
INSERT INTO `map_walls` VALUES (302,'mur_pierre_bleue',NULL,17093,0);
INSERT INTO `map_walls` VALUES (303,'mur_pierre_bleue',NULL,17094,0);
INSERT INTO `map_walls` VALUES (304,'mur_pierre_bleue',NULL,17095,0);
INSERT INTO `map_walls` VALUES (305,'mur_pierre_bleue',NULL,17096,0);
INSERT INTO `map_walls` VALUES (306,'mur_pierre_bleue',NULL,17097,0);
INSERT INTO `map_walls` VALUES (307,'mur_pierre_bleue',NULL,17137,0);
INSERT INTO `map_walls` VALUES (308,'mur_pierre_bleue',NULL,17138,0);
INSERT INTO `map_walls` VALUES (309,'mur_pierre_bleue',NULL,17140,0);
INSERT INTO `map_walls` VALUES (310,'mur_pierre_bleue',NULL,17141,0);
INSERT INTO `map_walls` VALUES (311,'mur_pierre_bleue',NULL,17142,0);
INSERT INTO `map_walls` VALUES (312,'coffre_metal',NULL,17198,0);
INSERT INTO `map_walls` VALUES (313,'coffre_metal',NULL,17201,0);
INSERT INTO `map_walls` VALUES (314,'coffre_metal',NULL,17202,0);
INSERT INTO `map_walls` VALUES (315,'tonneau',NULL,17203,0);
INSERT INTO `map_walls` VALUES (316,'coffre_bois',NULL,17200,0);
INSERT INTO `map_walls` VALUES (317,'coffre_humain',NULL,17207,0);
INSERT INTO `map_walls` VALUES (318,'pilier',NULL,17119,0);
INSERT INTO `map_walls` VALUES (319,'pilier',NULL,17107,0);
INSERT INTO `map_walls` VALUES (320,'pilier',NULL,17217,0);
INSERT INTO `map_walls` VALUES (321,'pilier',NULL,17218,0);
INSERT INTO `map_walls` VALUES (322,'pilier',NULL,17136,0);
INSERT INTO `map_walls` VALUES (323,'pilier',NULL,17147,0);
INSERT INTO `map_walls` VALUES (324,'statue_heroique',NULL,17114,0);
INSERT INTO `map_walls` VALUES (325,'statue_heroique',NULL,17102,0);
INSERT INTO `map_walls` VALUES (326,'statues1',NULL,17219,0);
INSERT INTO `map_walls` VALUES (327,'statues1',NULL,17220,0);
INSERT INTO `map_walls` VALUES (328,'arbre2',NULL,17339,0);
INSERT INTO `map_walls` VALUES (329,'arbre2',NULL,17336,0);
INSERT INTO `map_walls` VALUES (330,'arbre1',NULL,17370,0);
INSERT INTO `map_walls` VALUES (331,'arbre3',NULL,17365,0);
INSERT INTO `map_walls` VALUES (332,'arbre3',NULL,17238,0);
INSERT INTO `map_walls` VALUES (333,'arbre3',NULL,17240,0);
INSERT INTO `map_walls` VALUES (334,'arbre3',NULL,17222,0);
INSERT INTO `map_walls` VALUES (335,'arbre2',NULL,17221,0);
INSERT INTO `map_walls` VALUES (336,'arbre2',NULL,17239,0);
INSERT INTO `map_walls` VALUES (337,'arbre1',NULL,17228,0);
INSERT INTO `map_walls` VALUES (338,'pierre1',NULL,17275,0);
INSERT INTO `map_walls` VALUES (339,'pierre2',NULL,17277,0);
INSERT INTO `map_walls` VALUES (340,'pierre3',NULL,17372,0);
INSERT INTO `map_walls` VALUES (341,'pancarte',NULL,17346,0);
INSERT INTO `map_walls` VALUES (342,'pancarte',NULL,17352,0);
INSERT INTO `map_walls` VALUES (343,'mur_bois',NULL,17121,0);
INSERT INTO `map_walls` VALUES (344,'mur_bois',NULL,17122,0);
INSERT INTO `map_walls` VALUES (345,'mur_bois',NULL,17123,0);
INSERT INTO `map_walls` VALUES (346,'mur_pierre_bleue',NULL,17139,0);
INSERT INTO `map_walls` VALUES (347,'mur_bois_petrifie',NULL,17143,0);
INSERT INTO `map_walls` VALUES (348,'mur_bois_petrifie',NULL,17148,0);
INSERT INTO `map_walls` VALUES (349,'arbre1',NULL,17418,0);
INSERT INTO `map_walls` VALUES (350,'arbre1',NULL,17436,0);
INSERT INTO `map_walls` VALUES (351,'arbre1',NULL,17420,0);
INSERT INTO `map_walls` VALUES (352,'arbre2',NULL,17419,0);
INSERT INTO `map_walls` VALUES (353,'arbre2',NULL,17435,0);
INSERT INTO `map_walls` VALUES (354,'arbre3',NULL,17437,0);
INSERT INTO `map_walls` VALUES (355,'arbre3',NULL,17179,0);
INSERT INTO `map_walls` VALUES (356,'pierre3',NULL,17177,0);
INSERT INTO `map_walls` VALUES (357,'mur_pierre',NULL,51590,0);
INSERT INTO `map_walls` VALUES (358,'mur_pierre',NULL,51591,0);
INSERT INTO `map_walls` VALUES (359,'mur_pierre',NULL,51592,0);
INSERT INTO `map_walls` VALUES (360,'mur_pierre',NULL,51593,0);
INSERT INTO `map_walls` VALUES (361,'mur_pierre',NULL,51594,0);
INSERT INTO `map_walls` VALUES (362,'mur_pierre',NULL,51595,0);
INSERT INTO `map_walls` VALUES (363,'mur_pierre',NULL,51596,0);
INSERT INTO `map_walls` VALUES (364,'mur_pierre',NULL,51597,0);
INSERT INTO `map_walls` VALUES (365,'mur_pierre',NULL,51598,0);
INSERT INTO `map_walls` VALUES (372,'mur_pierre',NULL,51662,0);
INSERT INTO `map_walls` VALUES (373,'mur_pierre',NULL,51663,0);
INSERT INTO `map_walls` VALUES (374,'mur_pierre',NULL,51664,0);
INSERT INTO `map_walls` VALUES (375,'mur_pierre',NULL,51665,0);
INSERT INTO `map_walls` VALUES (376,'mur_pierre',NULL,51666,0);
INSERT INTO `map_walls` VALUES (377,'mur_pierre',NULL,51667,0);
INSERT INTO `map_walls` VALUES (378,'mur_pierre',NULL,51668,0);
INSERT INTO `map_walls` VALUES (379,'mur_pierre',NULL,51669,0);
INSERT INTO `map_walls` VALUES (380,'mur_pierre',NULL,51670,0);
INSERT INTO `map_walls` VALUES (388,'mur_pierre',NULL,51599,0);
INSERT INTO `map_walls` VALUES (389,'mur_pierre',NULL,51608,0);
INSERT INTO `map_walls` VALUES (390,'mur_pierre',NULL,51617,0);
INSERT INTO `map_walls` VALUES (391,'mur_pierre',NULL,51626,0);
INSERT INTO `map_walls` VALUES (392,'mur_pierre',NULL,51635,0);
INSERT INTO `map_walls` VALUES (393,'mur_pierre',NULL,51644,0);
INSERT INTO `map_walls` VALUES (394,'mur_pierre',NULL,51653,0);
INSERT INTO `map_walls` VALUES (403,'mur_pierre',NULL,51607,0);
INSERT INTO `map_walls` VALUES (404,'mur_pierre',NULL,51616,0);
INSERT INTO `map_walls` VALUES (405,'mur_pierre',NULL,51625,0);
INSERT INTO `map_walls` VALUES (406,'mur_pierre',NULL,51634,0);
INSERT INTO `map_walls` VALUES (407,'mur_pierre',NULL,51643,0);
INSERT INTO `map_walls` VALUES (408,'mur_pierre',NULL,51652,0);
INSERT INTO `map_walls` VALUES (409,'mur_pierre',NULL,51661,0);
INSERT INTO `map_walls` VALUES (417,'arbre1',NULL,51639,-1);
/*!40000 ALTER TABLE `map_walls` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `outcome_instructions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` varchar(50) NOT NULL,
  `parameters` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`parameters`)),
  `orderIndex` int(11) NOT NULL DEFAULT 0,
  `outcome_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_9DA2AC6FF5E9B83B` (`outcome_id`),
  CONSTRAINT `FK_9DA2AC6FF5E9B83B` FOREIGN KEY (`outcome_id`) REFERENCES `action_outcomes` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=137 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `outcome_instructions` DISABLE KEYS */;
INSERT INTO `outcome_instructions` VALUES (1,'lifeloss','{ \"actorDamagesTrait\": \"f\", \"targetDamagesTrait\": \"e\" }',0,1);
INSERT INTO `outcome_instructions` VALUES (3,'damageobject','{}',0,1);
INSERT INTO `outcome_instructions` VALUES (4,'lifeloss','{ \"actorDamagesTrait\": \"f\", \"targetDamagesTrait\": \"e\", \"distance\": true }',0,3);
INSERT INTO `outcome_instructions` VALUES (6,'lifeloss','{ \"actorDamagesTrait\": \"m\", \"targetDamagesTrait\": \"m\", \"bonusDamagesTrait\": 3, \"distance\": true }',0,4);
INSERT INTO `outcome_instructions` VALUES (8,'lifeloss','{ \"actorDamagesTrait\": \"m\", \"targetDamagesTrait\": \"m\", \"bonusDamagesTrait\": 8 }',0,7);
INSERT INTO `outcome_instructions` VALUES (10,'healing','{ \"actorHealingTrait\": \"agi\" }',0,9);
INSERT INTO `outcome_instructions` VALUES (11,'lifeloss','{ \"actorDamagesTrait\": \"f\", \"targetDamagesTrait\": \"e\", \"bonusDamagesTrait\": \"m\", \"bonusDefenseTrait\": \"m\" }',3,10);
INSERT INTO `outcome_instructions` VALUES (13,'teleport','{ \"coords\": \"target\" }',1,10);
INSERT INTO `outcome_instructions` VALUES (14,'object','{\"action\":\"steal\", \"object\": 1 }',0,11);
INSERT INTO `outcome_instructions` VALUES (15,'applystatus','{ \"adrenaline\": true, \"duration\": 172800 }',0,12);
INSERT INTO `outcome_instructions` VALUES (16,'rest','{}',10,13);
INSERT INTO `outcome_instructions` VALUES (17,'player','{\"carac\":\"malus\", \"value\": \"r\", \"player\": \"actor\"}',0,13);
INSERT INTO `outcome_instructions` VALUES (18,'player','{\"carac\": \"mvt\", \"value\" : 1, \"player\": \"actor\"}',0,14);
INSERT INTO `outcome_instructions` VALUES (19,'applystatus','{ \"cle_de_bras\": true, \"player\": \"actor\", \"duration\": 0 }',0,15);
INSERT INTO `outcome_instructions` VALUES (20,'player','{\"carac\": \"foi\", \"player\": \"actor\"}',0,16);
INSERT INTO `outcome_instructions` VALUES (21,'resource',NULL,0,17);
INSERT INTO `outcome_instructions` VALUES (22,'onlylog',NULL,0,18);
INSERT INTO `outcome_instructions` VALUES (23,'damageobject','{}',0,3);
INSERT INTO `outcome_instructions` VALUES (24,'damageobject','{}',0,10);
INSERT INTO `outcome_instructions` VALUES (25,'removeaction','{\"action\":\"tuto/attaquer\"}',0,19);
INSERT INTO `outcome_instructions` VALUES (26,'addraceactions','{}',1,19);
INSERT INTO `outcome_instructions` VALUES (27,'teleport','{ \"coords\": \"x,y,z,gaia2\" }',2,19);
INSERT INTO `outcome_instructions` VALUES (28,'lifeloss','{ \"actorDamagesTrait\": \"f\", \"targetDamagesTrait\": \"e\", \"bonusDamagesTrait\": 2, \"targetIgnore\": [\"tronc\"] }',3,20);
INSERT INTO `outcome_instructions` VALUES (29,'applystatus','{ \"corruption_du_bois\": true, \"player\": \"target\", \"duration\": 259200 }',0,21);
/*!40000 ALTER TABLE `outcome_instructions` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `players` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `player_type` varchar(20) NOT NULL DEFAULT 'real',
  `display_id` int(11) NOT NULL DEFAULT 0,
  `name` varchar(255) NOT NULL DEFAULT '',
  `psw` varchar(255) NOT NULL DEFAULT '',
  `mail` varchar(255) NOT NULL DEFAULT '',
  `plain_mail` varchar(255) NOT NULL DEFAULT '',
  `coords_id` int(11) NOT NULL DEFAULT 0,
  `race` varchar(255) NOT NULL DEFAULT '',
  `xp` int(11) NOT NULL DEFAULT 0,
  `pi` int(11) NOT NULL DEFAULT 0,
  `pr` int(11) NOT NULL DEFAULT 0,
  `malus` int(11) NOT NULL DEFAULT 0,
  `energie` int(11) NOT NULL DEFAULT 0,
  `godId` int(11) NOT NULL DEFAULT 0,
  `pf` int(11) NOT NULL DEFAULT 0,
  `rank` int(11) NOT NULL DEFAULT 1,
  `avatar` varchar(255) NOT NULL DEFAULT '',
  `portrait` varchar(255) NOT NULL DEFAULT '',
  `text` text NOT NULL DEFAULT 'Je suis nouveau, frappez-moi!',
  `story` text NOT NULL DEFAULT 'Je préfère garder cela pour moi.',
  `quest` varchar(255) DEFAULT 'gaia',
  `faction` varchar(255) NOT NULL DEFAULT '',
  `factionRole` int(11) NOT NULL DEFAULT 0,
  `secretFaction` varchar(255) NOT NULL DEFAULT '',
  `secretFactionRole` int(11) NOT NULL DEFAULT 0,
  `nextTurnTime` int(11) NOT NULL DEFAULT 0,
  `registerTime` int(11) NOT NULL DEFAULT 0,
  `lastActionTime` int(11) NOT NULL DEFAULT 0,
  `lastLoginTime` int(11) NOT NULL DEFAULT 0,
  `antiBerserkTime` int(11) NOT NULL DEFAULT 0,
  `lastTravelTime` int(11) NOT NULL DEFAULT 0,
  `bonus_points` int(11) NOT NULL DEFAULT 0,
  `email_bonus` tinyint(1) DEFAULT 0,
  `visible` varchar(255) DEFAULT NULL,
  `tutorial_session_id` varchar(36) DEFAULT NULL COMMENT 'Tutorial session UUID (for tutorial players)',
  `real_player_id_ref` int(11) DEFAULT NULL COMMENT 'Real player ID reference (for tutorial players)',
  PRIMARY KEY (`id`),
  KEY `coords_id` (`coords_id`),
  KEY `idx_type_display` (`player_type`,`display_id`),
  KEY `idx_player_type` (`player_type`),
  KEY `idx_tutorial_session` (`tutorial_session_id`),
  KEY `idx_real_player_id_ref` (`real_player_id_ref`),
  CONSTRAINT `players_ibfk_1` FOREIGN KEY (`coords_id`) REFERENCES `coords` (`id`),
  CONSTRAINT `fk_players_real_player_id_ref` FOREIGN KEY (`real_player_id_ref`) REFERENCES `players` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `players` DISABLE KEYS */;
INSERT INTO `players` VALUES (-999999,'npc',999999,'Gaïa','','','',51631,'dieu',0,0,0,0,100,0,0,1,'img/avatars/dieu/25.png','img/portraits/dieu/1.jpeg','Gaïa, déesse de la Terre, guide les nouveaux joueurs dans leur apprentissage.','Je préfère garder cela pour moi.','gaia','',0,'',0,0,0,0,0,0,0,0,0,NULL,NULL,NULL);
INSERT INTO `players` VALUES (-1,'npc',1,'Gaïa','$2y$10$m35XbOC9buOw7ZH/gB2k.ubYl7vEDYYjgTmDyLcGUNt15Q9LaBILe','','',15,'lutin',10,10,0,0,0,0,0,1,'img/avatars/ame/lutin.webp','img/portraits/ame/1.jpeg','Je suis nouveau, frappez-moi!','Je préfère garder cela pour moi.','gaia','saruta_et_freres',0,'',0,1744286400,0,0,0,16200,0,0,0,NULL,NULL,NULL);
INSERT INTO `players` VALUES (1,'real',1,'Cradek','$2y$10$m35XbOC9buOw7ZH/gB2k.ubYl7vEDYYjgTmDyLcGUNt15Q9LaBILe','$2y$10$hkduB0wnA8nfn2C.ck6UA.b6jr56K9WeBDel33IokN/rtogNXQ8C2','',17009,'nain',5906,99,20,0,7,-1,2,5,'img/avatars/nain/5.png','img/portraits/nain/45.jpeg','Je suis nouveau, frappez-moi!','Je préfère garder cela pour moi.','gaia','forge_sacree',0,'',0,1744540273,1736117307,1744478783,1744536431,1744430285,1744414037,0,0,NULL,NULL,NULL);
INSERT INTO `players` VALUES (2,'real',2,'Dorna','$2y$10$XJm1A0RZWGRbhvDlUyOP8e/O0hhDLLUwU.VJM00GbmWjydKqeoczy','$2y$10$pVJivan0Lhqg.x0OSWQzaulIWVr.BPJ.c3Q992jtWsy61FXH84wNS','',15318,'nain',77,77,0,34,0,0,0,1,'img/avatars/nain/73.png','img/portraits/nain/44.jpeg','Je suis nouveau, frappez-moi!','Je préfère garder cela pour moi.','gaia','forge_sacree',0,'',0,1744540949,1736118099,0,1744534215,16200,1744414042,0,0,NULL,NULL,NULL);
INSERT INTO `players` VALUES (3,'real',3,'Thyrias','$2y$10$SzsgPLFIpn11Rg/TDubHj.fvFLGZdgY.Vwx9VD9GlYYhPu5MR3SeG','$2y$10$1iltdhoPMNdCc9hBNMbdkuVpkb5/Qf7s2CIM0.KgIFwkQmVKXj7p6','',17014,'elfe',135,135,0,0,0,0,0,1,'img/avatars/elfe/70.png','img/portraits/elfe/33.jpeg','Je suis nouveau, frappez-moi!','Je préfère garder cela pour moi.','gaia','eryn_dolen',0,'',0,1745799779,1736120180,0,1744536536,1744584786,1744535727,0,0,NULL,NULL,NULL);
INSERT INTO `players` VALUES (4,'real',4,'ElfeDeTest','$2y$10$Riubh3hGhkkkXmAzJzIRweUbWNscYEdRJXtFFsogw/JjzRRLoTuPS','$2y$10$QnVaD.mKOx6RoGTWFoXlBeAso4ETFp8sQdEw2zq5D.EKtpTkpHgo.','test@test.com',15469,'elfe',25,25,0,2,-2,0,0,1,'img/avatars/elfe/1.png','img/portraits/ame/1.jpeg','Je suis nouveau, frappez-moi!','Je préfère garder cela pour moi.','gaia','eryn_dolen',0,'',0,1744484739,1744482661,1744482820,1744482714,1737697108,1744482820,0,0,NULL,NULL,NULL);
INSERT INTO `players` VALUES (5,'real',5,'Elfe','$2y$10$m35XbOC9buOw7ZH/gB2k.ubYl7vEDYYjgTmDyLcGUNt15Q9LaBILe','$2y$10$QnVaD.mKOx6RoGTWFoXlBeAso4ETFp8sQdEw2zq5D.EKtpTkpHgo.','elfe1@elfe1.com',15457,'elfe',25,25,0,0,1,0,0,1,'img/avatars/elfe/1.png','img/portraits/ame/1.jpeg','Je suis nouveau, frappez-moi!','Je préfère garder cela pour moi.','gaia','eryn_dolen',0,'',0,1744500900,1744483371,1744483652,1744483513,16200,1744483628,0,0,NULL,NULL,NULL);
INSERT INTO `players` VALUES (6,'real',6,'Nain','$2y$10$2/jUdRSDAnoA3Tl0Ph1R1uGqreJ9jycmb8OSC3lGUiqBsF.ETgnGC','$2y$10$XkX/0sNbzGVbf3EYIn.9ue7yexpqIcGF8/hT0Pxm8tR6cVaq3.vsK','test@test.com',17015,'nain',25,25,0,0,0,0,0,1,'img/avatars/nain/1.png','img/portraits/ame/1.jpeg','Je suis nouveau, frappez-moi!','Je préfère garder cela pour moi.','gaia','forge_sacree',0,'',0,1744548869,1744484069,1744484419,1744484074,16200,1744484419,0,0,NULL,NULL,NULL);
/*!40000 ALTER TABLE `players` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `players_actions` (
  `player_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `type` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`player_id`,`name`),
  CONSTRAINT `players_actions_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `players_actions` DISABLE KEYS */;
INSERT INTO `players_actions` VALUES (1,'attaquer','');
INSERT INTO `players_actions` VALUES (1,'courir','');
INSERT INTO `players_actions` VALUES (1,'dmg1/pic_de_pierre','sort');
INSERT INTO `players_actions` VALUES (1,'dps/poings_pierre','sort');
INSERT INTO `players_actions` VALUES (1,'entrainement','');
INSERT INTO `players_actions` VALUES (1,'fouiller','');
INSERT INTO `players_actions` VALUES (1,'prier','');
INSERT INTO `players_actions` VALUES (1,'repos','');
INSERT INTO `players_actions` VALUES (1,'soins/barbier','sort');
INSERT INTO `players_actions` VALUES (1,'special/attaque_sautee','sort');
INSERT INTO `players_actions` VALUES (1,'vol_a_la_tire','');
INSERT INTO `players_actions` VALUES (2,'attaquer','');
INSERT INTO `players_actions` VALUES (2,'courir','');
INSERT INTO `players_actions` VALUES (2,'dmg1/pic_de_pierre','sort');
INSERT INTO `players_actions` VALUES (2,'entrainement','');
INSERT INTO `players_actions` VALUES (2,'fouiller','');
INSERT INTO `players_actions` VALUES (2,'prier','');
INSERT INTO `players_actions` VALUES (2,'repos','');
INSERT INTO `players_actions` VALUES (2,'soins/barbier','sort');
INSERT INTO `players_actions` VALUES (3,'attaquer','');
INSERT INTO `players_actions` VALUES (3,'corrupt/corruption_du_bois','sort');
INSERT INTO `players_actions` VALUES (3,'courir','');
INSERT INTO `players_actions` VALUES (3,'dmg2/frappe_vicieuse','sort');
INSERT INTO `players_actions` VALUES (3,'entrainement','');
INSERT INTO `players_actions` VALUES (3,'fouiller','');
INSERT INTO `players_actions` VALUES (3,'prier','');
INSERT INTO `players_actions` VALUES (3,'repos','');
INSERT INTO `players_actions` VALUES (3,'soins/lien_de_vie','sort');
INSERT INTO `players_actions` VALUES (4,'tuto/attaquer','');
INSERT INTO `players_actions` VALUES (5,'attaquer','');
INSERT INTO `players_actions` VALUES (5,'courir','');
INSERT INTO `players_actions` VALUES (5,'dmg1/fleche_aquatique','sort');
INSERT INTO `players_actions` VALUES (5,'entrainement','');
INSERT INTO `players_actions` VALUES (5,'fouiller','');
INSERT INTO `players_actions` VALUES (5,'prier','');
INSERT INTO `players_actions` VALUES (5,'repos','');
INSERT INTO `players_actions` VALUES (5,'soins/lien_de_vie','sort');
INSERT INTO `players_actions` VALUES (6,'attaquer','');
INSERT INTO `players_actions` VALUES (6,'courir','');
INSERT INTO `players_actions` VALUES (6,'dmg1/pic_de_pierre','sort');
INSERT INTO `players_actions` VALUES (6,'entrainement','');
INSERT INTO `players_actions` VALUES (6,'fouiller','');
INSERT INTO `players_actions` VALUES (6,'prier','');
INSERT INTO `players_actions` VALUES (6,'repos','');
INSERT INTO `players_actions` VALUES (6,'soins/barbier','sort');
/*!40000 ALTER TABLE `players_actions` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `players_assists` (
  `player_id` int(11) NOT NULL,
  `target_id` int(11) NOT NULL,
  `player_rank` int(11) NOT NULL DEFAULT 1,
  `damages` int(11) NOT NULL DEFAULT 1,
  `time` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`player_id`,`target_id`),
  KEY `target_id` (`target_id`),
  CONSTRAINT `players_assists_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`),
  CONSTRAINT `players_assists_ibfk_2` FOREIGN KEY (`target_id`) REFERENCES `players` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `players_assists` DISABLE KEYS */;
/*!40000 ALTER TABLE `players_assists` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `players_banned` (
  `player_id` int(11) NOT NULL AUTO_INCREMENT,
  `ips` text NOT NULL,
  `text` longtext NOT NULL,
  PRIMARY KEY (`player_id`),
  KEY `player_id` (`player_id`),
  CONSTRAINT `players_banned_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `players_banned` DISABLE KEYS */;
/*!40000 ALTER TABLE `players_banned` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `players_bonus` (
  `player_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `n` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`player_id`,`name`),
  CONSTRAINT `players_bonus_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `players_bonus` DISABLE KEYS */;
/*!40000 ALTER TABLE `players_bonus` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `players_connections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `player_id` int(11) NOT NULL,
  `ip` varchar(255) NOT NULL DEFAULT '',
  `time` int(11) NOT NULL DEFAULT 0,
  `footprint` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `player_id` (`player_id`),
  CONSTRAINT `players_connections_fk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `players_connections` DISABLE KEYS */;
/*!40000 ALTER TABLE `players_connections` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `players_effects` (
  `player_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `endTime` int(11) DEFAULT NULL,
  `value` int(11) DEFAULT NULL,
  PRIMARY KEY (`player_id`,`name`),
  CONSTRAINT `players_effects_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `players_effects` DISABLE KEYS */;
/*!40000 ALTER TABLE `players_effects` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `players_followers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `player_id` int(11) NOT NULL,
  `foreground_id` int(11) NOT NULL,
  `params` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `player_id` (`player_id`),
  KEY `foreground_id` (`foreground_id`),
  CONSTRAINT `players_followers_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`),
  CONSTRAINT `players_followers_ibfk_3` FOREIGN KEY (`foreground_id`) REFERENCES `map_foregrounds` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `players_followers` DISABLE KEYS */;
/*!40000 ALTER TABLE `players_followers` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `players_forum_missives` (
  `player_id` int(11) NOT NULL,
  `name` bigint(20) NOT NULL,
  `viewed` int(1) NOT NULL DEFAULT 0,
  `last_post` bigint(20) NOT NULL DEFAULT 0,
  KEY `player_id` (`player_id`),
  CONSTRAINT `players_forum_missives_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `players_forum_missives` DISABLE KEYS */;
INSERT INTO `players_forum_missives` (`player_id`,`name`,`viewed`,`last_post`) VALUES (1,1724908803,1,0);
INSERT INTO `players_forum_missives` (`player_id`,`name`,`viewed`,`last_post`) VALUES (2,1724908803,1,0);
INSERT INTO `players_forum_missives` (`player_id`,`name`,`viewed`,`last_post`) VALUES (3,1724908803,1,0);
INSERT INTO `players_forum_missives` (`player_id`,`name`,`viewed`,`last_post`) VALUES (4,1724908803,0,0);
INSERT INTO `players_forum_missives` (`player_id`,`name`,`viewed`,`last_post`) VALUES (5,1724908803,0,0);
INSERT INTO `players_forum_missives` (`player_id`,`name`,`viewed`,`last_post`) VALUES (6,1724908803,1,0);
/*!40000 ALTER TABLE `players_forum_missives` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `players_forum_rewards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `from_player_id` int(11) NOT NULL,
  `to_player_id` int(11) NOT NULL,
  `postName` varchar(255) NOT NULL DEFAULT '',
  `topName` varchar(255) NOT NULL DEFAULT '',
  `img` varchar(255) NOT NULL DEFAULT '',
  `pr` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `from_player_id` (`from_player_id`),
  KEY `to_player_id` (`to_player_id`),
  CONSTRAINT `players_forum_rewards_ibfk_1` FOREIGN KEY (`from_player_id`) REFERENCES `players` (`id`),
  CONSTRAINT `players_forum_rewards_ibfk_2` FOREIGN KEY (`to_player_id`) REFERENCES `players` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `players_forum_rewards` DISABLE KEYS */;
/*!40000 ALTER TABLE `players_forum_rewards` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `players_ips` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip` varchar(255) NOT NULL DEFAULT '',
  `expTime` int(11) NOT NULL DEFAULT 0,
  `failed` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `players_ips` DISABLE KEYS */;
/*!40000 ALTER TABLE `players_ips` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `players_items` (
  `player_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `n` int(11) NOT NULL DEFAULT 0,
  `equiped` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`player_id`,`item_id`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `players_items_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`),
  CONSTRAINT `players_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `players_items` DISABLE KEYS */;
INSERT INTO `players_items` VALUES (1,1,254,'');
INSERT INTO `players_items` VALUES (1,5,1,'');
INSERT INTO `players_items` VALUES (1,8,5,'main1');
INSERT INTO `players_items` VALUES (1,16,59,'munition');
INSERT INTO `players_items` VALUES (1,27,1,'');
INSERT INTO `players_items` VALUES (1,29,7,'');
INSERT INTO `players_items` VALUES (1,75,1,'tete');
INSERT INTO `players_items` VALUES (1,79,1,'');
INSERT INTO `players_items` VALUES (1,86,11,'');
INSERT INTO `players_items` VALUES (1,89,21,'');
INSERT INTO `players_items` VALUES (1,90,10,'');
INSERT INTO `players_items` VALUES (1,93,8,'');
INSERT INTO `players_items` VALUES (2,1,9,'');
INSERT INTO `players_items` VALUES (2,8,1,'');
INSERT INTO `players_items` VALUES (2,75,1,'tete');
INSERT INTO `players_items` VALUES (3,1,40,'');
INSERT INTO `players_items` VALUES (3,8,1,'main1');
/*!40000 ALTER TABLE `players_items` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `players_items_bank` (
  `player_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `n` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`player_id`,`item_id`),
  KEY `item_id` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `players_items_bank` DISABLE KEYS */;
/*!40000 ALTER TABLE `players_items_bank` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `players_items_exchanges` (
  `exchange_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `n` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `target_id` int(11) NOT NULL,
  KEY `players_items_exchanges_fk_1` (`exchange_id`),
  KEY `players_items_exchanges_fk_2` (`item_id`),
  KEY `players_items_exchanges_fk_3` (`player_id`),
  KEY `players_items_exchanges_fk_4` (`target_id`),
  CONSTRAINT `players_items_exchanges_fk_1` FOREIGN KEY (`exchange_id`) REFERENCES `items_exchanges` (`id`),
  CONSTRAINT `players_items_exchanges_fk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`),
  CONSTRAINT `players_items_exchanges_fk_3` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`),
  CONSTRAINT `players_items_exchanges_fk_4` FOREIGN KEY (`target_id`) REFERENCES `players` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `players_items_exchanges` DISABLE KEYS */;
/*!40000 ALTER TABLE `players_items_exchanges` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `players_kills` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `player_id` int(11) NOT NULL,
  `target_id` int(11) NOT NULL,
  `player_rank` int(11) NOT NULL DEFAULT 1,
  `target_rank` int(11) NOT NULL DEFAULT 1,
  `xp` int(11) NOT NULL DEFAULT 0,
  `assist` int(11) NOT NULL DEFAULT 0,
  `is_inactive` tinyint(1) NOT NULL DEFAULT 0,
  `time` int(11) NOT NULL DEFAULT 0,
  `plan` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `player_id` (`player_id`),
  KEY `target_id` (`target_id`),
  CONSTRAINT `players_kills_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`),
  CONSTRAINT `players_kills_ibfk_2` FOREIGN KEY (`target_id`) REFERENCES `players` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `players_kills` DISABLE KEYS */;
/*!40000 ALTER TABLE `players_kills` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `players_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `player_id` int(11) NOT NULL,
  `target_id` int(11) NOT NULL,
  `text` varchar(255) NOT NULL DEFAULT '',
  `hiddenText` text NOT NULL DEFAULT '',
  `type` varchar(255) NOT NULL DEFAULT '',
  `plan` varchar(255) NOT NULL DEFAULT '',
  `time` int(11) NOT NULL DEFAULT 0,
  `coords_id` int(11) DEFAULT 0,
  `coords_computed` varchar(35) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `player_id` (`player_id`),
  KEY `target_id` (`target_id`),
  KEY `players_logs_coords_fk_1` (`coords_id`),
  CONSTRAINT `players_logs_coords_fk_1` FOREIGN KEY (`coords_id`) REFERENCES `coords` (`id`),
  CONSTRAINT `players_logs_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`),
  CONSTRAINT `players_logs_ibfk_2` FOREIGN KEY (`target_id`) REFERENCES `players` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1482 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `players_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `players_logs` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `players_logs_archives` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `player_id` int(11) NOT NULL,
  `target_id` int(11) NOT NULL,
  `text` varchar(255) NOT NULL DEFAULT '',
  `hiddenText` text NOT NULL DEFAULT '',
  `type` varchar(255) NOT NULL DEFAULT '',
  `plan` varchar(255) NOT NULL DEFAULT '',
  `time` int(11) NOT NULL DEFAULT 0,
  `coords_id` int(11) DEFAULT 0,
  `coords_computed` varchar(35) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `player_id` (`player_id`),
  KEY `target_id` (`target_id`),
  KEY `players_logs_archives_coords_fk_1` (`coords_id`),
  CONSTRAINT `players_logs_archives_coords_fk_1` FOREIGN KEY (`coords_id`) REFERENCES `coords` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=153 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `players_logs_archives` DISABLE KEYS */;
/*!40000 ALTER TABLE `players_logs_archives` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `players_options` (
  `player_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  KEY `player_id` (`player_id`),
  CONSTRAINT `players_options_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `players_options` DISABLE KEYS */;
INSERT INTO `players_options` VALUES (1,'isAdmin');
INSERT INTO `players_options` VALUES (1,'isSuperAdmin');
INSERT INTO `players_options` VALUES (2,'showActionDetails');
INSERT INTO `players_options` VALUES (1,'isMerchant');
INSERT INTO `players_options` VALUES (1,'showActionDetails');
INSERT INTO `players_options` VALUES (3,'showActionDetails');
/*!40000 ALTER TABLE `players_options` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `players_passives` (
  `player_id` int(11) NOT NULL,
  `passive_id` int(11) NOT NULL,
  PRIMARY KEY (`player_id`,`passive_id`),
  KEY `fk_players_passives_passive` (`passive_id`),
  CONSTRAINT `fk_players_passives_passive` FOREIGN KEY (`passive_id`) REFERENCES `action_passives` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `players_passives` DISABLE KEYS */;
/*!40000 ALTER TABLE `players_passives` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `players_pnjs` (
  `player_id` int(11) NOT NULL,
  `pnj_id` int(11) NOT NULL,
  `displayed` tinyint(1) NOT NULL,
  PRIMARY KEY (`player_id`,`pnj_id`),
  KEY `player_id` (`player_id`),
  KEY `pnj_id` (`pnj_id`),
  CONSTRAINT `players_pnjs_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`),
  CONSTRAINT `players_pnjs_ibfk_2` FOREIGN KEY (`pnj_id`) REFERENCES `players` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `players_pnjs` DISABLE KEYS */;
INSERT INTO `players_pnjs` VALUES (1,-1,1);
/*!40000 ALTER TABLE `players_pnjs` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `players_psw` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `player_id` int(11) NOT NULL DEFAULT 0,
  `uniqid` varchar(255) NOT NULL DEFAULT '',
  `sentTime` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `players_psw` DISABLE KEYS */;
/*!40000 ALTER TABLE `players_psw` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `players_quests` (
  `player_id` int(11) NOT NULL,
  `quest_id` int(11) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `startTime` int(11) NOT NULL DEFAULT 0,
  `endTime` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`player_id`,`quest_id`),
  KEY `quest_id` (`quest_id`),
  CONSTRAINT `players_quests_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`),
  CONSTRAINT `players_quests_ibfk_2` FOREIGN KEY (`quest_id`) REFERENCES `quests` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `players_quests` DISABLE KEYS */;
/*!40000 ALTER TABLE `players_quests` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `players_quests_steps` (
  `player_id` int(11) NOT NULL,
  `quest_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `endTime` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`player_id`,`quest_id`,`name`),
  KEY `quest_id` (`quest_id`),
  CONSTRAINT `players_quests_steps_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`),
  CONSTRAINT `players_quests_steps_ibfk_2` FOREIGN KEY (`quest_id`) REFERENCES `quests` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `players_quests_steps` DISABLE KEYS */;
/*!40000 ALTER TABLE `players_quests_steps` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `players_reduction_passives` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `player_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `players_reduction_passives` DISABLE KEYS */;
/*!40000 ALTER TABLE `players_reduction_passives` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `players_upgrades` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `player_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `cost` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `player_id` (`player_id`),
  CONSTRAINT `players_upgrades_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `players_upgrades` DISABLE KEYS */;
/*!40000 ALTER TABLE `players_upgrades` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `quests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `text` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `quests` DISABLE KEYS */;
/*!40000 ALTER TABLE `quests` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `race_actions` (
  `race_id` int(11) NOT NULL,
  `action_id` int(11) NOT NULL,
  PRIMARY KEY (`race_id`,`action_id`),
  KEY `IDX_1AF8249F6E59D40D` (`race_id`),
  KEY `IDX_1AF8249F9D32F035` (`action_id`),
  CONSTRAINT `FK_1AF8249F6E59D40D` FOREIGN KEY (`race_id`) REFERENCES `races` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_1AF8249F9D32F035` FOREIGN KEY (`action_id`) REFERENCES `actions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `race_actions` DISABLE KEYS */;
/*!40000 ALTER TABLE `race_actions` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `race_recipes` (
  `race_id` int(11) NOT NULL,
  `recipe_id` int(11) NOT NULL,
  PRIMARY KEY (`race_id`,`recipe_id`),
  KEY `IDX_BCD937C56E59D40D` (`race_id`),
  KEY `IDX_BCD937C559D8A214` (`recipe_id`),
  CONSTRAINT `FK_BCD937C559D8A214` FOREIGN KEY (`recipe_id`) REFERENCES `craft_recipes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_BCD937C56E59D40D` FOREIGN KEY (`race_id`) REFERENCES `races` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `race_recipes` DISABLE KEYS */;
/*!40000 ALTER TABLE `race_recipes` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `races` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` longtext DEFAULT NULL,
  `playable` tinyint(1) NOT NULL,
  `hidden` tinyint(1) NOT NULL,
  `portraitNextNumber` int(11) NOT NULL DEFAULT 1,
  `avatarNextNumber` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_5DBD1EC977153098` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `races` DISABLE KEYS */;
INSERT INTO `races` VALUES (1,'NAIN','nain','De très petite taille, les Nains sont presque aussi larges que hauts. Leur barbe toujours bien entretenue est leur fierté mais aussi un bon moyen de les reconnaître. Les nains vivent dans des cités souterraines et sont de bons armuriers et inventeurs. Terriblement efficaces en combat rapproché, ils résistent correctement aux tirs et un peu moins bien à la magie. Leur lenteur massive reste leur défaut principal.',1,0,51,113);
INSERT INTO `races` VALUES (2,'ELFE','elfe','C\'est une race élancée aux allures nobles. Ils existaient avant les Olympiens et ne vénèrent pas les Dieux de l\'Olympe. Les Elfes sont des créatures vivant dans les forêts et qui n\'en sortent que rarement. Doués en magie et plutôt bons en tir, les Elfes sont cependant de biens piètres guerriers de mêlée.',1,0,34,103);
INSERT INTO `races` VALUES (3,'AME','ame','',0,1,2,1);
INSERT INTO `races` VALUES (4,'LUTIN','lutin','',0,1,16,67);
/*!40000 ALTER TABLE `races` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tutorial_catalog` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `version` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT 'ra-book',
  `difficulty` enum('beginner','intermediate','advanced') DEFAULT 'beginner',
  `estimated_minutes` int(11) DEFAULT 10,
  `prerequisites` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`prerequisites`)),
  `plan` varchar(50) DEFAULT 'tutorial',
  `spawn_x` int(11) DEFAULT 0,
  `spawn_y` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `version` (`version`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tutorial catalog for managing multiple tutorials';
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `tutorial_catalog` DISABLE KEYS */;
INSERT INTO `tutorial_catalog` VALUES (1,'1.0.0','Tutoriel de base','Apprenez les bases du jeu : déplacement, récolte de ressources et combat.','ra-player','beginner',15,NULL,'tutorial',0,0,1,1,'2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_catalog` VALUES (2,'2.0.0-craft','Tutoriel Artisanat','Apprenez à utiliser le système d\'artisanat pour créer des objets à partir de ressources.','ra-forging','intermediate',5,'[\"1.0.0\"]','tutorial',0,0,1,2,'2026-04-19 08:23:30','2026-04-19 08:23:30');
/*!40000 ALTER TABLE `tutorial_catalog` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tutorial_dialogs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dialog_id` varchar(100) NOT NULL,
  `npc_name` varchar(100) NOT NULL,
  `version` varchar(20) NOT NULL DEFAULT '1.0.0',
  `dialog_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`dialog_data`)),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_dialog_version` (`dialog_id`,`version`),
  KEY `idx_dialog_id` (`dialog_id`),
  KEY `idx_version` (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `tutorial_dialogs` DISABLE KEYS */;
/*!40000 ALTER TABLE `tutorial_dialogs` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tutorial_enemies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tutorial_session_id` varchar(36) NOT NULL,
  `enemy_player_id` int(11) NOT NULL COMMENT 'ID in players table (negative)',
  `enemy_coords_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_session` (`tutorial_session_id`),
  KEY `fk_tutorial_enemies_player` (`enemy_player_id`),
  KEY `fk_tutorial_enemies_coords` (`enemy_coords_id`),
  CONSTRAINT `fk_tutorial_enemies_player` FOREIGN KEY (`enemy_player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tutorial_enemies_coords` FOREIGN KEY (`enemy_coords_id`) REFERENCES `coords` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Enemies spawned for combat training in tutorial';
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `tutorial_enemies` DISABLE KEYS */;
/*!40000 ALTER TABLE `tutorial_enemies` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tutorial_map_instances` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tutorial_session_id` varchar(36) NOT NULL,
  `plan_name` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_session` (`tutorial_session_id`),
  KEY `idx_plan` (`plan_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tutorial map instance tracking';
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `tutorial_map_instances` DISABLE KEYS */;
/*!40000 ALTER TABLE `tutorial_map_instances` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tutorial_players` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tutorial_session_id` varchar(36) NOT NULL COMMENT 'Link to tutorial_progress session',
  `player_id` int(11) DEFAULT NULL COMMENT 'Tutorial player ID in players table (set after INSERT)',
  `name` varchar(255) NOT NULL COMMENT 'Character name',
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'Is this tutorial character currently active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL COMMENT 'Soft delete when tutorial completes',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_session_char` (`tutorial_session_id`),
  KEY `idx_session` (`tutorial_session_id`),
  KEY `idx_tutorial_player` (`player_id`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Temporary characters created for each tutorial instance (Phase 4.5: real-player link lives on players.real_player_id_ref)';
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `tutorial_players` DISABLE KEYS */;
/*!40000 ALTER TABLE `tutorial_players` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tutorial_progress` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `player_id` int(11) NOT NULL,
  `tutorial_session_id` varchar(36) NOT NULL COMMENT 'UUID for each tutorial attempt',
  `current_step` varchar(100) NOT NULL DEFAULT '1.0' COMMENT 'Current step number',
  `total_steps` int(11) DEFAULT 0 COMMENT 'Total steps in tutorial version',
  `completed` tinyint(1) DEFAULT 0 COMMENT 'Has player completed this session',
  `started_at` timestamp NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `tutorial_mode` enum('first_time','replay','practice') DEFAULT 'first_time' COMMENT 'Tutorial context',
  `tutorial_version` varchar(20) NOT NULL DEFAULT '1.0.0' COMMENT 'Tutorial version',
  `xp_earned` int(11) DEFAULT 0 COMMENT 'Total XP earned during this tutorial session',
  `data` longtext DEFAULT NULL COMMENT 'Additional session data (JSON)',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_player_id` (`player_id`),
  KEY `idx_session_id` (`tutorial_session_id`),
  KEY `idx_completed` (`completed`),
  CONSTRAINT `tutorial_progress_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tutorial progress tracking for each player session';
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `tutorial_progress` DISABLE KEYS */;
INSERT INTO `tutorial_progress` VALUES (1,2,'7d2bfa1b-3b76-11f1-8aed-72570ab3947b','30.0',29,1,'2026-04-19 08:23:30','2026-04-19 08:23:30','first_time','1.0.0',0,'{}','2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_progress` VALUES (2,5,'7d2bfb69-3b76-11f1-8aed-72570ab3947b','30.0',29,1,'2026-04-19 08:23:30','2026-04-19 08:23:30','first_time','1.0.0',0,'{}','2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_progress` VALUES (3,4,'7d2bfc9b-3b76-11f1-8aed-72570ab3947b','30.0',29,1,'2026-04-19 08:23:30','2026-04-19 08:23:30','first_time','1.0.0',0,'{}','2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_progress` VALUES (4,1,'7d2bfcdf-3b76-11f1-8aed-72570ab3947b','30.0',29,1,'2026-04-19 08:23:30','2026-04-19 08:23:30','first_time','1.0.0',0,'{}','2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_progress` VALUES (5,3,'7d2bfe3f-3b76-11f1-8aed-72570ab3947b','30.0',29,1,'2026-04-19 08:23:30','2026-04-19 08:23:30','first_time','1.0.0',0,'{}','2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_progress` VALUES (6,6,'7d2bfe79-3b76-11f1-8aed-72570ab3947b','30.0',29,1,'2026-04-19 08:23:30','2026-04-19 08:23:30','first_time','1.0.0',0,'{}','2026-04-19 08:23:30','2026-04-19 08:23:30');
/*!40000 ALTER TABLE `tutorial_progress` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tutorial_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `idx_setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tutorial system feature flags and configuration';
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `tutorial_settings` DISABLE KEYS */;
INSERT INTO `tutorial_settings` VALUES (1,'global_enabled','0','Enable tutorial globally for all players','2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_settings` VALUES (2,'whitelisted_players','','Comma-separated list of player IDs who can access tutorial','2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_settings` VALUES (3,'auto_show_new_players','1','Automatically show tutorial to new players','2026-04-19 08:23:30','2026-04-19 08:23:30');
/*!40000 ALTER TABLE `tutorial_settings` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tutorial_step_context_changes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `step_id` int(11) NOT NULL,
  `context_key` varchar(50) NOT NULL COMMENT 'unlimited_mvt, consume_movements, set_mvt_limit, etc.',
  `context_value` text NOT NULL COMMENT 'Value (int, bool, string)',
  PRIMARY KEY (`id`),
  KEY `idx_step_id` (`step_id`),
  CONSTRAINT `tutorial_step_context_changes_ibfk_1` FOREIGN KEY (`step_id`) REFERENCES `tutorial_steps` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Context state changes applied during step';
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `tutorial_step_context_changes` DISABLE KEYS */;
INSERT INTO `tutorial_step_context_changes` VALUES (1,6,'unlimited_mvt','true');
INSERT INTO `tutorial_step_context_changes` VALUES (2,6,'consume_movements','false');
INSERT INTO `tutorial_step_context_changes` VALUES (3,9,'consume_movements','true');
/*!40000 ALTER TABLE `tutorial_step_context_changes` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tutorial_step_features` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `step_id` int(11) NOT NULL,
  `celebration` tinyint(1) DEFAULT 0 COMMENT 'Show celebration animation',
  `show_rewards` tinyint(1) DEFAULT 0 COMMENT 'Display rewards summary',
  `redirect_delay` int(11) DEFAULT NULL COMMENT 'Redirect to main game after N ms',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_step` (`step_id`),
  CONSTRAINT `tutorial_step_features_ibfk_1` FOREIGN KEY (`step_id`) REFERENCES `tutorial_steps` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Special features and effects for tutorial steps';
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `tutorial_step_features` DISABLE KEYS */;
INSERT INTO `tutorial_step_features` VALUES (1,1,0,0,NULL);
INSERT INTO `tutorial_step_features` VALUES (2,3,0,0,NULL);
INSERT INTO `tutorial_step_features` VALUES (3,4,0,0,NULL);
INSERT INTO `tutorial_step_features` VALUES (4,7,0,0,NULL);
INSERT INTO `tutorial_step_features` VALUES (5,8,0,0,NULL);
INSERT INTO `tutorial_step_features` VALUES (6,9,0,0,NULL);
INSERT INTO `tutorial_step_features` VALUES (7,12,0,0,NULL);
INSERT INTO `tutorial_step_features` VALUES (8,14,0,0,NULL);
INSERT INTO `tutorial_step_features` VALUES (9,16,0,0,NULL);
INSERT INTO `tutorial_step_features` VALUES (10,23,0,0,NULL);
INSERT INTO `tutorial_step_features` VALUES (11,24,0,0,NULL);
INSERT INTO `tutorial_step_features` VALUES (12,26,0,0,NULL);
INSERT INTO `tutorial_step_features` VALUES (13,28,0,0,NULL);
INSERT INTO `tutorial_step_features` VALUES (14,29,1,1,20000);
INSERT INTO `tutorial_step_features` VALUES (16,35,1,1,NULL);
/*!40000 ALTER TABLE `tutorial_step_features` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tutorial_step_highlights` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `step_id` int(11) NOT NULL,
  `selector` varchar(500) NOT NULL COMMENT 'CSS selector for additional highlight',
  PRIMARY KEY (`id`),
  KEY `idx_step_id` (`step_id`),
  CONSTRAINT `tutorial_step_highlights_ibfk_1` FOREIGN KEY (`step_id`) REFERENCES `tutorial_steps` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Additional elements to highlight beyond main target';
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `tutorial_step_highlights` DISABLE KEYS */;
INSERT INTO `tutorial_step_highlights` VALUES (1,5,'.case.go');
INSERT INTO `tutorial_step_highlights` VALUES (2,15,'.case[data-coords=\"0,1\"]');
/*!40000 ALTER TABLE `tutorial_step_highlights` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tutorial_step_interactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `step_id` int(11) NOT NULL,
  `selector` varchar(500) NOT NULL COMMENT 'CSS selector for allowed clickable element',
  `description` varchar(255) DEFAULT NULL COMMENT 'Human-readable description',
  PRIMARY KEY (`id`),
  KEY `idx_step_id` (`step_id`),
  CONSTRAINT `tutorial_step_interactions_ibfk_1` FOREIGN KEY (`step_id`) REFERENCES `tutorial_steps` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Allowed interactions for semi-blocking steps';
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `tutorial_step_interactions` DISABLE KEYS */;
INSERT INTO `tutorial_step_interactions` VALUES (1,3,'.case','Cases du damier');
INSERT INTO `tutorial_step_interactions` VALUES (2,3,'image','Personnages');
INSERT INTO `tutorial_step_interactions` VALUES (3,3,'.case-infos','Fiche personnage');
INSERT INTO `tutorial_step_interactions` VALUES (4,4,'.case','Cases du damier');
INSERT INTO `tutorial_step_interactions` VALUES (5,4,'.close-card','Bouton fermer');
INSERT INTO `tutorial_step_interactions` VALUES (6,4,'#game-map','Zone de jeu');
INSERT INTO `tutorial_step_interactions` VALUES (7,4,'svg','Fond du damier');
INSERT INTO `tutorial_step_interactions` VALUES (8,6,'.case',NULL);
INSERT INTO `tutorial_step_interactions` VALUES (9,6,'.case.go',NULL);
INSERT INTO `tutorial_step_interactions` VALUES (10,6,'#go-rect',NULL);
INSERT INTO `tutorial_step_interactions` VALUES (11,6,'#go-img',NULL);
INSERT INTO `tutorial_step_interactions` VALUES (12,8,'#show-caracs','Bouton caractéristiques');
INSERT INTO `tutorial_step_interactions` VALUES (13,9,'.case','Cases du damier');
INSERT INTO `tutorial_step_interactions` VALUES (14,9,'.case.go','Cases accessibles');
INSERT INTO `tutorial_step_interactions` VALUES (15,9,'#go-rect','Bouton de déplacement (rectangle)');
INSERT INTO `tutorial_step_interactions` VALUES (16,9,'#go-img','Bouton de déplacement (image)');
INSERT INTO `tutorial_step_interactions` VALUES (17,12,'.case','Cases du damier');
INSERT INTO `tutorial_step_interactions` VALUES (18,12,'image','Personnages');
INSERT INTO `tutorial_step_interactions` VALUES (19,12,'#current-player-avatar','Avatar du joueur');
INSERT INTO `tutorial_step_interactions` VALUES (20,14,'.case','Cases du damier');
INSERT INTO `tutorial_step_interactions` VALUES (21,14,'.close-card','Bouton fermer');
INSERT INTO `tutorial_step_interactions` VALUES (22,15,'.case',NULL);
INSERT INTO `tutorial_step_interactions` VALUES (23,15,'.case.go',NULL);
INSERT INTO `tutorial_step_interactions` VALUES (24,15,'#go-rect',NULL);
INSERT INTO `tutorial_step_interactions` VALUES (25,15,'#go-img',NULL);
INSERT INTO `tutorial_step_interactions` VALUES (26,16,'.case','Cases du damier');
INSERT INTO `tutorial_step_interactions` VALUES (27,16,'.case[data-coords=\"0,1\"]','L\'arbre');
INSERT INTO `tutorial_step_interactions` VALUES (28,18,'.action[data-action=\"fouiller\"]',NULL);
INSERT INTO `tutorial_step_interactions` VALUES (29,18,'.case-infos',NULL);
INSERT INTO `tutorial_step_interactions` VALUES (30,18,'button.action',NULL);
INSERT INTO `tutorial_step_interactions` VALUES (31,20,'#show-inventory',NULL);
INSERT INTO `tutorial_step_interactions` VALUES (32,22,'#back',NULL);
INSERT INTO `tutorial_step_interactions` VALUES (33,25,'.case',NULL);
INSERT INTO `tutorial_step_interactions` VALUES (34,25,'.case.go',NULL);
INSERT INTO `tutorial_step_interactions` VALUES (35,25,'#go-rect',NULL);
INSERT INTO `tutorial_step_interactions` VALUES (36,25,'#go-img',NULL);
INSERT INTO `tutorial_step_interactions` VALUES (37,26,'.case','Cases du damier');
INSERT INTO `tutorial_step_interactions` VALUES (38,26,'image','Personnages');
INSERT INTO `tutorial_step_interactions` VALUES (39,26,'.tutorial-enemy','Ennemi du tutoriel');
INSERT INTO `tutorial_step_interactions` VALUES (40,27,'.action[data-action=\"attaquer\"]',NULL);
/*!40000 ALTER TABLE `tutorial_step_interactions` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tutorial_step_next_preparation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `step_id` int(11) NOT NULL COMMENT 'Current step ID',
  `preparation_key` varchar(50) NOT NULL COMMENT 'restore_mvt, restore_actions, spawn_enemy, etc.',
  `preparation_value` text NOT NULL COMMENT 'Value for the preparation',
  PRIMARY KEY (`id`),
  KEY `idx_step_id` (`step_id`),
  CONSTRAINT `tutorial_step_next_preparation_ibfk_1` FOREIGN KEY (`step_id`) REFERENCES `tutorial_steps` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Preparation actions after step completion';
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `tutorial_step_next_preparation` DISABLE KEYS */;
INSERT INTO `tutorial_step_next_preparation` VALUES (1,10,'restore_mvt','4');
INSERT INTO `tutorial_step_next_preparation` VALUES (2,23,'spawn_enemy','tutorial_dummy');
/*!40000 ALTER TABLE `tutorial_step_next_preparation` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tutorial_step_prerequisites` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `step_id` int(11) NOT NULL,
  `mvt_required` int(11) DEFAULT NULL COMMENT 'Movement points required',
  `pa_required` int(11) DEFAULT NULL COMMENT 'Action points required',
  `auto_restore` tinyint(1) DEFAULT 1 COMMENT 'Auto-restore resources on step start',
  `consume_movements` tinyint(1) DEFAULT 0 COMMENT 'Consume MVT when moving',
  `unlimited_mvt` tinyint(1) DEFAULT 0 COMMENT 'Unlimited movement for this step',
  `unlimited_pa` tinyint(1) DEFAULT 0 COMMENT 'Unlimited actions for this step',
  `spawn_enemy` varchar(50) DEFAULT NULL COMMENT 'Enemy type to spawn',
  `ensure_harvestable_tree_x` int(11) DEFAULT NULL COMMENT 'Ensure harvestable tree at X',
  `ensure_harvestable_tree_y` int(11) DEFAULT NULL COMMENT 'Ensure harvestable tree at Y',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_step` (`step_id`),
  CONSTRAINT `tutorial_step_prerequisites_ibfk_1` FOREIGN KEY (`step_id`) REFERENCES `tutorial_steps` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Prerequisites and resource requirements for steps';
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `tutorial_step_prerequisites` DISABLE KEYS */;
INSERT INTO `tutorial_step_prerequisites` VALUES (1,1,NULL,NULL,1,0,0,0,NULL,NULL,NULL);
INSERT INTO `tutorial_step_prerequisites` VALUES (2,3,NULL,NULL,1,0,0,0,NULL,NULL,NULL);
INSERT INTO `tutorial_step_prerequisites` VALUES (3,4,NULL,NULL,1,0,0,0,NULL,NULL,NULL);
INSERT INTO `tutorial_step_prerequisites` VALUES (4,6,1,NULL,1,0,1,0,NULL,NULL,NULL);
INSERT INTO `tutorial_step_prerequisites` VALUES (5,7,NULL,NULL,1,0,0,0,NULL,NULL,NULL);
INSERT INTO `tutorial_step_prerequisites` VALUES (6,8,NULL,NULL,1,0,0,0,NULL,NULL,NULL);
INSERT INTO `tutorial_step_prerequisites` VALUES (7,9,-1,NULL,1,1,0,0,NULL,NULL,NULL);
INSERT INTO `tutorial_step_prerequisites` VALUES (8,11,-1,2,1,0,0,0,NULL,NULL,NULL);
INSERT INTO `tutorial_step_prerequisites` VALUES (9,12,NULL,NULL,1,0,0,0,NULL,NULL,NULL);
INSERT INTO `tutorial_step_prerequisites` VALUES (10,14,NULL,NULL,1,0,0,0,NULL,NULL,NULL);
INSERT INTO `tutorial_step_prerequisites` VALUES (11,15,-1,NULL,1,0,0,0,NULL,0,1);
INSERT INTO `tutorial_step_prerequisites` VALUES (12,16,NULL,NULL,1,0,0,0,NULL,NULL,NULL);
INSERT INTO `tutorial_step_prerequisites` VALUES (13,18,NULL,1,1,0,0,0,NULL,NULL,NULL);
INSERT INTO `tutorial_step_prerequisites` VALUES (14,23,NULL,NULL,1,0,0,0,NULL,NULL,NULL);
INSERT INTO `tutorial_step_prerequisites` VALUES (15,24,NULL,NULL,1,0,0,0,'tutorial_dummy',NULL,NULL);
INSERT INTO `tutorial_step_prerequisites` VALUES (16,25,-1,NULL,1,0,0,0,NULL,NULL,NULL);
INSERT INTO `tutorial_step_prerequisites` VALUES (17,26,NULL,NULL,1,0,0,0,NULL,NULL,NULL);
INSERT INTO `tutorial_step_prerequisites` VALUES (18,27,NULL,1,1,0,0,0,NULL,NULL,NULL);
INSERT INTO `tutorial_step_prerequisites` VALUES (19,28,NULL,NULL,1,0,0,0,NULL,NULL,NULL);
/*!40000 ALTER TABLE `tutorial_step_prerequisites` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tutorial_step_ui` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `step_id` int(11) NOT NULL,
  `target_selector` varchar(500) DEFAULT NULL COMMENT 'CSS selector for element to highlight',
  `target_description` varchar(255) DEFAULT NULL COMMENT 'Human-readable description',
  `highlight_selector` varchar(500) DEFAULT NULL COMMENT 'Alternative selector for highlighting',
  `tooltip_position` enum('top','bottom','left','right','center','center-top','center-bottom') DEFAULT 'bottom',
  `interaction_mode` enum('blocking','semi-blocking','open') DEFAULT 'blocking',
  `blocked_click_message` text DEFAULT NULL COMMENT 'Message shown when clicking blocked element',
  `show_delay` int(11) DEFAULT 0 COMMENT 'Delay in ms before showing tooltip',
  `auto_advance_delay` int(11) DEFAULT NULL COMMENT 'Auto-advance after N ms',
  `allow_manual_advance` tinyint(1) DEFAULT 1 COMMENT 'Allow manual Next button',
  `auto_close_card` tinyint(1) DEFAULT NULL COMMENT 'Auto-close action card',
  `tooltip_offset_x` int(11) DEFAULT 0 COMMENT 'X offset for tooltip',
  `tooltip_offset_y` int(11) DEFAULT 0 COMMENT 'Y offset for tooltip',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_step` (`step_id`),
  KEY `idx_interaction_mode` (`interaction_mode`),
  CONSTRAINT `tutorial_step_ui_ibfk_1` FOREIGN KEY (`step_id`) REFERENCES `tutorial_steps` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='UI configuration for tutorial steps';
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `tutorial_step_ui` DISABLE KEYS */;
INSERT INTO `tutorial_step_ui` VALUES (1,1,NULL,NULL,NULL,'center','blocking',NULL,0,NULL,1,0,0,0);
INSERT INTO `tutorial_step_ui` VALUES (2,2,'.case[data-coords=\"0,0\"]',NULL,NULL,'bottom','blocking',NULL,200,NULL,1,NULL,0,0);
INSERT INTO `tutorial_step_ui` VALUES (3,3,'.case[data-coords=\"1,0\"]',NULL,NULL,'right','semi-blocking',NULL,0,NULL,1,0,0,0);
INSERT INTO `tutorial_step_ui` VALUES (4,4,'#ui-card .close-card',NULL,NULL,'right','semi-blocking',NULL,300,NULL,1,0,0,0);
INSERT INTO `tutorial_step_ui` VALUES (5,5,'.case.go',NULL,NULL,'top','blocking',NULL,300,NULL,1,NULL,0,0);
INSERT INTO `tutorial_step_ui` VALUES (6,6,'.case.go',NULL,NULL,'top','semi-blocking',NULL,0,NULL,1,NULL,0,0);
INSERT INTO `tutorial_step_ui` VALUES (7,7,NULL,NULL,NULL,'center','blocking',NULL,0,NULL,1,0,0,0);
INSERT INTO `tutorial_step_ui` VALUES (8,8,'#show-caracs',NULL,NULL,'bottom','semi-blocking',NULL,700,NULL,1,0,0,0);
INSERT INTO `tutorial_step_ui` VALUES (9,9,'#mvt-counter',NULL,NULL,'right','semi-blocking',NULL,700,NULL,1,0,0,0);
INSERT INTO `tutorial_step_ui` VALUES (10,10,'#mvt-counter',NULL,NULL,'right','blocking',NULL,700,NULL,1,NULL,0,0);
INSERT INTO `tutorial_step_ui` VALUES (11,11,'#action-counter',NULL,NULL,'right','blocking',NULL,700,NULL,1,NULL,0,0);
INSERT INTO `tutorial_step_ui` VALUES (12,12,'#current-player-avatar',NULL,NULL,'bottom','semi-blocking',NULL,0,NULL,1,0,0,0);
INSERT INTO `tutorial_step_ui` VALUES (13,13,'.card-actions',NULL,NULL,'right','blocking',NULL,300,NULL,1,NULL,0,0);
INSERT INTO `tutorial_step_ui` VALUES (14,14,'#ui-card .close-card',NULL,NULL,'right','semi-blocking',NULL,0,NULL,1,1,0,0);
INSERT INTO `tutorial_step_ui` VALUES (15,15,'.case[data-coords=\"0,1\"]',NULL,NULL,'center-bottom','semi-blocking',NULL,0,NULL,1,NULL,0,0);
INSERT INTO `tutorial_step_ui` VALUES (16,16,'.case[data-coords=\"0,1\"]',NULL,NULL,'bottom','semi-blocking',NULL,0,NULL,0,0,0,0);
INSERT INTO `tutorial_step_ui` VALUES (17,17,'.resource-status',NULL,NULL,'left','blocking',NULL,300,NULL,1,NULL,0,0);
INSERT INTO `tutorial_step_ui` VALUES (18,18,'.action[data-action=\"fouiller\"]',NULL,NULL,'right','semi-blocking',NULL,300,NULL,1,1,0,0);
INSERT INTO `tutorial_step_ui` VALUES (19,19,'#action-counter',NULL,NULL,'right','blocking',NULL,700,NULL,1,NULL,0,0);
INSERT INTO `tutorial_step_ui` VALUES (20,20,'#show-inventory',NULL,NULL,'bottom','semi-blocking',NULL,300,NULL,1,NULL,0,0);
INSERT INTO `tutorial_step_ui` VALUES (21,21,'.item-case[data-name=\"Bois\"]',NULL,NULL,'left','blocking',NULL,700,NULL,1,NULL,0,0);
INSERT INTO `tutorial_step_ui` VALUES (22,22,'#back',NULL,NULL,'bottom','semi-blocking',NULL,200,NULL,1,NULL,0,0);
INSERT INTO `tutorial_step_ui` VALUES (23,23,NULL,NULL,NULL,'center','blocking',NULL,0,NULL,1,0,0,0);
INSERT INTO `tutorial_step_ui` VALUES (24,24,'.tutorial-enemy',NULL,NULL,'bottom','blocking',NULL,500,NULL,1,0,0,0);
INSERT INTO `tutorial_step_ui` VALUES (25,25,'.tutorial-enemy',NULL,NULL,'center-bottom','semi-blocking',NULL,0,NULL,1,NULL,0,0);
INSERT INTO `tutorial_step_ui` VALUES (26,26,'.tutorial-enemy',NULL,NULL,'bottom','semi-blocking',NULL,0,NULL,1,0,0,0);
INSERT INTO `tutorial_step_ui` VALUES (27,27,'.action[data-action=\"attaquer\"]',NULL,NULL,'right','semi-blocking',NULL,0,NULL,1,NULL,0,0);
INSERT INTO `tutorial_step_ui` VALUES (28,28,'#red-filter',NULL,NULL,'right','blocking',NULL,700,NULL,1,0,0,0);
INSERT INTO `tutorial_step_ui` VALUES (29,29,NULL,NULL,NULL,'center','blocking',NULL,0,NULL,1,NULL,0,0);
INSERT INTO `tutorial_step_ui` VALUES (32,30,NULL,NULL,NULL,'center','blocking',NULL,0,NULL,1,NULL,0,0);
INSERT INTO `tutorial_step_ui` VALUES (33,31,'#show-menu',NULL,NULL,'bottom','semi-blocking',NULL,0,NULL,0,NULL,0,0);
INSERT INTO `tutorial_step_ui` VALUES (34,32,'.menu-inventaire',NULL,NULL,'right','semi-blocking',NULL,300,NULL,0,NULL,0,0);
INSERT INTO `tutorial_step_ui` VALUES (35,33,'.inventory-tab-craft',NULL,NULL,'bottom','semi-blocking',NULL,300,NULL,0,NULL,0,0);
INSERT INTO `tutorial_step_ui` VALUES (36,34,'.craft-recipes',NULL,NULL,'right','blocking',NULL,500,NULL,1,NULL,0,0);
INSERT INTO `tutorial_step_ui` VALUES (37,35,NULL,NULL,NULL,'center','blocking',NULL,0,NULL,1,NULL,0,0);
/*!40000 ALTER TABLE `tutorial_step_ui` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tutorial_step_validation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `step_id` int(11) NOT NULL,
  `requires_validation` tinyint(1) DEFAULT 0,
  `validation_type` varchar(50) DEFAULT NULL COMMENT 'any_movement, movements_depleted, position, action_used, etc.',
  `validation_hint` text DEFAULT NULL COMMENT 'Hint shown when validation fails',
  `target_x` int(11) DEFAULT NULL COMMENT 'Target X coordinate',
  `target_y` int(11) DEFAULT NULL COMMENT 'Target Y coordinate',
  `movement_count` int(11) DEFAULT NULL COMMENT 'Required number of movements',
  `action_name` varchar(50) DEFAULT NULL COMMENT 'Required action name',
  `action_charges_required` int(11) DEFAULT 1 COMMENT 'Number of times action must be used',
  `combat_required` tinyint(1) DEFAULT 0,
  `panel_id` varchar(50) DEFAULT NULL COMMENT 'Panel that must be opened',
  `element_selector` varchar(255) DEFAULT NULL COMMENT 'Element that must be visible/hidden',
  `element_clicked` varchar(255) DEFAULT NULL COMMENT 'Element that must be clicked',
  `dialog_id` varchar(50) DEFAULT NULL COMMENT 'Dialog that must be completed',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_step` (`step_id`),
  KEY `idx_validation_type` (`validation_type`),
  CONSTRAINT `tutorial_step_validation_ibfk_1` FOREIGN KEY (`step_id`) REFERENCES `tutorial_steps` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Validation rules for tutorial steps';
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `tutorial_step_validation` DISABLE KEYS */;
INSERT INTO `tutorial_step_validation` VALUES (1,1,0,NULL,NULL,NULL,NULL,NULL,NULL,1,0,NULL,NULL,NULL,NULL);
INSERT INTO `tutorial_step_validation` VALUES (2,2,0,NULL,NULL,NULL,NULL,NULL,NULL,1,0,NULL,NULL,NULL,NULL);
INSERT INTO `tutorial_step_validation` VALUES (3,3,1,'ui_panel_opened','Cliquez sur Gaïa pour ouvrir sa fiche',NULL,NULL,NULL,NULL,1,0,'actions',NULL,NULL,NULL);
INSERT INTO `tutorial_step_validation` VALUES (4,4,1,'ui_element_hidden','Fermez la fiche de personnage',NULL,NULL,NULL,NULL,1,0,NULL,'#ui-card',NULL,NULL);
INSERT INTO `tutorial_step_validation` VALUES (5,5,0,NULL,NULL,NULL,NULL,NULL,NULL,1,0,NULL,NULL,NULL,NULL);
INSERT INTO `tutorial_step_validation` VALUES (6,6,1,'any_movement','Déplacez-vous sur une case adjacente',NULL,NULL,NULL,NULL,1,0,NULL,NULL,NULL,NULL);
INSERT INTO `tutorial_step_validation` VALUES (7,7,0,NULL,NULL,NULL,NULL,NULL,NULL,1,0,NULL,NULL,NULL,NULL);
INSERT INTO `tutorial_step_validation` VALUES (8,8,1,'ui_panel_opened','Ouvrez le panneau des caractéristiques',NULL,NULL,NULL,NULL,1,0,'characteristics',NULL,NULL,NULL);
INSERT INTO `tutorial_step_validation` VALUES (9,9,1,'movements_depleted','Utilisez tous vos mouvements',NULL,NULL,NULL,NULL,1,0,NULL,NULL,NULL,NULL);
INSERT INTO `tutorial_step_validation` VALUES (10,10,0,NULL,NULL,NULL,NULL,NULL,NULL,1,0,NULL,NULL,NULL,NULL);
INSERT INTO `tutorial_step_validation` VALUES (11,11,0,NULL,NULL,NULL,NULL,NULL,NULL,1,0,NULL,NULL,NULL,NULL);
INSERT INTO `tutorial_step_validation` VALUES (12,12,1,'ui_panel_opened','Cliquez sur votre personnage',NULL,NULL,NULL,NULL,1,0,'actions',NULL,NULL,NULL);
INSERT INTO `tutorial_step_validation` VALUES (13,13,0,NULL,NULL,NULL,NULL,NULL,NULL,1,0,NULL,NULL,NULL,NULL);
INSERT INTO `tutorial_step_validation` VALUES (14,14,1,'ui_element_hidden','Fermez la fiche',NULL,NULL,NULL,NULL,1,0,NULL,'#ui-card',NULL,NULL);
INSERT INTO `tutorial_step_validation` VALUES (15,15,1,'adjacent_to_position','Approchez-vous de l\'arbre',0,1,NULL,NULL,1,0,NULL,NULL,NULL,NULL);
INSERT INTO `tutorial_step_validation` VALUES (16,16,1,'ui_panel_opened','Cliquez sur l\'arbre',NULL,NULL,NULL,NULL,1,0,'actions',NULL,NULL,NULL);
INSERT INTO `tutorial_step_validation` VALUES (17,17,0,NULL,NULL,NULL,NULL,NULL,NULL,1,0,NULL,NULL,NULL,NULL);
INSERT INTO `tutorial_step_validation` VALUES (18,18,1,'action_used','Utilisez l\'action Fouiller',NULL,NULL,NULL,'fouiller',1,0,NULL,NULL,NULL,NULL);
INSERT INTO `tutorial_step_validation` VALUES (19,19,0,NULL,NULL,NULL,NULL,NULL,NULL,1,0,NULL,NULL,NULL,NULL);
INSERT INTO `tutorial_step_validation` VALUES (20,20,1,'ui_interaction','Cliquez sur le bouton Inventaire',NULL,NULL,NULL,NULL,1,0,NULL,NULL,'#show-inventory',NULL);
INSERT INTO `tutorial_step_validation` VALUES (21,21,0,NULL,NULL,NULL,NULL,NULL,NULL,1,0,NULL,NULL,NULL,NULL);
INSERT INTO `tutorial_step_validation` VALUES (22,22,1,'ui_interaction','Retournez au damier',NULL,NULL,NULL,NULL,1,0,NULL,NULL,'#back',NULL);
INSERT INTO `tutorial_step_validation` VALUES (23,23,0,NULL,NULL,NULL,NULL,NULL,NULL,1,0,NULL,NULL,NULL,NULL);
INSERT INTO `tutorial_step_validation` VALUES (24,24,0,NULL,NULL,NULL,NULL,NULL,NULL,1,0,NULL,NULL,NULL,NULL);
INSERT INTO `tutorial_step_validation` VALUES (25,25,1,'adjacent_to_position','Approchez-vous de l\'ennemi',2,1,NULL,NULL,1,0,NULL,NULL,NULL,NULL);
INSERT INTO `tutorial_step_validation` VALUES (26,26,1,'ui_panel_opened','Cliquez sur l\'ennemi',NULL,NULL,NULL,NULL,1,0,'actions',NULL,NULL,NULL);
INSERT INTO `tutorial_step_validation` VALUES (27,27,1,'action_used','Attaquez l\'ennemi',NULL,NULL,NULL,'attaquer',1,0,NULL,NULL,NULL,NULL);
INSERT INTO `tutorial_step_validation` VALUES (28,28,0,NULL,NULL,NULL,NULL,NULL,NULL,1,0,NULL,NULL,NULL,NULL);
INSERT INTO `tutorial_step_validation` VALUES (29,29,0,NULL,NULL,NULL,NULL,NULL,NULL,1,0,NULL,NULL,NULL,NULL);
INSERT INTO `tutorial_step_validation` VALUES (32,30,0,NULL,NULL,NULL,NULL,NULL,NULL,1,0,NULL,NULL,NULL,NULL);
INSERT INTO `tutorial_step_validation` VALUES (33,31,1,'ui_interaction',NULL,NULL,NULL,NULL,NULL,1,0,NULL,NULL,'#show-menu',NULL);
INSERT INTO `tutorial_step_validation` VALUES (34,32,1,'ui_interaction',NULL,NULL,NULL,NULL,NULL,1,0,NULL,NULL,'.menu-inventaire',NULL);
INSERT INTO `tutorial_step_validation` VALUES (35,33,1,'ui_interaction',NULL,NULL,NULL,NULL,NULL,1,0,NULL,NULL,'.inventory-tab-craft',NULL);
INSERT INTO `tutorial_step_validation` VALUES (36,34,0,NULL,NULL,NULL,NULL,NULL,NULL,1,0,NULL,NULL,NULL,NULL);
INSERT INTO `tutorial_step_validation` VALUES (37,35,0,NULL,NULL,NULL,NULL,NULL,NULL,1,0,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `tutorial_step_validation` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tutorial_steps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `version` varchar(20) NOT NULL DEFAULT '1.0.0',
  `step_id` varchar(100) DEFAULT NULL COMMENT 'Human-readable step identifier',
  `next_step` varchar(100) DEFAULT NULL COMMENT 'Next step identifier for branching logic',
  `step_number` decimal(5,1) NOT NULL COMMENT 'Order in sequence',
  `step_type` varchar(50) NOT NULL COMMENT 'info, movement, action, combat, etc.',
  `title` varchar(255) NOT NULL COMMENT 'Step title shown to player',
  `text` text NOT NULL COMMENT 'Step description/instructions',
  `xp_reward` int(11) DEFAULT 0 COMMENT 'XP awarded for completing this step',
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'Feature flag',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_version_step` (`version`,`step_number`),
  KEY `idx_version` (`version`),
  KEY `idx_step_number` (`step_number`),
  KEY `idx_step_id` (`step_id`),
  KEY `idx_active` (`is_active`),
  KEY `idx_step_type` (`step_type`)
) ENGINE=InnoDB AUTO_INCREMENT=59 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tutorial step definitions - core information';
/*!40101 SET character_set_client = @saved_cs_client */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `tutorial_steps` DISABLE KEYS */;
INSERT INTO `tutorial_steps` VALUES (1,'1.0.0','welcome','your_character',1.0,'info','Bienvenue !','Bienvenue dans Age of Olympia ! Ce tutoriel va vous apprendre les bases du jeu. Suivez les instructions pour découvrir comment explorer, récolter et combattre.',5,1,'2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_steps` VALUES (2,'1.0.0','your_character','meet_gaia',2.0,'info','Votre personnage','Voici <strong>votre personnage</strong> ! Il est représenté au centre du damier. C\'est vous dans le monde d\'Olympia.',5,1,'2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_steps` VALUES (3,'1.0.0','meet_gaia','close_card',3.0,'info','Gaïa, votre guide','Voici <strong>Gaïa</strong>, la déesse de la Terre. Elle sera votre guide tout au long de ce tutoriel. Cliquez sur elle pour voir sa fiche.',5,1,'2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_steps` VALUES (4,'1.0.0','close_card','movement_intro',4.0,'ui_interaction','Fermer la fiche','Vous pouvez <strong>fermer la fiche</strong> en cliquant sur le bouton X, sur une case vide, ou ailleurs sur le damier.',5,1,'2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_steps` VALUES (5,'1.0.0','movement_intro','first_move',5.0,'info','Se déplacer','Regardez les <strong>cases</strong> autour de vous ! Ce sont les cases où vous pouvez vous déplacer si elles sont vides.',5,1,'2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_steps` VALUES (6,'1.0.0','first_move','movement_limit_warning',6.0,'movement','Premier pas','Cliquez sur une <strong>case mise en valeur</strong> pour vous déplacer !',10,1,'2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_steps` VALUES (7,'1.0.0','movement_limit_warning','show_characteristics',7.0,'info','Mouvements limités !','<strong>Attention !</strong> En jeu réel, vos mouvements sont <strong>limités</strong>. Vous avez {max_mvt} mouvements par tour. <strong>À partir de maintenant, chaque déplacement consommera 1 mouvement.</strong>',5,1,'2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_steps` VALUES (8,'1.0.0','show_characteristics','deplete_movements',8.0,'ui_interaction','Vos caractéristiques','Cliquez sur <strong>\"Caractéristiques\"</strong> pour voir vos stats, dont vos mouvements restants.',5,1,'2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_steps` VALUES (9,'1.0.0','deplete_movements','movements_depleted_info',9.0,'movement','Épuisez vos mouvements','Maintenant, <strong>déplacez-vous jusqu\'à épuiser vos {max_mvt} mouvements</strong>. Regardez le compteur diminuer !',15,1,'2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_steps` VALUES (10,'1.0.0','movements_depleted_info','actions_intro',10.0,'info','Plus de mouvements !','Vous n\'avez plus de mouvements ! En jeu réel, ils se régénèrent à chaque tour (toutes les 18h). Pour le tutoriel, on vous les restaure.',5,1,'2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_steps` VALUES (11,'1.0.0','actions_intro','click_yourself',11.0,'info','Les Actions','En plus des mouvements, vous avez des <strong>Points d\'Action (PA)</strong>. Ils permettent de fouiller, attaquer, récolter...',5,1,'2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_steps` VALUES (12,'1.0.0','click_yourself','actions_panel_info',12.0,'ui_interaction','Vos actions','<strong>Cliquez sur votre personnage</strong> pour voir les actions disponibles.',5,1,'2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_steps` VALUES (13,'1.0.0','actions_panel_info','close_card_for_tree',13.0,'info','Panneau d\'actions','Voici vos <strong>actions disponibles</strong> ! Chaque action consomme des PA. Nous allons en tester une : la récolte de ressources.',5,1,'2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_steps` VALUES (14,'1.0.0','close_card_for_tree','walk_to_tree',14.0,'ui_interaction','Direction l\'arbre','Fermez cette fiche. Nous allons aller vers un <strong>arbre</strong> pour le récolter.',5,1,'2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_steps` VALUES (15,'1.0.0','walk_to_tree','observe_tree',15.0,'movement','Approchez de l\'arbre','Déplacez-vous vers l\'<strong>arbre</strong> marqué sur le damier. Vous devez être sur une case <strong>adjacente</strong> pour le récolter.',10,1,'2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_steps` VALUES (16,'1.0.0','observe_tree','tree_info',16.0,'ui_interaction','Observer l\'arbre','<strong>Cliquez sur l\'arbre</strong> pour voir ses informations.',5,1,'2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_steps` VALUES (17,'1.0.0','tree_info','use_fouiller',17.0,'info','Ressource récoltable','Cet arbre est <strong>récoltable</strong> ! Vous voyez l\'indication \"récoltable\" sous le damier, en bas de votre écran.',5,1,'2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_steps` VALUES (18,'1.0.0','use_fouiller','action_consumed',18.0,'action','Fouiller !','Cliquez sur <strong>Fouiller</strong> pour récolter du bois de l\'arbre.',15,1,'2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_steps` VALUES (19,'1.0.0','action_consumed','open_inventory',20.0,'info','Action consommée','Vous avez récolté du <strong>bois</strong> ! Remarquez que l\'action a consommé <strong>1 PA</strong>. Vos PA se régénèrent aussi à chaque tour.',5,1,'2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_steps` VALUES (20,'1.0.0','open_inventory','inventory_wood',21.0,'ui_interaction','Votre inventaire','Ouvrez votre <strong>Inventaire</strong> pour voir le bois récolté.',5,1,'2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_steps` VALUES (21,'1.0.0','inventory_wood','close_inventory',22.0,'info','Du bois !','Voilà votre <strong>bois</strong> ! Les ressources récoltées vont dans votre inventaire. Vous pourrez les utiliser pour fabriquer des objets.',5,1,'2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_steps` VALUES (22,'1.0.0','close_inventory','combat_intro',23.0,'ui_interaction','Retour au jeu','Fermez l\'inventaire pour revenir au jeu. Cliquez sur <strong>Retour</strong>.',5,1,'2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_steps` VALUES (23,'1.0.0','combat_intro','enemy_spawned',24.0,'info','Le Combat','Maintenant, passons au <strong>combat</strong> ! C\'est essentiel pour survivre dans Olympia. Un ennemi d\'entraînement vous attend.',5,1,'2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_steps` VALUES (24,'1.0.0','enemy_spawned','walk_to_enemy',25.0,'info','Votre adversaire','Voici une <strong>âme d\'entraînement</strong> ! C\'est un ennemi inoffensif créé pour le tutoriel. Approchez-vous !',5,1,'2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_steps` VALUES (25,'1.0.0','walk_to_enemy','click_enemy',26.0,'movement','Approchez l\'ennemi','Déplacez-vous vers l\'<strong>âme d\'entraînement</strong>. Vous devez être sur une <strong>case adjacente</strong> pour attaquer.',10,1,'2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_steps` VALUES (26,'1.0.0','click_enemy','attack_enemy',27.0,'ui_interaction','Cibler l\'ennemi','<strong>Cliquez sur l\'âme d\'entraînement</strong> pour voir ses informations et l\'action d\'attaque.',5,1,'2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_steps` VALUES (27,'1.0.0','attack_enemy','attack_result',28.0,'combat','Attaquez !','Cliquez sur <strong>Attaquer</strong> pour frapper l\'âme d\'entraînement !',20,1,'2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_steps` VALUES (28,'1.0.0','attack_result','tutorial_complete',29.0,'info','Ennemi blessé !','Excellent ! Vous pouvez voir le <strong>résultat de l\'attaque</strong> : l\'ennemi a perdu des PV ! Regardez la barre rouge qui indique les dégâts.',5,1,'2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_steps` VALUES (29,'1.0.0','tutorial_complete',NULL,30.0,'info','Tutoriel terminé !','<strong>Félicitations !</strong> Vous avez terminé le tutoriel ! Vous savez maintenant vous déplacer, récolter des ressources et combattre. Bonne chance dans Olympia !',50,1,'2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_steps` VALUES (30,'2.0.0-craft','craft_welcome',NULL,1.0,'info','Bienvenue Artisan !','Dans ce tutoriel, vous apprendrez à récolter des ressources et à fabriquer des objets. L\'artisanat est essentiel pour créer votre équipement !',5,1,'2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_steps` VALUES (31,'2.0.0-craft','craft_open_menu','craft_walk_to_tree',2.0,'ui_interaction','Récolter des Ressources','Avant de pouvoir fabriquer quoi que ce soit, vous devez récolter des matières premières. Commençons par du bois !',5,1,'2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_steps` VALUES (32,'2.0.0-craft','craft_click_inventory','craft_observe_tree',3.0,'ui_interaction','Approchez de l\'arbre','Déplacez-vous à côté de l\'arbre pour pouvoir le récolter. Cliquez sur une case adjacente à l\'arbre.',5,1,'2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_steps` VALUES (33,'2.0.0-craft','craft_click_artisanat','craft_fouiller_tree',4.0,'ui_interaction','Ouvrir vos actions','Cliquez sur <strong>votre personnage</strong> pour voir vos actions disponibles, dont l\'action Fouiller.',5,1,'2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_steps` VALUES (34,'2.0.0-craft','craft_explain_interface','craft_got_wood',5.0,'info','Récolter le bois','Utilisez l\'action Fouiller pour récolter du bois de l\'arbre.',10,1,'2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_steps` VALUES (35,'2.0.0-craft','craft_complete','craft_walk_to_rock',6.0,'info','Bois obtenu !','Excellent ! Vous avez obtenu du bois. Maintenant, allons chercher de la pierre pour avoir plus d\'options de craft.',20,1,'2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_steps` VALUES (42,'2.0.0-craft','craft_walk_to_rock','craft_observe_rock',7.0,'movement','Approchez du rocher','Déplacez-vous à côté du rocher pour pouvoir le récolter.',5,1,'2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_steps` VALUES (43,'2.0.0-craft','craft_observe_rock','craft_fouiller_rock',8.0,'ui_interaction','Ouvrir vos actions','Cliquez sur <strong>votre personnage</strong> pour voir vos actions disponibles.',0,1,'2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_steps` VALUES (44,'2.0.0-craft','craft_fouiller_rock','craft_got_stone',9.0,'action','Récolter la pierre','Utilisez l\'action Fouiller pour récolter de la pierre.',10,1,'2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_steps` VALUES (45,'2.0.0-craft','craft_got_stone','craft_gather_stone_2_intro',10.0,'info','Pierre obtenue !','Parfait ! Vous avez maintenant du bois et de la pierre. Mais ça n\'est pas suffisant pour une pioche !',0,1,'2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_steps` VALUES (46,'2.0.0-craft','craft_gather_stone_2_intro','craft_fouiller_rock_2',10.1,'info','Ouvrir vos actions','Cliquez sur <strong>votre personnage</strong> pour voir vos actions disponibles.',0,1,'2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_steps` VALUES (48,'2.0.0-craft','craft_fouiller_rock_2','craft_got_stone_2',10.2,'action','Récolter une 2e pierre','Utilisez à nouveau l\'action <strong>Fouiller</strong> pour récolter une seconde pierre.',5,1,'2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_steps` VALUES (49,'2.0.0-craft','craft_got_stone_2','craft_open_inventory',10.3,'info','Pierres obtenues !','Excellent ! Vous avez maintenant <strong>1 bois</strong> et <strong>2 pierres</strong>. C\'est exactement ce qu\'il faut pour une pioche !',5,1,'2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_steps` VALUES (50,'2.0.0-craft','craft_open_inventory','craft_click_artisanat',11.0,'ui_interaction','Ouvrir l\'inventaire','Cliquez sur le bouton Inventaire dans le menu pour accéder à vos objets.',0,1,'2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_steps` VALUES (51,'2.0.0-craft','craft_click_artisanat','craft_interface_explain',12.0,'ui_interaction','Onglet Artisanat','Cliquez sur l\'onglet Artisanat pour voir les objets que vous pouvez fabriquer.',0,1,'2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_steps` VALUES (52,'2.0.0-craft','craft_interface_explain','craft_click_ingredient',13.0,'info','Interface d\'Artisanat','Voici vos ingrédients disponibles. Chaque objet affiché ici peut être utilisé dans une recette. Cliquez sur un ingrédient pour voir ce que vous pouvez fabriquer avec.',0,1,'2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_steps` VALUES (53,'2.0.0-craft','craft_click_ingredient','craft_recipes_explain',14.0,'ui_interaction','Choisir un ingrédient','Cliquez sur la <strong>pierre</strong> pour voir les recettes disponibles.',0,1,'2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_steps` VALUES (54,'2.0.0-craft','craft_recipes_explain','craft_do_craft',15.0,'info','Les Recettes','Voici les objets que vous pouvez fabriquer avec de la pierre. Chaque ligne montre les ingrédients nécessaires et le bouton pour crafter.',0,1,'2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_steps` VALUES (55,'2.0.0-craft','craft_do_craft','craft_success',16.0,'action','Fabriquer une Pioche','Cliquez sur le bouton <strong>Créer</strong> à côté de la Pioche pour la fabriquer. Une pioche nécessite 1 bois et 2 pierres !',15,1,'2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_steps` VALUES (56,'2.0.0-craft','craft_success','craft_return_game',17.0,'info','Fabrication réussie !','Bravo ! Vous avez fabriqué votre première <strong>pioche</strong>. Elle a été ajoutée à votre inventaire et vous permettra de miner des ressources plus efficacement !',0,1,'2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_steps` VALUES (57,'2.0.0-craft','craft_return_game','craft_complete',18.0,'ui_interaction','Retour au jeu','Cliquez sur Retour pour revenir au jeu.',0,1,'2026-04-19 08:23:30','2026-04-19 08:23:30');
INSERT INTO `tutorial_steps` VALUES (58,'2.0.0-craft','craft_complete',NULL,19.0,'info','Tutoriel Terminé !','Félicitations ! Vous maîtrisez maintenant les bases de l\'artisanat. Explorez le monde pour trouver des ressources rares et fabriquer des équipements puissants !',50,1,'2026-04-19 08:23:30','2026-04-19 08:23:30');
/*!40000 ALTER TABLE `tutorial_steps` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

