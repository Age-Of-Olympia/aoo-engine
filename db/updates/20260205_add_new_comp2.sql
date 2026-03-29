ALTER TABLE actions
ADD COLUMN category VARCHAR(50) DEFAULT NULL;
ADD COLUMN cost VARCHAR(255);
ADD COLUMN prerequisites VARCHAR(50) DEFAULT NULL;

ALTER TABLE action_passives
ADD COLUMN category VARCHAR(50) DEFAULT NULL;
ADD COLUMN prerequisites VARCHAR(50) DEFAULT NULL;

ALTER TABLE players_actions
DROP COLUMN charges;

UPDATE actions 
SET cost = CASE 
    WHEN name = 'epuisement' THEN '<span style="color: #8e44ad;">1 A</span>'
    WHEN name = 'attaque_precise' THEN '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">2 PM</span>'
    WHEN name = 'attaque_violente' THEN '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">2 PM</span>'
    WHEN name = 'croc-en-jambe' THEN '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">6 PM</span>'
    WHEN name = 'manchette' THEN '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">2 PM</span>'
    WHEN name = 'arme_infusee' THEN '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">8 PM</span>'
    WHEN name = 'tir_epuisant' THEN '<span style="color: #8e44ad;">1 A</span>'
    WHEN name = 'tir_precis' THEN '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">2 PM</span>'
    WHEN name = 'tir_violent' THEN '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">2 PM</span>'
    WHEN name = 'tir_a_la_cheville' THEN '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">6 PM</span>'
    WHEN name = 'tir_handicapant' THEN '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">4 PM</span>'
    WHEN name = 'jet_infuse' THEN '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">10 PM</span>'
    WHEN name = 'epuisement_arcaniques' THEN '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">4 PM</span>'
    WHEN name = 'arcane_precise' THEN '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">6 PM</span>'
    WHEN name = 'arcane_violente' THEN '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">6 PM</span>'
    WHEN name = 'aveuglement' THEN '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">4 PM</span>'
    WHEN name = 'coup_precis' THEN '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">4 PM</span>'
    WHEN name = 'peau_de_granit' THEN '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">4 PM</span>'
    WHEN name = 'maladresse' THEN '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">4 PM</span>'
    WHEN name = 'vulnerabilite' THEN '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">6 PM</span>'
    WHEN name = 'restauration_mineure' THEN '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">6 PM</span>'
    WHEN name = 'enchevetrement' THEN '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">6 PM</span>'
    WHEN name = 'exploration' THEN '<span style="color: #8e44ad;">Toutes les A restantes</span>'
    WHEN name = 'discretion' THEN '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">2x(<i class="ra ra-player-teleport"></i>+1) PM</span>, <span style="color: #27ae60;">1/2x(<i class="ra ra-player-teleport"></i>+1) Mvt</span>'
    WHEN name = 'camouflage-olympien' THEN '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">4x(<i class="ra ra-player-teleport"></i>+1) PM</span>, <span style="color: #27ae60;">1/2x(<i class="ra ra-player-teleport"></i>+1) Mvt</span>'
    WHEN name = 'camouflage-nain' THEN '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">4x(<i class="ra ra-player-teleport"></i>+1) PM</span>, <span style="color: #27ae60;">1/2x(<i class="ra ra-player-teleport"></i>+1) Mvt</span>'
    WHEN name = 'camouflage-elfe' THEN '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">4x(<i class="ra ra-player-teleport"></i>+1) PM</span>, <span style="color: #27ae60;">1/2x(<i class="ra ra-player-teleport"></i>+1) Mvt</span>'
    WHEN name = 'camouflage-geant' THEN '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">4x(<i class="ra ra-player-teleport"></i>+1) PM</span>, <span style="color: #27ae60;">1/2x(<i class="ra ra-player-teleport"></i>+1) Mvt</span>'
    WHEN name = 'camouflage-hs' THEN '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">4x(<i class="ra ra-player-teleport"></i>+1) PM</span>, <span style="color: #27ae60;">1/2x(<i class="ra ra-player-teleport"></i>+1) Mvt</span>'
    ELSE cost
