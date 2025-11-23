INSERT INTO actions (name, icon, type, display_name, text, niveau, race)
VALUES 
(
    'epuisement','ra-crossed-axes','technique','Épuisement',
    'Attaque pure de corps-à-corps qui vise à épuiser l''adversaire plutôt que le blesser.',
     1, null
),
(
    'attaque_precise','ra-crossed-axes','technique','Attaque précise',
    'Attaque de corps-à-corps plus précise au détriment des dégâts.',
     1, null
),
(
    'attaque_violente','ra-crossed-axes','technique','Attaque violente',
    'Attaque de corps-à-corps plus puissante au détriment de la précision.',
     1, null
),
(
    'croc-en-jambe','ra-crossed-axes','technique','Croc-en-jambe',
    'Attaque de corps-à-corps visant les jambes pour ralentir l''adversaire.',
     2, null
),
(
    'manchette','ra-crossed-axes','technique','Manchette',
    'Attaque pure de corps-à-corps qui vise à gêner les capacités offensives de l''adversaire plutôt que le blesser.',
     2, null
),
(
    'griffes','ra-crossed-axes','passif','Griffes',
    'Des griffes acérées pour trancher les chairs à mains nues.',
     2, 'hs'
),
(
    'arme_infusee','ra-crossed-axes','technique','Arme infusée',
    'Attaque de corps-à-corps infusée de magie.',
     3, null
),
(
    'tir_epuisant','ra-crossbow','technique','Tir épuisant',
    'Attaque pure de tir qui vise à épuiser l''adversaire plutôt que le blesser.',
     1, null
),
(
    'tir_precis','ra-crossbow','technique','Tire précis',
    'Attaque à distance plus précise au détriment des dégâts.',
     1, null
),
(
    'tir_violent','ra-crossbow','technique','Tir violent',
    'Attaque à distance plus puissante au détriment de la précision.',
     1, null
),
(
    'tir_a_la_cheville','ra-crossbow','technique','Tir à la cheville',
    'Attaque de tir visant les jambes pour ralentir l''adversaire.',
     2, null
),
(
    'tir_handicapant','ra-crossbow','technique','Tir handicapant',
    'Attaque pure de tir qui vise à gêner les capacités défensives de l''adversaire plutôt que le blesser.',
     2, null
),
(
    'jet_infuse','ra-crossbow','technique','Jet infusé',
    'Attaque de jet infusé de magie.',
     3, null
),
(
    'epuisement_arcanique','ra-fairy-wand','spell','Épuisement arcanique',
    'Sort pur qui vise à épuiser l''adversaire plutôt que le blesser.',
     1, null
),
(
    'arcane_precise','ra-fairy-wand','spell','Arcane précise',
    'Sort plus précis au détriment des dégâts.',
     1, null
),
(
    'arcane_violente','ra-fairy-wand','spell','Arcane violente',
    'Sort plus puissant au détriment de la précision.',
     1, null
),
(
    'aveuglement','ra-fairy-wand','spell','Aveuglement',
    'Malédiction qui aveugle l''adversaire.',
     1, null
),
(
    'coup_precis','ra-fairy-wand','spell','Coup précis',
    'Bénédiction qui augmente la précision de la cible.',
     1, null
),
(
    'peau_de_granit','ra-fairy-wand','spell','Peau de granit',
    'Bénédiction qui augmente la défense de la cible.',
     1, null
),
(
    'maladresse','ra-fairy-wand','spell','Maladresse',
    'Malédiction qui diminue la précision de l''adversaire.',
     1, null
),
(
    'vulnerabilite','ra-fairy-wand','spell','Vulnérabilité',
    'Malédiction qui diminue la défense de l''adversaire.',
     1, null
),
(
    'restauration_mineure','ra-fairy-wand','spell','Restauration mineure',
    'Bénédiction qui rétablit de la vigueur à la cible.',
     1, null
),
(
    'enchevetrement','ra-fairy-wand','spell','Enchevêtrement',
    'Des racines attaquent les jambes de l''adversaire pour le ralentir.',
     2, null
),
(
    'exploration','ra-aware','technique','Exploration',
    'Explorer les environs.',
     1, null
),
(
    'discretion','ra-player-dodge','technique','Discrétion',
    'Disparaît temporairement de la carte du monde.',
     3, null
),
(
    'camouflage-olympien','ra-player-dodge','technique','Camouflage (Olympien)',
    'Se fait passer pour un Olympien sur la carte du monde.',
     4, null
),
(
    'camouflage-nain','ra-player-dodge','technique','Camouflage (Nain)',
    'Se fait passer pour un Nain sur la carte du monde.',
     4, null
),
(
    'camouflage-elfe','ra-player-dodge','technique','Camouflage (Elfe)',
    'Se fait passer pour un Elfe sur la carte du monde.',
     4, null
),
(
    'camouflage-geant','ra-player-dodge','technique','Camouflage (Géant)',
    'Se fait passer pour un Géant sur la carte du monde.',
     4, null
),
(
    'camouflage-hs','ra-player-dodge','technique','Camouflage (HS)',
    'Se fait passer pour un Homme Sauvage sur la carte du monde.',
     4, null
);

