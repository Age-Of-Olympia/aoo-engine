<?php
namespace Tests\Action\Mock;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

class TestDatabaseActions
{
    public Connection $connection;

    public function __construct()
    {
        // Créer directement la connexion Doctrine DBAL sans passer par PDO
        $connectionParams = [
            'driver' => 'pdo_sqlite',
            'memory' => true, // Base de données SQLite en mémoire
        ];
        $this->connection = DriverManager::getConnection($connectionParams);
        
        $this->createTables();
    }

    private function createTables(): void
    {
        // Table actions
        $this->connection->executeStatement("
            CREATE TABLE actions (
                id INTEGER PRIMARY KEY,
                name VARCHAR(50),
                icon VARCHAR(50) NOT NULL,
                type VARCHAR(50) NOT NULL,
                display_name VARCHAR(50),
                text TEXT
            )
        ");

        // Table action_conditions
        $this->connection->executeStatement("
            CREATE TABLE action_conditions (
                id INTEGER PRIMARY KEY,
                conditionType VARCHAR(100) NOT NULL,
                parameters TEXT,
                action_id INTEGER NOT NULL,
                execution_order INTEGER DEFAULT NULL,
                blocking INTEGER NOT NULL DEFAULT 0
            )
        ");

        // Table action_outcomes
        $this->connection->executeStatement("
            CREATE TABLE action_outcomes (
                id INTEGER PRIMARY KEY,
                apply_to_self INTEGER NOT NULL DEFAULT 0,
                name VARCHAR(100),
                on_success INTEGER NOT NULL DEFAULT 1,
                action_id INTEGER NOT NULL
            )
        ");

        // Table outcome_instructions
        $this->connection->executeStatement("
            CREATE TABLE outcome_instructions (
                id INTEGER PRIMARY KEY,
                type VARCHAR(50) NOT NULL,
                parameters TEXT,
                orderIndex INTEGER NOT NULL DEFAULT 0,
                outcome_id INTEGER NOT NULL
            )
        ");

        // Insérer toutes les données
        $this->insertAllData();
    }

    private function insertAllData(): void
    {
        // Insérer toutes les actions
        $this->connection->executeStatement("
            INSERT INTO actions (id, name, icon, type, display_name, text) VALUES
                (1, 'melee', 'ra-crossed-swords', 'melee', 'Corps à corps', NULL),
                (2, 'distance', 'ra-arrow-cluster', 'distance', 'Tirer', NULL),
                (3, 'dmg1/pic_de_pierre', 'ra-drill', 'spell', 'Pic de Pierre', 'Projette un pic de pierre sur l''adversaire.'),
                (4, 'dps/poings_pierre', 'ra-barbed-arrow', 'spell', 'Poings de Pierre', 'Vos poings deviennent durs comme de la roche millénaire, que vous abattez sur vos ennemis.'),
                (5, 'soins/barbier', 'ra-cut-palm', 'heal', 'Barbier', 'Petites et grandes chirurgies des blessés.'),
                (6, 'special/attaque_sautee', 'ra-axe-swing', 'technique', 'Attaque Sautée', 'Avec une arme de mêlée, déplace immédiatement le personnage au contact de la cible et lui inflige des dégâts magiques.'),
                (7, 'vol_a_la_tire', 'ra-nuclear', 'steal', 'Vol à la tire', 'Dérobe des pièces d''or !'),
                (8, 'repos', 'ra-campfire', 'rest', 'Repos', NULL),
                (9, 'courir', 'ra-boot-stomp', 'run', 'Courir', NULL),
                (10, 'esquive/cle_de_bras', 'ra-bear-trap', 'technique', 'Clé de bras', 'Pare la prochaine attaque de mêlée et immobilise l''adversaire (uniquement à Mains nues).'),
                (11, 'prier', 'ra-crowned-heart', 'pray', 'Prier', NULL),
                (12, 'fouiller', 'ra-clover', 'search', 'Fouiller', NULL),
                (13, 'entrainement', 'ra-archery-target', 'train', 'Entraînement', NULL),
                (14, 'dmg1/lame_volante', 'ra-spinning-sword', 'spell', 'Lame Volante', 'Projette une lame sur l''adversaire. '),
                (15, 'soins/imposition_des_mains', 'ra-hand-emblem', 'heal', 'Imposition des Mains', 'Toucher magique pour soigner un blessé.'),
                (16, 'dmg1/fleche_aquatique', 'ra-thorn-arrow', 'spell', 'Flèche Aquatique', 'Projette une flèche composée d''eau sur l''adversaire.'),
                (17, 'tuto/attaquer', 'ra-crossed-swords', 'melee', 'Attaquer', NULL),
                (18, 'dmg2/frappe_vicieuse', 'ra-diving-dagger', 'technique', 'Frappe Vicieuse', 'Ignore l''armure de l''adversaire.'),
                (19, 'dps/glaciation', 'ra-barbed-arrow', 'spell', 'Glaciation', 'Vous devenez froids comme l''hiver et propagez cette froideur à un ennemi proche.'),
                (20, 'dmg1/boule_de_magma', 'ra-burning-eye', 'spell', 'Boule de Magma', 'Lance une boule de lave en fusion sur l''adversaire, qui prend feu.'),
                (21, 'dmg2/uppercut', 'ra-tooth', 'technique', 'Uppercut', 'Double les dégâts sur une attaque aux poings.'),
                (22, 'dmg2/assomoir', 'ra-cracked-helm', 'technique', 'Assommoir', 'Ignore le casque (chance de critique).'),
                (23, 'dmg1/aiguillon', 'ra-barbed-arrow', 'spell', 'Aiguillon', 'Projette un aiguillon sur l''adversaire.'),
                (24, 'dmg1/dard', 'ra-kunai', 'spell', 'Dard', 'Projette un dard sur l''adversaire.'),
                (25, 'special/arme_vivante', 'ra-spiked-tentacle', 'spell', 'Arme Vivante', 'Ajoute des dégâts magiques à une attaque avec une arme composée de bois (pétrifié ou non).'),
                (26, 'corrupt/corruption_du_bois', 'ra-biohazard', 'spell', 'Corruption du Bois', 'Augmente la chance que l''équipement en bois de l''adversaire se casse.'),
                (27, 'soins/regeneration', 'ra-cycle', 'heal', 'Régénération', 'Au contact des éléments, régénère la santé.'),
                (28, 'soins/flux_vital', 'ra-level-two', 'heal', 'Flux Vital', 'Puise dans la régénération magique pour se soigner.'),
                (29, 'dmg2/desarmement', 'ra-hand', 'technique', 'Désarmement', 'Désarme l''adversaire.'),
                (30, 'dps/soumission', 'ra-barbed-arrow', 'spell', 'Soumission divine', 'Vous vous approchez de votre cible et déferlez un torrent d''énergie divine dans sa direction.'),
                (31, 'special/lame_benie', 'ra-lightning-sword', 'spell', 'Lame bénie', 'Ajoute des dégâts magiques lors d''une attaque de mêlée.'),
                (32, 'dps/souffle_cime', 'ra-barbed-arrow', 'spell', 'Souffle des cimes', 'Imprégné de la puissance de l''orage, vous rugissez un flot de foudre sur votre adversaire.'),
                (33, 'soins/lien_de_vie', 'ra-level-two-advanced', 'heal', 'Lien de Vie', 'Puise dans la régénération magique pour se soigner.'),
                (34, 'esquive/parade', 'ra-sword', 'technique', 'Parade', 'Pare la prochaine attaque de mêlée de l''adversaire (nécessite une arme de mêlée).'),
                (35, 'special/meteore', 'ra-burning-meteor', 'spell', 'Météore', 'Ajoute des dégâts magiques et de feu à une attaque au jet de pierre.'),
                (36, 'special/trait_beni', 'ra-supersonic-arrow', 'spell', 'Trait Béni', 'Ajoute des dégâts magiques lors d''une attaque au tir.'),
                (37, 'special/tai_otoshi', 'ra-falling', 'technique', 'Tai Otoshi', 'Projette l''adversaire.'),
                (38, 'esquive/leurre', 'ra-lava', 'technique', 'Leurre', 'Leurre la prochaine attaque magique de l''adversaire.'),
                (39, 'enchant/enchantement_de_boucliers', 'ra-heavy-shield', 'technique', 'Enchantement de Boucliers', 'Enchante le bouclier équipé (il devient alors incassable).'),
                (40, 'enchant/enchantement_d_armures', 'ra-vest', 'technique', 'Enchantement d''Armures', 'Enchante l''armure équipée (elle devient alors incassable).'),
                (41, 'esquive/pas_de_cote', 'ra-player-dodge', 'technique', 'Pas de Côté', 'Esquive la prochaine attaque physique en vous déplaçant aléatoirement d''une case (nécessite 1Mvt).'),
                (42, 'dps/taillade', 'ra-barbed-arrow', 'spell', 'Taillade illusoire', 'Des crocs et des griffes spectraux assaillent votre adversaire de toute part'),
                (43, 'brancardier', 'ra-cut-palm', 'heal', 'Brancardier', 'Va falloir soigner tout ça...'),
                (44, 'bibinouze', 'ra-cut-palm', 'heal', 'Bibinouze', 'Ca fait du bien là où ça passe.')
        ");

        // Insérer toutes les conditions
        $this->connection->executeStatement("
            INSERT INTO action_conditions (id, conditionType, parameters, action_id, execution_order, blocking) VALUES
                (1, 'RequiresDistance', '{\"max\":1}', 1, 0, 1),
                (2, 'MeleeCompute', '{\"actorRollType\":\"cc\", \"targetRollType\": \"cc/agi\"}', 1, 10, 0),
                (3, 'RequiresTraitValue', '{ \"a\": 1 }', 1, 7, 1),
                (5, 'RequiresWeaponType', '{\"type\": [\"melee\"]}', 1, 1, 1),
                (6, 'RequiresDistance', '{\"min\":2}', 2, 0, 1),
                (7, 'DistanceCompute', '{\"actorRollType\":\"ct\", \"targetRollType\": \"cc/agi\"}', 2, 10, 0),
                (8, 'RequiresTraitValue', '{ \"a\": 1 }', 2, 3, 1),
                (10, 'RequiresWeaponType', '{\"type\": [\"tir\",\"jet\"]}', 2, 1, 1),
                (11, 'RequiresAmmo', '{}', 2, 4, 1),
                (12, 'RequiresDistance', '{\"min\":2}', 3, 0, 1),
                (13, 'RequiresTraitValue', '{ \"a\": 1, \"pm\": 4 }', 3, 3, 1),
                (14, 'SpellCompute', '{\"actorRollType\":\"fm\", \"targetRollType\": \"fm\"}', 3, 10, 0),
                (15, 'RequiresDistance', '{\"max\":1}', 4, 0, 1),
                (16, 'RequiresTraitValue', '{ \"a\": 1, \"pm\": 8 }', 4, 3, 1),
                (17, 'SpellCompute', '{\"actorRollType\":\"fm\", \"targetRollType\": \"fm\"}', 4, 10, 0),
                (18, 'RequiresDistance', '{\"max\":1}', 5, 0, 1),
                (19, 'RequiresTraitValue', '{ \"a\": 1, \"pm\": 8 }', 5, 3, 1),
                (20, 'RequiresDistance', '{\"min\":2}', 6, 0, 1),
                (21, 'RequiresTraitValue', '{ \"a\": 1, \"pm\": 12 }', 6, 7, 1),
                (22, 'MeleeCompute', '{\"actorRollType\":\"cc\", \"targetRollType\": \"cc/agi\"}', 6, 10, 0),
                (24, 'RequiresWeaponType', '{\"type\": [\"melee\"]}', 6, 1, 1),
                (25, 'RequiresDistance', '{\"max\":1}', 7, 0, 1),
                (26, 'RequiresTraitValue', '{ \"a\": 1 }', 7, 5, 1),
                (27, 'Compute', '{\"actorRollType\":\"agi\", \"targetRollType\": \"agi\"}', 7, 10, 0),
                (28, 'ForbidIfHasEffect', '{ \"actorEffect\": \"adrenaline\", \"targetEffect\" : \"adrenaline\" }', 7, 6, 1),
                (29, 'RequiresDistance', '{\"max\":0}', 8, 0, 1),
                (30, 'RequiresTraitValue', '{ \"a\": 1 }', 8, 5, 1),
                (31, 'RequiresTraitValue', '{ \"a\": 1 }', 9, 1, 1),
                (32, 'RequiresTraitValue', '{ \"repos\": \"effets\" }', 8, 1, 1),
                (33, 'RequiresTraitValue', '{ \"a\": 1, \"pm\": 7 }', 10, 3, 1),
                (34, 'ForbidIfHasEffect', '{ \"actorEffect\": \"cle_de_bras\" }', 10, 6, 1),
                (35, 'RequiresTraitValue', '{ \"a\": 1 }', 11, 2, 1),
                (36, 'RequiresDistance', '{\"max\":0}', 11, 0, 1),
                (37, 'RequiresGodAffiliation', '{}', 11, 1, 1),
                (38, 'RequiresTraitValue', '{ \"a\": 1 }', 12, 2, 1),
                (39, 'RequiresDistance', '{\"max\":0}', 12, 0, 1),
                (40, 'RequiresResource', '{}', 12, 1, 1),
                (41, 'RequiresTraitValue', '{ \"energie\": \"both\" }', 13, 1, 1),
                (42, 'Option', '{\"option\": \"noTrain\"}', 13, 0, 1),
                (43, 'RequiresTraitValue', '{ \"a\": 1 }', 13, 2, 1),
                (44, 'RequiresDistance', '{\"max\":1}', 13, 0, 1),
                (45, 'RequiresDistance', '{\"min\":2}', 14, 0, 1),
                (46, 'RequiresTraitValue', '{ \"a\": 1, \"pm\": 4 }', 14, 3, 1),
                (48, 'SpellCompute', '{\"actorRollType\":\"fm\", \"targetRollType\": \"fm\"}', 14, 10, 0),
                (49, 'RequiresDistance', '{\"max\":1}', 15, 1, 1),
                (50, 'RequiresTraitValue', '{ \"a\": 1, \"pm\": 7 }', 15, 3, 1),
                (51, 'AntiSpell', '{}', 15, 0, 1),
                (52, 'RequiresDistance', '{\"min\":2}', 16, 0, 1),
                (53, 'RequiresTraitValue', '{ \"a\": 1, \"pm\": 5 }', 16, 3, 1),
                (54, 'SpellCompute', '{\"actorRollType\":\"fm\", \"targetRollType\": \"fm\"}', 16, 10, 0),
                (55, 'RequiresDistance', '{\"max\":1}', 17, 0, 1),
                (56, 'RequiresDistance', '{\"max\":1}', 18, 0, 1),
                (57, 'RequiresTraitValue', '{ \"a\": 1, \"pm\": 7 }', 18, 3, 1),
                (58, 'TechniqueCompute', '{\"actorRollType\":\"cc\", \"targetRollType\": \"cc/agi\"}', 18, 10, 0),
                (59, 'RequiresDistance', '{\"max\":2}', 19, 0, 1),
                (60, 'RequiresTraitValue', '{ \"a\": 1, \"pm\": 8 }', 19, 3, 1),
                (61, 'SpellCompute', '{\"actorRollType\":\"fm\", \"targetRollType\": \"fm\"}', 19, 10, 0),
                (62, 'RequiresDistance', '{\"min\":2}', 20, 0, 1),
                (63, 'RequiresTraitValue', '{ \"a\": 1, \"pm\": 7 }', 20, 3, 1),
                (64, 'SpellCompute', '{\"actorRollType\":\"fm\", \"targetRollType\": \"fm\"}', 20, 10, 0),
                (65, 'RequiresDistance', '{\"max\":1}', 21, 0, 1),
                (66, 'RequiresTraitValue', '{ \"a\": 1, \"pm\": 8 }', 21, 3, 1),
                (67, 'TechniqueCompute', '{\"actorRollType\":\"cc\", \"targetRollType\": \"cc/agi\"  }', 21, 10, 0),
                (68, 'RequiresDistance', '{\"max\":1}', 22, 0, 1),
                (69, 'RequiresTraitValue', '{ \"a\": 1, \"pm\": 7 }', 22, 3, 1),
                (70, 'TechniqueCompute', '{\"actorRollType\":\"cc\", \"targetRollType\": \"cc/agi\"  }', 22, 10, 0),
                (71, 'RequiresDistance', '{\"min\":2}', 23, 0, 1),
                (72, 'RequiresTraitValue', '{ \"a\": 1, \"pm\": 4 }', 23, 3, 1),
                (73, 'SpellCompute', '{\"actorRollType\":\"fm\", \"targetRollType\": \"fm\"}', 23, 10, 0),
                (74, 'RequiresDistance', '{\"min\":2}', 24, 0, 1),
                (75, 'RequiresTraitValue', '{ \"a\": 1, \"pm\": 3 }', 24, 3, 1),
                (76, 'SpellCompute', '{\"actorRollType\":\"fm\", \"targetRollType\": \"fm\"}', 24, 10, 0),
                (77, 'RequiresDistance', '{\"max\":1}', 25, 0, 1),
                (78, 'RequiresTraitValue', '{ \"a\": 1, \"pm\": 12 }', 25, 3, 1),
                (79, 'SpellCompute', '{\"actorRollType\":\"cc\", \"targetRollType\": \"cc/agi\"  }', 25, 10, 0),
                (80, 'ForbidIfHasEffect', '{ \"targetEffects\" : [\"corruption_du_metal\",\"corruption_du_bronze\",\"corruption_du_cuir\",\"corruption_des_plantes\"] }', 26, 6, 1),
                (81, 'RequiresTraitValue', '{ \"a\": 1, \"pm\": 7 }', 26, 3, 1),
                (82, 'SpellCompute', '{\"actorRollType\":\"fm\", \"targetRollType\": \"fm\"}', 26, 10, 0),
                (83, 'RequiresDistance', '{\"max\":1}', 27, 0, 1),
                (84, 'RequiresTraitValue', '{ \"a\": 1, \"pm\": 6 }', 27, 3, 1),
                (85, 'AntiSpell', '{}', 27, 1, 1),
                (86, 'ForbidIfHasEffect', '{\"actorEffect\": \"poison\"}', 27, 2, 1),
                (87, 'RequiresDistance', '{\"max\":1}', 28, 0, 1),
                (88, 'RequiresTraitValue', '{ \"a\": 1, \"pm\": 6 }', 28, 3, 1),
                (89, 'AntiSpell', '{}', 28, 1, 1),
                (90, 'ForbidIfHasEffect', '{\"actorEffect\": \"poison_magique\"}', 28, 2, 1),
                (91, 'RequiresDistance', '{\"max\":1}', 29, 0, 1),
                (92, 'RequiresTraitValue', '{ \"a\": 1, \"pm\": 7 }', 29, 3, 1),
                (93, 'TechniqueCompute', '{\"actorRollType\":\"cc\", \"targetRollType\": \"cc/agi\"  }', 29, 10, 0),
                (94, 'RequiresDistance', '{\"max\":2}', 30, 0, 1),
                (95, 'RequiresTraitValue', '{ \"a\": 1, \"pm\": 8 }', 30, 3, 1),
                (96, 'SpellCompute', '{\"actorRollType\":\"fm\", \"targetRollType\": \"fm\"  }', 30, 10, 0),
                (97, 'RequiresDistance', '{\"max\":1}', 31, 0, 1),
                (98, 'RequiresTraitValue', '{ \"a\": 1, \"pm\": 12 }', 31, 3, 1),
                (99, 'TechniqueCompute', '{\"actorRollType\":\"cc\", \"targetRollType\": \"cc/agi\"  }', 31, 10, 0),
                (100, 'RequiresDistance', '{\"max\":2}', 32, 0, 1),
                (101, 'RequiresTraitValue', '{ \"a\": 1, \"pm\": 8 }', 32, 3, 1),
                (102, 'SpellCompute', '{\"actorRollType\":\"fm\", \"targetRollType\": \"fm\"  }', 32, 10, 0),
                (103, 'RequiresDistance', '{\"max\":1}', 33, 0, 1),
                (104, 'RequiresTraitValue', '{ \"a\": 1, \"pm\": 6 }', 33, 3, 1),
                (105, 'AntiSpell', '{}', 33, 1, 1),
                (106, 'ForbidIfHasEffect', '{\"actorEffect\": \"poison_magique\"}', 33, 2, 1),
                (107, 'RequiresTraitValue', '{ \"a\": 1, \"pm\": 7 }', 34, 3, 1),
                (108, 'ForbidIfHasEffect', '{\"actorEffect\": \"parade\"}', 34, 2, 1),
                (109, 'RequiresWeaponCraftedWith', '{\"craftedWith\":[\"bois\",\"bois_petrifie\"], \"location\": [\"main1\"]}', 25, 1, 1),
                (110, 'RequiresDistance', '{\"min\":2}', 35, 0, 1),
                (111, 'RequiresAmmo', '{\"itemId\": 86}', 35, 1, 1),
                (112, 'RequiresTraitValue', '{ \"a\": 1, \"pm\": 12 }', 35, 3, 1),
                (113, 'SpellCompute', '{\"actorRollType\":\"ct\", \"targetRollType\": \"cc/agi\"  }', 35, 10, 0),
                (114, 'RequiresWeaponType', '{\"type\": [\"melee\"]}', 31, 1, 1),
                (115, 'RequiresDistance', '{\"min\":2}', 36, 0, 1),
                (116, 'RequiresTraitValue', '{ \"a\": 1, \"pm\": 12 }', 36, 3, 1),
                (117, 'RequiresWeaponType', '{\"type\": [\"tir\"]}', 36, 1, 1),
                (118, 'SpellCompute', '{\"actorRollType\":\"ct\", \"targetRollType\": \"cc/agi\" }', 36, 10, 0),
                (119, 'RequiresDistance', '{\"max\":1}', 37, 0, 1),
                (120, 'RequiresTraitValue', '{ \"a\": 1, \"pm\": 1 }', 37, 3, 1),
                (121, 'RequiresWeaponType', '{\"type\": [\"melee\"]}', 37, 1, 1),
                (122, 'TechniqueCompute', '{\"actorRollType\":\"agi\", \"targetRollType\": \"agi\"  }', 37, 10, 0),
                (123, 'RequiresTraitValue', '{ \"a\": 1, \"pm\": 8 }', 38, 3, 1),
                (124, 'ForbidIfHasEffect', '{\"actorEffect\": \"leurre\"}', 38, 1, 1),
                (125, 'RequiresDistance', '{\"max\":0}', 39, 0, 1),
                (126, 'RequiresTraitValue', '{ \"a\": 1, \"pm\": 10 }', 39, 3, 1),
                (127, 'RequiresWeaponType', '{ \"type\": [\"bouclier\"], \"location\": [\"main2\"] }', 39, 1, 1),
                (128, 'ForbidOnEquipedObjectStatus', '{ \"location\" : \"main2\" }', 39, 2, 1),
                (129, 'RequiresDistance', '{\"max\":0}', 40, 0, 1),
                (130, 'RequiresTraitValue', '{ \"a\": 1, \"pm\": 10 }', 40, 3, 1),
                (131, 'RequiresWeaponType', '{ \"type\": [\"armure\"], \"location\": [\"tronc\"] }', 40, 1, 1),
                (132, 'ForbidOnEquipedObjectStatus', '{ \"location\" : \"tronc\" }', 40, 2, 1),
                (133, 'RequiresTraitValue', '{ \"a\": 1, \"pm\": 7, \"mvt\": 1 }', 41, 3, 1),
                (134, 'ForbidIfHasEffect', '{ \"name\" : \"pas_de_cote\" }', 41, 2, 1),
                (135, 'RequiresDistance', '{\"max\":3}', 42, 0, 1),
                (136, 'RequiresTraitValue', '{ \"a\": 1, \"pm\": 10 }', 42, 3, 1),
                (137, 'SpellCompute', '{\"actorRollType\":\"fm\", \"targetRollType\": \"fm\"}', 42, 10, 0)
        ");

        // Insérer tous les outcomes
        $this->connection->executeStatement("
            INSERT INTO action_outcomes (id, apply_to_self, name, on_success, action_id) VALUES
                (1, 0, 'melee_damage', 1, 1),
                (3, 0, 'distance_damage', 1, 2),
                (4, 0, 'spell_damage', 1, 3),
                (7, 0, 'spell_damage', 1, 4),
                (9, 0, 'technique_healing', 1, 5),
                (10, 0, 'melee_damage', 1, 6),
                (11, 0, 'steal_effect', 1, 7),
                (12, 0, 'steal_effect', 0, 7),
                (13, 1, 'rest_effect', 1, 8),
                (14, 1, 'run_effect', 1, 9),
                (15, 1, 'dodge_effect', 1, 10),
                (16, 1, 'pray_effect', 1, 11),
                (17, 1, 'search_effect', 1, 12),
                (18, 0, 'train_effect', 1, 13),
                (19, 0, 'spell_damage', 1, 14),
                (20, 0, 'spell_healing', 1, 15),
                (21, 0, 'spell_damage', 1, 16),
                (22, 0, 'tuto_attack_effect', 1, 17),
                (23, 0, 'technique_damage', 1, 18),
                (24, 0, 'spell_damage', 1, 19),
                (25, 0, 'spell_damage', 1, 20),
                (26, 0, 'technique_damage', 1, 21),
                (27, 0, 'technique_damage', 1, 22),
                (28, 0, 'spell_damage', 1, 23),
                (29, 0, 'spell_damage', 1, 24),
                (30, 0, 'spell_damage', 1, 25),
                (32, 0, 'spell_corrupt', 1, 26),
                (33, 0, 'spell_healing', 1, 27),
                (34, 1, 'spell_healing', 1, 28),
                (35, 0, 'technique_damage', 1, 29),
                (36, 0, 'spell_damage', 1, 30),
                (37, 0, 'spell_damage', 1, 31),
                (38, 0, 'spell_damage', 1, 32),
                (39, 0, 'spell_healing', 1, 33),
                (40, 0, 'dodge_effect', 1, 34),
                (41, 0, 'spell_damage', 1, 35),
                (42, 0, 'spell_damage', 1, 36),
                (43, 0, 'technique_damage', 1, 37),
                (44, 1, 'dodge_effect', 1, 38),
                (45, 1, 'technique_enchant', 1, 39),
                (46, 1, 'technique_enchant', 1, 40),
                (47, 1, 'dodge_effect', 1, 41),
                (48, 1, 'attaque_sautee_failed', 0, 6),
                (49, 0, 'spell_damage', 1, 42),
                (50, 0, 'technique_healing', 1, 43),
                (51, 0, 'technique_healing', 1, 44)
        ");

        // Insérer toutes les instructions
        $this->connection->executeStatement("
            INSERT INTO outcome_instructions (id, type, parameters, orderIndex, outcome_id) VALUES
                (1, 'lifeloss', '{ \"actorDamagesTrait\": \"f\", \"targetDamagesTrait\": \"e\" }', 0, 1),
                (3, 'damageobject', '{}', 0, 1),
                (4, 'lifeloss', '{ \"actorDamagesTrait\": \"f\", \"targetDamagesTrait\": \"e\", \"distance\": true }', 0, 3),
                (6, 'lifeloss', '{ \"actorDamagesTrait\": \"m\", \"targetDamagesTrait\": \"m\", \"bonusDamagesTrait\": 3 }', 0, 4),
                (8, 'lifeloss', '{ \"actorDamagesTrait\": \"m\", \"targetDamagesTrait\": \"m\", \"bonusDamagesTrait\": 8 }', 0, 7),
                (10, 'healing', '{ \"actorHealingTrait\": \"agi\" }', 0, 9),
                (11, 'lifeloss', '{ \"actorDamagesTrait\": \"f\", \"targetDamagesTrait\": \"e\", \"bonusDamagesTrait\": \"m\", \"bonusDefenseTrait\": \"m\" }', 3, 10),
                (13, 'teleport', '{ \"coords\": \"target\" }', 1, 10),
                (14, 'object', '{\"action\":\"steal\", \"object\": 1 }', 0, 11),
                (15, 'applystatus', '{ \"adrenaline\": true, \"duration\": 172800 }', 0, 12),
                (16, 'applystatus', '{ \"finished\": true, \"player\": \"actor\" }', 10, 13),
                (17, 'player', '{\"carac\":\"fatigue\", \"value\": 4, \"player\": \"actor\"}', 0, 13),
                (18, 'player', '{\"carac\": \"mvt\", \"value\" : 1, \"player\": \"actor\"}', 0, 14),
                (19, 'applystatus', '{ \"cle_de_bras\": true, \"player\": \"actor\", \"duration\": 0 }', 0, 15),
                (20, 'player', '{\"carac\": \"foi\", \"player\": \"actor\"}', 0, 16),
                (21, 'resource', '{}', 0, 17),
                (22, 'onlylog', '{}', 0, 18),
                (23, 'damageobject', '{}', 0, 3),
                (24, 'damageobject', '{}', 0, 10),
                (25, 'lifeloss', '{ \"actorDamagesTrait\": \"m\", \"targetDamagesTrait\": \"m\", \"bonusDamagesTrait\": 3 }', 0, 19),
                (26, 'healing', '{ \"actorHealingTrait\": \"m\", \"bonusHealingTrait\": 3 }', 0, 20),
                (27, 'lifeloss', '{ \"actorDamagesTrait\": \"m\", \"targetDamagesTrait\": \"m\", \"bonusDamagesTrait\": 3 }', 0, 21),
                (28, 'applystatus', '{ \"eau\": true, \"player\": \"target\", \"duration\": 172800 }', 10, 21),
                (29, 'removeaction', '{\"action\":\"tuto/attaquer\"}', 0, 22),
                (30, 'addraceactions', '{}', 1, 22),
                (31, 'teleport', '{ \"coords\": \"x,y,z,gaia2\" }', 2, 22),
                (32, 'lifeloss', '{ \"actorDamagesTrait\": \"f\", \"targetDamagesTrait\": \"e\", \"bonusDamagesTrait\": 2, \"targetIgnore\": [\"tronc\"] }', 3, 23),
                (33, 'lifeloss', '{ \"actorDamagesTrait\": \"m\", \"targetDamagesTrait\": \"m\", \"bonusDamagesTrait\": 6 }', 0, 24),
                (34, 'lifeloss', '{ \"actorDamagesTrait\": \"m\", \"targetDamagesTrait\": \"m\", \"bonusDamagesTrait\": 3 }', 0, 25),
                (35, 'applystatus', '{ \"feu\": true, \"player\": \"target\", \"duration\": 172800 }', 10, 25),
                (36, 'lifeloss', '{ \"actorDamagesTrait\": \"f\", \"targetDamagesTrait\": \"e\", \"actorIgnore\": [\"main1\"], \"autoCrit\": true }', 0, 26),
                (37, 'lifeloss', '{ \"actorDamagesTrait\": \"f\", \"targetDamagesTrait\": \"e\", \"targetIgnore\": [\"tete\"], \"bonusDamagesTrait\": 4 }', 0, 27),
                (38, 'lifeloss', '{ \"actorDamagesTrait\": \"m\", \"targetDamagesTrait\": \"m\", \"bonusDamagesTrait\": 3 }', 0, 28),
                (39, 'lifeloss', '{ \"actorDamagesTrait\": \"m\", \"targetDamagesTrait\": \"m\", \"bonusDamagesTrait\": 1 }', 0, 29),
                (40, 'lifeloss', '{ \"actorDamagesTrait\": \"f\", \"targetDamagesTrait\": \"e\", \"bonusDamagesTrait\": \"m\", \"bonusDefenseTrait\": \"m\"}', 0, 30),
                (41, 'applystatus', '{ \"corruption_du_bois\": true, \"player\": \"target\", \"duration\": 259200 }', 0, 32),
                (42, 'healing', '{ \"actorHealingTrait\": \"r\" }', 0, 33),
                (43, 'applystatus', '{ \"poison\": true, \"player\": \"actor\", \"duration\": 0 }', 10, 33),
                (44, 'healing', '{ \"actorHealingTrait\": \"rm\" }', 0, 34),
                (45, 'applystatus', '{ \"poison_magique\": true, \"player\": \"actor\", \"duration\": 0 }', 10, 34),
                (46, 'lifeloss', '{ \"actorDamagesTrait\": \"f\", \"targetDamagesTrait\": \"e\", \"bonusDamagesTrait\": 4 }', 0, 35),
                (47, 'lifeloss', '{ \"actorDamagesTrait\": \"m\", \"targetDamagesTrait\": \"m\", \"bonusDamagesTrait\": 6}', 0, 36),
                (48, 'lifeloss', '{ \"actorDamagesTrait\": \"f\", \"targetDamagesTrait\": \"e\", \"bonusDefenseTrait\": \"m\", \"bonusDamagesTrait\": \"m\" }', 0, 37),
                (49, 'lifeloss', '{ \"actorDamagesTrait\": \"m\", \"targetDamagesTrait\": \"m\", \"bonusDamagesTrait\": 6}', 0, 38),
                (50, 'healing', '{ \"actorHealingTrait\": \"rm\" }', 0, 39),
                (51, 'applystatus', '{ \"poison_magique\": true, \"player\": \"actor\", \"duration\": 0 }', 10, 39),
                (52, 'applystatus', '{ \"parade\": true, \"player\": \"actor\", \"duration\": 0 }', 0, 40),
                (53, 'lifeloss', '{ \"actorDamagesTrait\": \"f\", \"targetDamagesTrait\": \"e\", \"bonusDamagesTrait\": \"m\", \"bonusDefenseTrait\": \"m\" }', 0, 41),
                (54, 'applystatus', '{ \"feu\": true, \"player\": \"target\", \"duration\": 172800 }', 0, 41),
                (55, 'dropweapon', '{ \"targetLocation\": \"main1\", \"dropChance\": 10 }', 0, 35),
                (56, 'lifeloss', '{ \"actorDamagesTrait\": \"f\", \"targetDamagesTrait\": \"e\", \"bonusDamagesTrait\": \"m\", \"bonusDefenseTrait\": \"m\" }', 0, 42),
                (57, 'lifeloss', '{ \"actorDamagesTrait\": \"f\", \"targetDamagesTrait\": \"e\", \"bonusDamagesTrait\": \"-3\"}', 0, 43),
                (58, 'teleport', '{ \"coords\": \"projected\" }', 0, 43),
                (59, 'applystatus', '{ \"leurre\": \"true\", \"player\": \"actor\", \"duration\": 86400 }', 0, 44),
                (60, 'enchant', '{ \"location\": \"main2\" }', 0, 45),
                (61, 'enchant', '{ \"location\": \"tronc\" }', 0, 46),
                (62, 'player', '{\"carac\" : \"fatigue\", \"value\": 1, \"player\" : \"target\"}', 2, 18),
                (63, 'tiletype', '{}', 1, 14),
                (64, 'applystatus', '{ \"pas_de_cote\": true, \"player\": \"actor\", \"duration\": 0 }', 0, 47),
                (65, 'teleport', '{ \"coords\": \"target\" }', 1, 48),
                (66, 'lifeloss', '{ \"actorDamagesTrait\": \"m\", \"targetDamagesTrait\": \"m\", \"bonusDamagesTrait\": 6 }', 0, 49),
                (67, 'healing', '{ \"actorHealingTrait\": 50 }', 0, 50),
                (68, 'healing', '{ \"actorPMHealingTrait\": 50 }', 0, 51)
        ");
    }

    public function exe(string $sql, array $params = [], bool $getAffectedRows = false)
    {
        if ($getAffectedRows) {
            return $this->connection->executeStatement($sql, $params);
        }
        
        $stmt = $this->connection->prepare($sql);
        return new TestDatabaseResult($stmt->executeQuery($params));
    }

    public function insert(string $table, array $values): bool
    {
        $columns = implode(', ', array_keys($values));
        $placeholders = ':' . implode(', :', array_keys($values));
        
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        
        return $this->connection->executeStatement($sql, $values) > 0;
    }

    public function start_transaction(string $name): void
    {
        $this->connection->beginTransaction();
    }

    public function commit_transaction(string $name): void
    {
        $this->connection->commit();
    }

    public function rollback_transaction(string $name): void
    {
        $this->connection->rollBack();
    }
}

class TestDatabaseResult
{
    private $result;

    public function __construct($result)
    {
        $this->result = $result;
    }

    public function fetch_object(): ?object
    {
        $row = $this->result->fetchAssociative();
        if ($row === false || $row === null) {
            return null;
        }
        return (object) $row;
    }
}