END;

UPDATE actions 
SET category = CASE 
    WHEN name = 'epuisement' THEN 'melee-curse'
    WHEN name = 'attaque_precise' THEN 'melee-off'
    WHEN name = 'attaque_violente' THEN 'melee-off'
    WHEN name = 'croc-en-jambe' THEN 'melee-off'
    WHEN name = 'manchette' THEN 'melee-curse'
    WHEN name = 'arme_infusee' THEN 'melee-off'
    WHEN name = 'tir_epuisant' THEN 'distance-curse'
    WHEN name = 'tir_precis' THEN 'distance-off'
    WHEN name = 'tir_violent' THEN 'distance-off'
    WHEN name = 'tir_a_la_cheville' THEN 'distance-off'
    WHEN name = 'tir_handicapant' THEN 'distance-curse'
    WHEN name = 'jet_infuse' THEN 'distance-off'
    WHEN name = 'epuisement_arcaniques' THEN 'spell-curse'
    WHEN name = 'arcane_precise' THEN 'spell-off'
    WHEN name = 'arcane_violente' THEN 'spell-off'
    WHEN name = 'aveuglement' THEN 'spell-curse'
    WHEN name = 'coup_precis' THEN 'spell-support'
    WHEN name = 'peau_de_granit' THEN 'spell-support'
    WHEN name = 'maladresse' THEN 'spell-curse'
    WHEN name = 'vulnerabilite' THEN 'spell-curse'
    WHEN name = 'restauration_mineure' THEN 'spell-support'
    WHEN name = 'enchevetrement' THEN 'spell-off'
    WHEN name = 'exploration' THEN 'stealth-buff'
    WHEN name = 'discretion' THEN 'stealth-buff'
    WHEN name = 'camouflage-olympien' THEN 'stealth-buff'
    WHEN name = 'camouflage-nain' THEN 'stealth-buff'
    WHEN name = 'camouflage-elfe' THEN 'stealth-buff'
    WHEN name = 'camouflage-geant' THEN 'stealth-buff'
    WHEN name = 'camouflage-hs' THEN 'stealth-buff'
    ELSE cost
END;

UPDATE actions 
SET text = CASE 
    WHEN name = 'epuisement' THEN 'Jet pur. Essoufflement(X/2) où X est la différence des jets de dé'
    WHEN name = 'attaque_precise' THEN '+4 pour toucher. -3 Dmg'
    WHEN name = 'attaque_violente' THEN '-6 pour toucher, +2 Dmg'
    WHEN name = 'croc-en-jambe' THEN 'Ralentissement(x2D2)'
    WHEN name = 'manchette' THEN 'Jet pur. Maladresse(X/2)  où X est la différence des jets de dé'
    WHEN name = 'arme_infusee' THEN '+M/3 Dmg'
    WHEN name = 'tir_epuisant' THEN 'Jet pur. Essoufflement(X/3) où X est la différence des jets de dé'
    WHEN name = 'tir_precis' THEN '+4 pour toucher. -3 Dmg'
    WHEN name = 'tir_violent' THEN '-6 pour toucher, +2 Dmg'
    WHEN name = 'tir_a_la_cheville' THEN 'Nécessite une arme à munitions. Ralentissement(x1D2)'
    WHEN name = 'tir_handicapant' THEN 'Jet pur. Vulnérabilité(X/3)  où X est la différence des jets de dé'
    WHEN name = 'jet_infuse' THEN 'Nécessite une arme de jet. +M/3 Dmg'
    WHEN name = 'epuisement_arcaniques' THEN 'Jet pur. Essoufflement(X/3) où X est la différence des jets de dé'
    WHEN name = 'arcane_precise' THEN '+4 pour toucher. -3 Dmg'
    WHEN name = 'arcane_violente' THEN '-6 pour toucher, +2 Dmg'
    WHEN name = 'aveuglement' THEN 'Aveuglement(x1)'
    WHEN name = 'coup_precis' THEN 'Dextérité(x2)'
    WHEN name = 'peau_de_granit' THEN 'Protection(x2)'
    WHEN name = 'maladresse' THEN 'Maladresse(x2)'
    WHEN name = 'vulnerabilite' THEN 'Vulnérabilité(x2)'
    WHEN name = 'restauration_mineure' THEN 'Restauration(5)'
    WHEN name = 'enchevetrement' THEN 'Ralentissement (x1D2)'
    WHEN name = 'exploration' THEN 'Acuité visuelle(X) où X est le nombre d''A utilisées'
    WHEN name = 'discretion' THEN 'Imposture(+1). Le personnage n''apparaît plus sur la carte générale jusqu''à son prochain tour'
    WHEN name = 'camouflage-olympien' THEN 'Apparaît en Olympien sur la carte générale jusqu''à son prochain tour'
    WHEN name = 'camouflage-nain' THEN 'Apparaît en Nain sur la carte générale jusqu''à son prochain tour'
    WHEN name = 'camouflage-elfe' THEN 'Apparaît en Elfe sur la carte générale jusqu''à son prochain tour'
    WHEN name = 'camouflage-geant' THEN 'Apparaît en Géant sur la carte générale jusqu''à son prochain tour'
    WHEN name = 'camouflage-hs' THEN 'Apparaît en Homme Sauvage sur la carte générale jusqu''à son prochain tour'
    ELSE text