INSERT INTO action_outcomes (id,apply_to_self, name, on_success, action_id)
VALUES 
(
    55,0,'mtechnique_epuisement',1,46
),
(
    56,0,'mtechnique_att_precise',1,47
),
(
    57,0,'mtechnique_att_violente',1,48
),
(
    58,0,'mtechnique_croc_en_jambe',1,49
),
(
    59,0,'mtechnique_manchette',1,50
),
(
    60,0,'mtechnique_arme_infusee',1,52
),
(
    61,0,'dtechnique_epuisant',1,53
),
(
    62,0,'dtechnique_precis',1,54
),
(
    63,0,'dtechnique_violent',1,55
),
(
    64,0,'dtechnique_tir_cheville',1,56
),
(
    65,0,'dtechnique_handicapant',1,57
),
(
    66,0,'dtechnique_jet_infuse',1,58
),
(
    67,0,'mal_epuisement_arcanique',1,59
),
(
    68,0,'spell_arcane_precise',1,60
),
(
    69,0,'spell_arcane_violente',1,61
),
(
    70,0,'spell_aveuglement',1,62
),
(
    71,0,'bene_coup_precis',1,63
),
(
    72,0,'bene_peau_granit',1,64
),
(
    73,0,'mal_maladresse',1,65
),
(
    74,0,'mal_vulnerabilite',1,66
),
(
    75,0,'bene_restauration_min',1,67
),
(
    76,0,'spell_enchevetrement',1,68
),
(
    77,1,'buff_exploration',1,69
),
(
    78,1,'buff_discretion',1,70
),
(
    79,1,'buff_camouflage_ol',1,71
),
(
    80,1,'buff_camouflage_na',1,72
),
(
    81,1,'buff_camouflage_el',1,73
),
(
    82,1,'buff_camouflage_ge',1,74
),
(
    83,1,'buff_camouflage_hs',1,75
);

INSERT INTO action_conditions (conditionType, parameters, action_id, execution_order, blocking)
VALUES 
(
    'RequiresDistance','{"max":1}',46,0,1
),
(
    'RequiresWeaponType','{"type": ["melee"]}',46,1,1
),
(
    'RequiresTraitValue','{"a":1}',46,5,1
),
(
    'MeleePureCompute','{"actorRollType":"cc", "targetRollType": "cc/agi"}',46,7,0
),
(
    'RequiresDistance','{"max":1}',47,0,1
),
(
    'RequiresWeaponType','{"type": ["melee"]}',47,1,1
),
(
    'RequiresTraitValue','{"a":1, "pm":2}',47,5,1
),
(
    'MeleeCompute','{"actorRollType":"cc", "targetRollType": "cc/agi", "actorRollBonus" : 4}',47,7,0
),
(
    'RequiresDistance','{"max":1}',48,0,1
),
(
    'RequiresWeaponType','{"type": ["melee"]}',48,1,1
),
(
    'RequiresTraitValue','{"a":1, "pm":2}',48,5,1
),
(
    'MeleeCompute','{"actorRollType":"cc", "targetRollType": "cc/agi", "actorRollBonus" : -6}',48,7,0
),
(
    'RequiresDistance','{"max":1}',49,0,1
),
(
    'RequiresWeaponType','{"type": ["melee"]}',49,1,1
),
(
    'RequiresTraitValue','{"a":1, "pm":6}',49,5,1
),
(
    'MeleeCompute','{"actorRollType":"cc", "targetRollType": "cc/agi"}',49,7,0
),
(
    'RequiresDistance','{"max":1}',50,0,1
),
(
    'RequiresWeaponType','{"type": ["melee"]}',50,1,1
),
(
    'RequiresTraitValue','{"a":1, "pm":2}',50,5,1
),
(
    'MeleePureCompute','{"actorRollType":"cc", "targetRollType": "cc/agi"}',50,7,0
),
(
    'RequiresDistance','{"max":1}',52,0,1
),
(
    'RequiresWeaponType','{"type": ["melee"]}',52,1,1
),
(
    'RequiresTraitValue','{"a":1, "pm":8}',52,5,1
),
(
    'MeleePureCompute','{"actorRollType":"cc", "targetRollType": "cc/agi"}',52,7,0
),
(
    'RequiresDistance','{"min":2}',53,0,1
),
(
    'RequiresWeaponType','{"type": ["tir"]}',53,1,1
),
(
    'RequiresTraitValue','{"a":1}',53,5,1
),
(
    'DistancePureCompute','{"actorRollType":"ct", "targetRollType": "cc/agi"}',53,7,0
),
(
    'RequiresDistance','{"min":2}',54,0,1
),
(
    'RequiresWeaponType','{"type": ["tir","jet"]}',54,1,1
),
(
    'RequiresTraitValue','{"a":1, "pm":2}',54,5,1
),
(
    'DistanceCompute','{"actorRollType":"ct", "targetRollType": "cc/agi", "actorRollBonus" : 4}',54,7,0
),
(
    'RequiresDistance','{"min":2}',55,0,1
),
(
    'RequiresWeaponType','{"type": ["tir","jet"]}',55,1,1
),
(
    'RequiresTraitValue','{"a":1, "pm":2}',55,5,1
),
(
    'DistanceCompute','{"actorRollType":"ct", "targetRollType": "cc/agi", "actorRollBonus" : -6}',55,7,0
),
(
    'RequiresDistance','{"min":2}',56,0,1
),
(
    'RequiresWeaponType','{"type": ["tir"]}',56,1,1
),
(
    'RequiresTraitValue','{"a":1, "pm":6}',56,5,1
),
(
    'DistanceCompute','{"actorRollType":"ct", "targetRollType": "cc/agi"}',56,7,0
),
(
    'RequiresDistance','{"min":2}',57,0,1
),
(
    'RequiresWeaponType','{"type": ["tir"]}',57,1,1
),
(
    'RequiresTraitValue','{"a":1, "pm":4}',57,5,1
),
(
    'DistancePureCompute','{"actorRollType":"ct", "targetRollType": "cc/agi"}',57,7,0
),
(
    'RequiresDistance','{"min":2}',58,0,1
),
(
    'RequiresWeaponType','{"type": ["jet"]}',58,1,1
),
(
    'RequiresTraitValue','{"a":1, "pm":10}',58,5,1
),
(
    'DistanceCompute','{"actorRollType":"ct", "targetRollType": "cc/agi"}',58,7,0
),
(
    'RequiresDistance','{"min":2}',59,0,1
),
(
    'RequiresTraitValue','{"a":1, "pm":4}',59,5,1
),
(
    'SpellPureCompute','{"actorRollType":"fm", "targetRollType": "fm"}',59,7,0
),
(
    'RequiresDistance','{"min":2}',60,0,1
),
(
    'RequiresTraitValue','{"a":1, "pm":6}',60,5,1
),
(
    'SpellCompute','{"actorRollType":"fm", "targetRollType": "fm", "actorRollBonus" : 4}',60,7,0
),
(
    'RequiresDistance','{"min":2}',61,0,1
),
(
    'RequiresTraitValue','{"a":1, "pm":6}',61,50,1
),
(
    'SpellCompute','{"actorRollType":"fm", "targetRollType": "fm", "actorRollBonus" : -6}',61,7,0
),
(
    'RequiresDistance','{"min":2}',62,0,1
),
(
    'RequiresTraitValue','{"a":1, "pm":4}',62,5,1
),
(
    'SpellCompute','{"actorRollType":"fm", "targetRollType": "fm"}',62,7,0
),
(
    'RequiresDistance','{"max":1}',63,0,1
),
(
    'RequiresTraitValue','{"a":1, "pm":4}',63,5,1
),
(
    'RequiresDistance','{"max":1}',64,0,1
),
(
    'RequiresTraitValue','{"a":1, "pm":4}',64,5,1
),
(
    'RequiresDistance','{"min":2}',65,0,1
),
(
    'RequiresTraitValue','{"a":1, "pm":4}',65,5,1
),
(
    'RequiresDistance','{"min":2}',66,0,1
),
(
    'RequiresTraitValue','{"a":1, "pm":6}',66,5,1
),
(
    'RequiresDistance','{"max":1}',67,0,1
),
(
    'RequiresTraitValue','{"a":1, "pm":6}',67,5,1
),
(
    'RequiresDistance','{"min":2}',68,0,1
),
(
    'RequiresTraitValue','{"a":1, "pm":6}',68,5,1
),
(
    'RequiresDistance','{"max":0}',69,0,1
),
(
    'RequiresTraitValue','{ "remaining": "a" } 	',69,5,1
),
(
    'RequiresDistance','{"max":0}',70,0,1
),
(
    'RequiresTraitValue','{ "a": 1, "furtif": [2,0.5] }',70,5,1
),
(
    'RequiresDistance','{"max":0}',71,0,1
),
(
    'RequiresTraitValue','{ "a": 1, "furtif": [4,0.5] }',71,5,1
),
(
    'RequiresDistance','{"max":0}',72,0,1
),
(
    'RequiresTraitValue','{ "a": 1, "furtif": [4,0.5] }',72,5,1
),
(
    'RequiresDistance','{"max":0}',73,0,1
),
(
    'RequiresTraitValue','{ "a": 1, "furtif": [4,0.5] }',73,5,1
),
(
    'RequiresDistance','{"max":0}',74,0,1
),
(
    'RequiresTraitValue','{ "a": 1, "furtif": [4,0.5] }',74,5,1
),
(
    'RequiresDistance','{"max":0}',75,0,1
),
(
    'RequiresTraitValue','{ "a": 1, "furtif": [4,0.5] }',75,5,1
);