END;

UPDATE outcome_instructions 
SET parameters = REPLACE(parameters, '"furtif": true', '"imposture": true')
WHERE type = 'applystatus' 
AND parameters LIKE '%"furtif": true%';

UPDATE action_conditions 
SET parameters = REPLACE(parameters, '"furtif"', '"imposture"')
WHERE conditionType = 'RequiresTraitValue' 
AND parameters LIKE '%"furtif"%';

UPDATE outcome_instructions
SET parameters = REPLACE(parameters, '"furtif"', '"imposture"')
WHERE type = 'applystatus' 
AND parameters LIKE '%"furtif"%';

UPDATE outcome_instructions
SET parameters = REPLACE(parameters, '{ "repos": "effets" }', '{}')
WHERE type = 'rest';

INSERT INTO outcome_instructions (type, parameters, orderIndex, outcome_id)
VALUES 
(
    'applystatus','{ "discretion": true, "stackable": false, "value": 1, "player": "target", "duration": 1}',8,78
)

DELETE FROM actions 
WHERE name = 'griffes';

DELETE FROM action_conditions 
WHERE parameters = '{ "a": 1 }'
AND action_id=8;

DELETE FROM action_conditions 
WHERE parameters = '{ "repos": "effets" }';

DELETE FROM outcome_instructions
WHERE parameters = '{"carac":"malus", "player": "actor"}';

DELETE FROM outcome_instructions
WHERE parameters = '{ "finished": true, "player": "actor" }';