INSERT INTO outcome_instructions (type, parameters, orderIndex, outcome_id)
VALUES 
(
    'malus','{ "rollDivisor": 2}',10,55
),
(
    'lifeloss','{ "actorDamagesTrait": "f", "targetDamagesTrait": "e", "bonusDamagesTrait": -3}',10,56
),
(
    'lifeloss','{ "actorDamagesTrait": "f", "targetDamagesTrait": "e", "bonusDamagesTrait": 2}',10,57
),
(
    'lifeloss','{ "actorDamagesTrait": "f", "targetDamagesTrait": "e"}',9,58
),
(
    'applystatus','{ "ralentissement": true, "stackable": false, "value": [2,3,4], "player": "target", "duration": 1}',10,58
),
(
    'applystatus','{ "maladresse": true, "stackable": false, "value": ["rollDivisor",2], "player": "target", "duration": 86400}',10,59
),
(
    'lifeloss','{ "actorDamagesTrait": "f", "targetDamagesTrait": "e", "bonusDamagesTrait": ["m",3]}',10,60
),
(
    'malus','{ "rollDivisor": 3}',10,61
),
(
    'lifeloss','{ "actorDamagesTrait": "f", "targetDamagesTrait": "e", "bonusDamagesTrait": -3, "distance":true}',10,62
),
(
    'lifeloss','{ "actorDamagesTrait": "f", "targetDamagesTrait": "e", "bonusDamagesTrait": 2, "distance":true}',10,63
),
(
    'lifeloss','{ "actorDamagesTrait": "f", "targetDamagesTrait": "e", "distance":true}',9,64
),
(
    'applystatus','{ "ralentissement": true, "stackable": false, "value": [1,2], "player": "target", "duration": 1}',10,64
),
(
    'applystatus','{ "vulnerabilite": true, "stackable": false, "value": ["rollDivisor",3], "player": "target", "duration": 86400}',10,65
),
(
    'lifeloss','{ "actorDamagesTrait": "f", "targetDamagesTrait": "e", "bonusDamagesTrait": ["m",3]}',10,66
),
(
    'malus','{ "rollDivisor": 3}',10,67
),
(
    'lifeloss','{ "actorDamagesTrait": "m", "targetDamagesTrait": "m", "bonusDamagesTrait": 0}',10,68
),
(
    'lifeloss','{ "actorDamagesTrait": "m", "targetDamagesTrait": "m", "bonusDamagesTrait": 5}',10,69
),
(
    'applystatus','{ "aveuglement": true, "stackable": false, "value": 1, "player": "target", "duration": 86400}',10,70
),
(
    'applystatus','{ "dexterite": true, "stackable": false, "value": 2, "player": "target", "duration": 86400}',10,71
),
(
    'applystatus','{ "protection": true, "stackable": false, "value": 2, "player": "target", "duration": 86400}',10,72
),
(
    'applystatus','{ "maladresse": true, "stackable": false, "value": 2, "player": "target", "duration": 86400}',10,73
),
(
    'applystatus','{ "vulnerabilite": true, "stackable": false, "value": 2, "player": "target", "duration": 86400}',10,74
),
(
    'removemalus','{ "fixedMalus": 5}',10,75
),
(
    'lifeloss','{ "actorDamagesTrait": "m", "targetDamagesTrait": "m", "bonusDamagesTrait": 1}',9,76
),
(
    'applystatus','{ "ralentissement": true, "stackable": false, "value": [1,2], "player": "target", "duration": 1}',10,76
),
(
    'applystatus','{ "acuite_visuelle": true, "stackable": false, "value": ["remaining","a"], "player": "target", "duration": 1}',10,77
),
(
    'player','{"carac": "visible", "value" : "invisible", "player": "actor"}',10,78
),
(
    'player','{"carac": "visible", "value" : "olympien", "player": "actor"}',10,79
),
(
    'player','{"carac": "visible", "value" : "nain", "player": "actor"}',10,80
),
(
    'player','{"carac": "visible", "value" : "elfe", "player": "actor"}',10,81
),
(
    'player','{"carac": "visible", "value" : "geant", "player": "actor"}',10,82
),
(
    'player','{"carac": "visible", "value" : "hs", "player": "actor"}',10,83
);