INSERT INTO actions (name, icon, type, display_name, text, level, race, category, cost, prerequisites)
VALUES 
(
    'coup_ajuste','ra-bowie-knife','technique','Coup ajusté',
    'Avantage',1, null,'melee-off',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">4 PM</span>',null
),
(
    'coup_epaule','ra-shovel','technique','Coup d''épaule',
    'Une attaque à -4 pour toucher et -3 Dmg',1, null,'melee-off',
    '<span style="color: #27ae60;">5 Mvt</span>',null
),
(
    'saut_attaque','ra-overhead','technique','Saut d''attaque',
    'Saute sur la cible et l''attaque au contact. Subit les même malus de distance qu''un tir.',3, null,'melee-off',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">10 PM</span>,<span style="color: #27ae60;">1 Mvt</span>',null
),
(
    'recuperation','ra-medical-pack','heal','Récupération',
    'Soin(R/2)',3, null,'spell-support',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">4 PM</span>',null
),
(
    'recuperation_superieure','ra-medical-pack','heal','Récupération supérieure',
    'Soin(R)',4, null,'spell-support',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">10 PM</span>',null
),
(
    'restauration','ra-fairy-wand','buff','Restauration',
    'Restauration(R/2)',2, null,'spell-support',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">6 PM</span>',null
),
(
    'restauration_majeure','ra-fairy-wand','buff','Restauration majeure',
    'Restauration(R)',3, null,'spell-support',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">12 PM</span>',null
),
(
    'regeneration','ra-medical-pack','heal','Régénération',
    'Soin(X/2) où X est la R de la cible',2, null,'spell-support',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">4 PM</span>',null
),
(
    'regeneration_acceleree','ra-medical-pack','heal','Régénération accélérée',
    'Soin(X2) où X est la R de la cible',3, null,'spell-support',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">10 PM</span>',null
),
(
    'pas-leger','ra-shoe-prints','buff','Pas léger',
    'Les déplacements ne laissent pas de traces de pas jusqu''au prochain tour',1, null,'stealth-buff',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">2x(<i class="ra ra-player-teleport"></i>+1) PM</span>, <span style="color: #27ae60;">1x(<i class="ra ra-player-teleport"></i>+1) Mvt</span>',
    null
),
(
    'puissance_nature','ra-clover','buff','Puissance de la nature',
    'Dextérité(x2), Protection(x2)',2, null,'spell-support',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">8 PM</span>',null
),
(
    'aide','ra-fairy-wand','buff','Aide',
    'Dextérité(x4)',3, null,'spell-support',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">8 PM</span>',null
),
(
    'reflexes_accrus','ra-fairy-wand','buff','Réflexes accrus',
    'Protection(x4)',3, null,'spell-support',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">8 PM</span>',null
),
(
    'benediction','ra-fairy-wand','buff','Bénédiction',
    'Dextérité(x4), Protection(x4)',4, null,'spell-support',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">15 PM</span>',null
),
(
    'sauvegarde','ra-fairy-wand','buff','Sauvegarde',
    'Protection(x8)',5, null,'spell-support',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">20 PM</span>',null
),
(
    'virtuose','ra-fairy-wand','buff','Virtuose',
    'Dextérité(x8)',5, null,'spell-support',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">20 PM</span>',null
),
(
    'armure','ra-vest','buff','Armure',
    'Armure(x1)',2, null,'spell-support',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">8 PM</span>',null
),
(
    'agressivite','ra-dinosaur','buff','Agressivité',
    'Agressivité(x1)',2, null,'spell-support',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">6 PM</span>',null
),
(
    'cuirasse','ra-vest','buff','Cuirasse',
    'Armure(x2)',4, null,'spell-support',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">15 PM</span>',null
),
(
    'ferocite','ra-dinosaur','buff','Férocité',
    'Agressivité(x2)',4, null,'spell-support',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">12 PM</span>',null
),
(
    'fragilite','ra-broken-bottle','spell','Fragilité',
    'Fragilité(x1)',2, null,'spell-curse',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">10 PM</span>',null
),
(
    'friabilite','ra-broken-bottle','spell','Friabilité',
    'Fragilité(x2)',4, null,'spell-curse',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">20 PM</span>',null
),
(
    'faiblesse','ra-player-pain','spell','Faiblesse',
    'Faiblesse(x1)',2, null,'spell-curse',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">6 PM</span>',null
),
(
    'anemie','ra-player-pain','spell','Anémie',
    'Faiblesse(x2)',4, null,'spell-curse',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">12 PM</span>',null
),
(
    'colere_nature','ra-player-thunder-struck','spell','Colère de la nature',
    'Maladresse(x2), Vulnérabilité(x2)',4, null,'spell-curse',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">8 PM</span>',null
),
(
    'fatigue','ra-broken-shield','spell','Fatigue',
    'Vulnérabilité(x4)',4, null,'spell-curse',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">12 PM</span>',null
),
(
    'malchance','ra-cut-palm','spell','Malchance',
    'Maladresse(x4)',4, null,'spell-curse',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">8 PM</span>',null
),
(
    'puissance_lutin','ra-player-thunder-struck','spell','Puissance du Lutin capricieux',
    'Maladresse(x4), Vulnérabilité(x4)',4, null,'spell-curse',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">20 PM</span>',null
),
(
    'extenuation','ra-broken-shield','spell','Exténuation',
    'Vulnérabilité(x8)',4, null,'spell-curse',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">25 PM</span>',null
),
(
    'guigne','ra-broken-shield','spell','Guigne',
    'Maladresse(x8)',4, null,'spell-curse',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">20 PM</span>',null
),
(
    'attaque_drainante','ra-knife-fork','technique','Attaque drainante',
    'Une attaque avec Drain',3, null,'melee-off',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">4 PM</span>',null
),
(
    'attaque_siphonnante','ra-knife-fork','technique','Attaque siphonnante',
    'Une attaque avec Siphon',3, null,'melee-off',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #c0392b;">2 PV</span>',null
),
(
    'frappe_tempe','ra-decapitation','technique','Frappe à la tempe',
    'Dommages Mentaux(X/2) où X est le nombre de dégâts infligés',3, null,'melee-off',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">4 PM</span>',null
),
(
    'arme_impro','ra-wrench','technique','Arme improvisée',
    'Permet d''effectuer une attaque à distance à -4 pour toucher et -2 Dmg sans le matériel adéquat',1, null,'melee-off',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">2 PM</span>',null
);

INSERT INTO action_outcomes (id,apply_to_self, name, on_success, action_id)
VALUES 
(
    87,0,'mtechnique_coupajuste',1,77
),
(
    88,0,'mtechnique_coupepaule',1,78
),
(
    89,0,'mtechnique_sautattaque',1,79
),
(
    90,0,'bene_recuperation',1,80
),
(
    91,0,'bene_recuperationsup',1,81
),
(
    92,0,'bene_restauration',1,82
),
(
    93,0,'bene_restaurationmaj',1,83
),
(
    94,0,'bene_regen',1,84
),
(
    95,0,'bene_regenacc',1,85
),
(
    96,0,'buff_pasleger',1,86
),
(
    97,0,'buff_puissnature',1,87
),
(
    98,0,'bene_aide',1,88
),
(
    99,0,'bene_reflex_acc',1,89
),
(
    100,0'bene_bene',1,90
),
(
    101,0,'bene_sauvegarde',1,91
),
(
    102,0,'bene_virtuose',1,92
),
(
    103,0,'bene_armure',1,93
),
(
    104,0,'bene_agressivite',1,94
),
(
    105,0,'bene_cuirasse',1,95
),
(
    106,0,'bene_ferocite',1,96
),
(
    107,0,'spell_fragilite',1,97
),
(
    108,0,'spell_friabilite',1,98
),
(
    109,0,'spell_faiblesse',1,99
),
(
    110,0,'spell_anemie',1,100
),
(
    111,0,'spell_colerenature',1,101
),
(
    112,0,'spell_fatigue',1,102
),
(
    113,0,'spell_malchance',1,103
),
(
    114,0,'spell_puisslutin',1,104
),
(
    115,0,'spell_extenuation',1,105
),
(
    116,0,'spell_guigne',1,106
),
(
    117,0,'mtechnique_att_drain',1,107
),
(
    118,0,'mtechnique_att_siphon',1,108
),
(
    119,0,'mtechnique_frappe_tempe',1,109
),
(
    120,0,'dtechnique_arme_impro',1,110
);

INSERT INTO action_passives (name, traits, type, carac, value, conditions, level, race)
VALUES 
(
    'griffes','["f"]','att','fixed',3.00,'{"weapon":["poing","ceste"]}', 2,"hs",
    "melee","Griffes","Bonus de +3 Dmg aux poings"
),
(
    'fulgurance','["cc","esquive"]','att','mvt',0.20,null, 2,"elfe",
    "melee","Fulgurance","+1 pour toucher au CàC et +1 Esquive tous les 5 Mvt max"
),
(
    'encaisser','["e","m"]','def','fixed',25.00,null, 2,"",
    "survival","Encaisser","Bénéficie des effets d'Encaisse à 25 PV ou moins"
);


INSERT INTO action_conditions (conditionType, parameters, action_id, execution_order, blocking)
VALUES 
/* coup_precis */
(
    'BuffCompute','{"actorRollType":"fm", "targetRollType": "fm"}',63,7,0
),
/* peau_de_granit */
(
    'BuffCompute','{"actorRollType":"fm", "targetRollType": "fm"}',64,7,0
),
/* restauration_mineure */
(
    'BuffCompute','{"actorRollType":"fm", "targetRollType": "fm"}',64,7,0
),
/* repos */
(
    'RequiresTraitValue','{ "remainingNullable": "a" }',8,2,1
),
(
    'RequiresTraitValue','{ "remainingNullable": "mvt" }',8,2,1
),
/* coup_ajuste */
(
    'RequiresDistance','{"max":1}',77,0,1
),
(
    'RequiresWeaponType','{"type": ["melee"]}',77,1,1
),
(
    'RequiresTraitValue','{"a":1, "pm":2}',77,5,1
),
(
    'MeleeCompute','{"actorRollType":"cc", "targetRollType": "cc/agi", "actorAdvantage":true}',77,7,0
),
/* coup_epaule */
(
    'RequiresDistance','{"max":1}',78,0,1
),
(
    'RequiresWeaponType','{"type": ["melee"]}',78,1,1
),
(
    'RequiresTraitValue','{"mvt":5}',78,5,1
),
(
    'MeleeCompute','{"actorRollType":"cc", "targetRollType": "cc/agi", "actorRollBonus" : -4}',78,7,0
),
/* saut_attaque */
(
    'RequiresWeaponType','{"type": ["melee"]}',79,1,1
),
(
    'RequiresTraitValue','{"a":1, "pm":10, "mvt":1}',79,5,1
),
(
    'MeleeCompute','{"actorRollType":"cc", "targetRollType": "cc/agi"}',79,7,0
),
/* recuperation */
(
    'RequiresDistance','{"max":1}',80,0,1
),
(
    'RequiresTraitValue','{ "a":1, "pm":4 }',80,3,1
),
(
    'BuffCompute','{"actorRollType":"fm", "targetRollType": "fm"}',80,7,0
),
/* recuperation_superieure */
(
    'RequiresDistance','{"max":1}',81,0,1
),
(
    'RequiresTraitValue','{ "a":1, "pm":10 }',81,3,1
),
(
    'BuffCompute','{"actorRollType":"fm", "targetRollType": "fm"}',81,7,0
),
/* restauration */
(
    'RequiresDistance','{"max":1}',82,0,1
),
(
    'RequiresTraitValue','{ "a":1, "pm":6 }',82,3,1
),
(
    'BuffCompute','{"actorRollType":"fm", "targetRollType": "fm"}',82,7,0
),
/* restauration_majeure*/
(
    'RequiresDistance','{"max":1}',83,0,1
),
(
    'RequiresTraitValue','{ "a":1, "pm":12 }',83,3,1
),
(
    'BuffCompute','{"actorRollType":"fm", "targetRollType": "fm"}',83,7,0
),
/* regeneration */
(
    'RequiresDistance','{"max":1}',84,0,1
),
(
    'RequiresTraitValue','{ "a":1, "pm":4 }',84,3,1
),
(
    'BuffCompute','{"actorRollType":"fm", "targetRollType": "fm"}',84,7,0
),
/* regeneration_acceleree */
(
    'RequiresDistance','{"max":1}',85,0,1
),
(
    'RequiresTraitValue','{ "a": 1, "pm":10 }',85,3,1
),
(
    'BuffCompute','{"actorRollType":"fm", "targetRollType": "fm"}',85,7,0
),
/* puissance_nature */
(
    'RequiresDistance','{"max":1}',86,0,1
),
(
    'RequiresTraitValue','{ "a": 1, "pm":8 }',86,3,1
),
(
    'BuffCompute','{"actorRollType":"fm", "targetRollType": "fm"}',86,7,0
),
/* aide */
(
    'RequiresDistance','{"max":1}',87,0,1
),
(
    'RequiresTraitValue','{ "a": 1, "pm":8 }',87,3,1
),
(
    'BuffCompute','{"actorRollType":"fm", "targetRollType": "fm"}',87,7,0
),
/* reflexes_accruse */
(
    'RequiresDistance','{"max":1}',88,0,1
),
(
    'RequiresTraitValue','{ "a": 1, "pm":8 }',88,3,1
),
(
    'BuffCompute','{"actorRollType":"fm", "targetRollType": "fm"}',88,7,0
),
/* reflexes_accruse */
(
    'RequiresDistance','{"max":1}',89,0,1
),
(
    'RequiresTraitValue','{ "a": 1, "pm":8 }',89,3,1
),
(
    'BuffCompute','{"actorRollType":"fm", "targetRollType": "fm"}',89,7,0
),
/* benediction */
(
    'RequiresDistance','{"max":1}',90,0,1
),
(
    'RequiresTraitValue','{ "a": 1, "pm":15 }',90,3,1
),
(
    'BuffCompute','{"actorRollType":"fm", "targetRollType": "fm"}',90,7,0
),
/* sauvegarde */
(
    'RequiresDistance','{"max":1}',91,0,1
),
(
    'RequiresTraitValue','{ "a": 1, "pm":20 }',91,3,1
),
(
    'BuffCompute','{"actorRollType":"fm", "targetRollType": "fm"}',91,7,0
),
/* virtuose */
(
    'RequiresDistance','{"max":1}',92,0,1
),
(
    'RequiresTraitValue','{ "a": 1, "pm":20 }',92,3,1
),
(
    'BuffCompute','{"actorRollType":"fm", "targetRollType": "fm"}',92,7,0
),
/* armure */
(
    'RequiresDistance','{"max":1}',93,0,1
),
(
    'RequiresTraitValue','{ "a": 1, "pm":8 }',93,3,1
),
(
    'BuffCompute','{"actorRollType":"fm", "targetRollType": "fm"}',93,7,0
),
/* agressivite */
(
    'RequiresDistance','{"max":1}',94,0,1
),
(
    'RequiresTraitValue','{ "a": 1, "pm":6 }',94,3,1
),
(
    'BuffCompute','{"actorRollType":"fm", "targetRollType": "fm"}',94,7,0
),
/* cuirasse */
(
    'RequiresDistance','{"max":1}',95,0,1
),
(
    'RequiresTraitValue','{ "a": 1, "pm":15 }',95,3,1
),
(
    'BuffCompute','{"actorRollType":"fm", "targetRollType": "fm"}',95,7,0
),
/* ferocite */
(
    'RequiresDistance','{"max":1}',96,0,1
),
(
    'RequiresTraitValue','{ "a": 1, "pm":12 }',96,3,1
),
(
    'BuffCompute','{"actorRollType":"fm", "targetRollType": "fm"}',96,7,0
),
/* faiblesse */
(
    'RequiresDistance','{"min":2}',97,0,1
),
(
    'RequiresTraitValue','{ "a": 1, "pm":6 }',97,3,1
),
(
    'SpellCompute','{"actorRollType":"fm", "targetRollType": "fm"}',97,7,0
),
/* fragilite */
(
    'RequiresDistance','{"min":2}',98,0,1
),
(
    'RequiresTraitValue','{ "a": 1, "pm":10 }',98,3,1
),
(
    'SpellCompute','{"actorRollType":"fm", "targetRollType": "fm"}',98,7,0
),
/* friabilite */
(
    'RequiresDistance','{"min":2}',99,0,1
),
(
    'RequiresTraitValue','{ "a": 1, "pm":20 }',99,3,1
),
(
    'SpellCompute','{"actorRollType":"fm", "targetRollType": "fm"}',99,7,0
),
/* anemie */
(
    'RequiresDistance','{"min":2}',100,0,1
),
(
    'RequiresTraitValue','{ "a": 1, "pm":12 }',100,3,1
),
(
    'SpellCompute','{"actorRollType":"fm", "targetRollType": "fm"}',100,7,0
),
/* colere_nature */
(
    'RequiresDistance','{"min":2}',101,0,1
),
(
    'RequiresTraitValue','{ "a": 1, "pm":8 }',101,3,1
),
(
    'SpellCompute','{"actorRollType":"fm", "targetRollType": "fm"}',101,7,0
),
/* fatigue */
(
    'RequiresDistance','{"min":2}',102,0,1
),
(
    'RequiresTraitValue','{ "a": 1, "pm":12 }',102,3,1
),
(
    'SpellCompute','{"actorRollType":"fm", "targetRollType": "fm"}',102,7,0
),
/* malchance */
(
    'RequiresDistance','{"min":2}',103,0,1
),
(
    'RequiresTraitValue','{ "a": 1, "pm":8 }',103,3,1
),
(
    'SpellCompute','{"actorRollType":"fm", "targetRollType": "fm"}',103,7,0
),
/* puissance_lutin */
(
    'RequiresDistance','{"min":2}',104,0,1
),
(
    'RequiresTraitValue','{ "a": 1, "pm":20 }',104,3,1
),
(
    'SpellCompute','{"actorRollType":"fm", "targetRollType": "fm"}',104,7,0
),
/* extenuation */
(
    'RequiresDistance','{"min":2}',105,0,1
),
(
    'RequiresTraitValue','{ "a": 1, "pm":25 }',105,3,1
),
(
    'SpellCompute','{"actorRollType":"fm", "targetRollType": "fm"}',105,7,0
),
/* guigne */
(
    'RequiresDistance','{"min":2}',106,0,1
),
(
    'RequiresTraitValue','{ "a": 1, "pm":20 }',106,3,1
),
(
    'SpellCompute','{"actorRollType":"fm", "targetRollType": "fm"}',106,7,0
),
/* attaque_drainante */
(
    'RequiresDistance','{"max":1}',107,0,1
),
(
    'RequiresWeaponType','{"type": ["melee"]}',107,1,1
),
(
    'RequiresTraitValue','{"a":1, "pm":4}',107,5,1
),
(
    'MeleeCompute','{"actorRollType":"cc", "targetRollType": "cc/agi", "drain":true}',107,7,0
),
/* attaque_siphonnante */
(
    'RequiresDistance','{"max":1}',108,0,1
),
(
    'RequiresWeaponType','{"type": ["melee"]}',108,1,1
),
(
    'RequiresTraitValue','{"a":1, "pv":2}',108,5,1
),
(
    'MeleeCompute','{"actorRollType":"cc", "targetRollType": "cc/agi", "siphon":true}',108,7,0
),
/* frappe_tempe */
(
    'RequiresDistance','{"max":1}',109,0,1
),
(
    'RequiresWeaponType','{"type": ["melee"]}',109,1,1
),
(
    'RequiresTraitValue','{"a":1, "pm":4}',109,5,1
),
(
    'MeleeCompute','{"actorRollType":"cc", "targetRollType": "cc/agi"}',109,7,0
),
/* arme_impro */
(
    'RequiresDistance','{"min":2}',110,0,1
),
(
    'RequiresTraitValue','{"a":1, "pm":2}',110,5,1
),
(
    'DistanceCompute','{"actorRollType":"ct", "targetRollType": "cc/agi", "actorRollBonus" : -4}',110,7,0
);

INSERT INTO outcome_instructions (type, parameters, orderIndex, outcome_id)
VALUES 
(
    'lifeloss','{ "actorDamagesTrait": "f", "targetDamagesTrait": "e" }',1,87
),
(
    'lifeloss','{ "actorDamagesTrait": "f", "targetDamagesTrait": "e", "bonusDamagesTrait": -3 }',1,88
),
(
    'teleport','{ "coords": "target" }',2,89
),
(
    'lifeloss','{ "actorDamagesTrait": "f", "targetDamagesTrait": "e", "distance": true}',1,89
),
(
    'applystatus','{ "imposture": true, "stackable": true, "value": 1, "player": "target", "duration": 172800}',10,96
),
(
    'applystatus','{ "leger": true, "stackable": false, "value": 1, "player": "target", "duration": 1}',9,96
);
