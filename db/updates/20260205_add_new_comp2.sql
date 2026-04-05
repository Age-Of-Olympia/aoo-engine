ALTER TABLE actions
ADD COLUMN category VARCHAR(50) DEFAULT NULL,
ADD COLUMN cost VARCHAR(255),
ADD COLUMN prerequisites VARCHAR(50) DEFAULT NULL;

ALTER TABLE action_passives
ADD COLUMN category VARCHAR(50) DEFAULT NULL,
ADD COLUMN prerequisites VARCHAR(50) DEFAULT NULL,
ADD COLUMN display_name VARCHAR(255) DEFAULT NULL, 
ADD COLUMN text TEXT DEFAULT NULL;

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
    WHEN name = 'epuisement_arcanique' THEN '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">4 PM</span>'
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
    WHEN name = 'epuisement_arcanique' THEN 'spell-curse'
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
    ELSE category
END;

UPDATE actions 
SET text = CASE 
    WHEN name = 'epuisement' THEN 'Jet pur. Essoufflement(X/2) où X est la différence des jets de dé'
    WHEN name = 'attaque_precise' THEN '+4 pour toucher, -3 Dmg'
    WHEN name = 'attaque_violente' THEN '-6 pour toucher, +2 Dmg'
    WHEN name = 'croc-en-jambe' THEN 'Ralentissement(x2D2)'
    WHEN name = 'manchette' THEN 'Jet pur. Maladresse(X/2)  où X est la différence des jets de dé'
    WHEN name = 'arme_infusee' THEN '+M/3 Dmg'
    WHEN name = 'tir_epuisant' THEN 'Jet pur. Essoufflement(X/3) où X est la différence des jets de dé'
    WHEN name = 'tir_precis' THEN '+4 pour toucher, -3 Dmg'
    WHEN name = 'tir_violent' THEN '-6 pour toucher, +2 Dmg'
    WHEN name = 'tir_a_la_cheville' THEN 'Nécessite une arme à munitions. Ralentissement(x1D2)'
    WHEN name = 'tir_handicapant' THEN 'Jet pur. Vulnérabilité(X/3)  où X est la différence des jets de dé'
    WHEN name = 'jet_infuse' THEN 'Nécessite une arme de jet. +M/3 Dmg'
    WHEN name = 'epuisement_arcaniques' THEN 'Jet pur. Essoufflement(X/3) où X est la différence des jets de dé'
    WHEN name = 'arcane_precise' THEN 'Bonus +0, +4 pour toucher'
    WHEN name = 'arcane_violente' THEN 'Bonus +5, -6 pour toucher'
    WHEN name = 'aveuglement' THEN 'Aveuglement(x1)'
    WHEN name = 'coup_precis' THEN 'Dextérité(x2)'
    WHEN name = 'peau_de_granit' THEN 'Protection(x2)'
    WHEN name = 'maladresse' THEN 'Maladresse(x2)'
    WHEN name = 'vulnerabilite' THEN 'Vulnérabilité(x2)'
    WHEN name = 'restauration_mineure' THEN 'Restauration(5)'
    WHEN name = 'enchevetrement' THEN 'Bonus +1, Ralentissement (x1D2)'
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
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">2 PM</span>',null
),
(
    'coup_epaule','ra-shovel','technique','Coup d''épaule',
    'Une attaque à -4 pour toucher et -3 Dmg',1, null,'melee-off',
    '<span style="color: #27ae60;">5 Mvt</span>',null
),
(
    'saut_attaque','ra-overhead','technique','Saut d''attaque',
    'Saute sur la cible et l''attaque au contact.',3, null,'melee-off',
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
    'Soin(X) où X est la R de la cible',3, null,'spell-support',
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
    'Maladresse(x2), Vulnérabilité(x2)',2, null,'spell-curse',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">8 PM</span>',null
),
(
    'fatigue','ra-broken-shield','spell','Fatigue',
    'Vulnérabilité(x4)',3, null,'spell-curse',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">12 PM</span>',null
),
(
    'malchance','ra-cut-palm','spell','Malchance',
    'Maladresse(x4)',3, null,'spell-curse',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">8 PM</span>',null
),
(
    'puissance_lutin','ra-player-thunder-struck','spell','Puissance du Lutin capricieux',
    'Maladresse(x4), Vulnérabilité(x4)',5, null,'spell-curse',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">20 PM</span>',null
),
(
    'extenuation','ra-broken-shield','spell','Exténuation',
    'Vulnérabilité(x8)',5, null,'spell-curse',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">25 PM</span>',null
),
(
    'guigne','ra-broken-shield','spell','Guigne',
    'Maladresse(x8)',5, null,'spell-curse',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">20 PM</span>',null
),
(
    'attaque_drainante','ra-knife-fork','technique','Attaque drainante',
    'Drain',3, null,'melee-off',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">4 PM</span>',null
),
(
    'attaque_siphonnante','ra-knife-fork','technique','Attaque siphonnante',
    'Siphon',3, null,'melee-off',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #c0392b;">2 PV</span>',null
),
(
    'frappe_tempe','ra-decapitation','technique','Frappe à la tempe',
    'Dommages Mentaux(X/2) où X est le nombre de dégâts infligés',3, null,'melee-off',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">4 PM</span>',null
),
(
    'arme_impro','ra-wrench','technique','Arme improvisée',
    'Permet d''effectuer une attaque à distance à -4 pour toucher et -2 Dmg sans le matériel adéquat',1, null,'distance-off',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">2 PM</span>',null
),
(
    'bout_portant','ra-supersonic-arrow','technique','Bout portant',
    'Une attaque avec une arme de Jet au contact à -8 pour toucher',1, null,'distance-off',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">2 PM</span>',null
),
(
    'tir_ajuste','ra-supersonic-arrow','technique','Tir ajusté',
    'Avantage',1, null,'distance-off',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">2 PM</span>',null
),
(
    'jet_sable','ra-splash','technique','Jet de sable',
    'Un jet de sable au contact sans dégâts et sans besoin d''arme. Aveuglement(x2)',2, null,'distance-curse',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">4 PM</span>, <span style="color: #27ae60;">1 Mvt</span>',null
),
(
    'arcane_ajustee','ra-fairy-wand','technique','Arcane ajustée',
    'Bonus +3, Avantage',1, null,'spell-off',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">6 PM</span>',null
),
(
    'dard','ra-fairy-wand','technique','Dard',
    'Bonus +1',1, null,'spell-off',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">3 PM</span>',null
),
(
    'drain','ra-knife-fork','technique','Drain',
    'Bonus +1, Drain',2, null,'spell-off',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">6 PM</span>',null
),
(
    'siphon','ra-knife-fork','technique','Siphon',
    'Bonus +1, Siphon',2, null,'spell-off',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #c0392b;">5 PV</span>, <span style="color: #27ae60;">2 Mvt</span>',null
),
(
    'stabilisation','ra-boot-stomp','buff','Stabilisation',
    'Stabilité(+6)',2, null,'spell-support',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">2 PM</span>, <span style="color: #27ae60;">1 Mvt</span>',null
),
(
    'renforcement','ra-lion','buff','Renforcement',
    'Renforcement(x6)',2, null,'spell-support',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">6 PM</span>',null
),
(
    'instabilite','ra-falling','spell','Instabilité',
    'Instabilité(x6)',2, null,'spell-curse',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">6 PM</span>',null
),
(
    'bousculade','ra-falling','technique','Bousculade',
    'Touche automatiquement. Repousse la cible d''une case si le Repoussement fonctionne.',2, null,'melee-curse',
    '<span style="color: #8e44ad;">1 A</span>,<span style="color: #27ae60;">1 Mvt</span>',null
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
    100,0,'bene_bene',1,90
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
),
(
    121,0,'dtechnique_bout_portant',1,111
),
(
    122,0,'dtechnique_tir_ajuste',1,112
),
(
    123,0,'dtechnique_jet_sable',1,113
),
(
    124,0,'spell_arcane_ajustee',1,114
),
(
    125,0,'spell_dard',1,115
),
(
    126,0,'spell_drain',1,116
),
(
    127,0,'spell_siphon',1,117
),
(
    128,0,'bene_stabilisation',1,118
),
(
    129,0,'bene_renforcement',1,119
),
(
    130,0,'spell_instabilite',1,120
),
(
    131,0,'mtechnique_bousculade',1,121
);

INSERT INTO action_passives (name, traits, type, carac, value, conditions, level, race,category,display_name,text)
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
),
(
    'duelliste','["cc"]','att','advantage',0.00,null, 4,"",
    "melee","Duelliste","Gagne Avantage sur les attaques et techniques basées sur la CC"
),
(
    'lanceur','["ct"]','att','advantage',0.00,'{"weapon":["pierre","lance","javelot_lourd","pilum","hache_jet","pierre_noire"]}', 4,"",
    "distance","Lanceur","Gagne Avantage sur les attaques et techniques basées sur la CT avec une arme de jet"
),
(
    'tireur-elite','["ct"]','att','advantage',0.00,'{"weapon":["arc","fustibale","arc_long","arc_elfique","arc_ensorcele","sarbacane"]}', 4,"",
    "distance","Tireur d'élite","Gagne Avantage sur les attaques et techniques basées sur la CT avec une arme à munitions"
),
(
    'anguille','["cc/agi"]','def','advantage',0.00,null, 4,"",
    "survival","Anguille","Gagne Avantage sur les esquives"
),
(
    'volonte-fer','["fm"]','def','advantage',0.00,null, 4,"",
    "survival","Volonté de Fer","Gagne Avantage en résistant à la magie"
),
(
    'couverture','["cc"]','esquive_tir','',0.00,null, 4,"",
    "melee","Couverture","Esquive les Tirs à 9/10 CC et 1/10 Agi si il est équipé d'un Bouclier"
),
(
    'reflexes_fulgurants','["agi"]','esquive_tir','',0.00,null, 4,"",
    "survival","Réflexes fulgurants","Esquiver les Tirs se fait à 6/7 Agi et 1/7 CC"
),
(
    'inepuisable','["malus"]','malus','',1.00,null, 4,"",
    "survival","Inépuisables","Les Malus appliqués par des actions adverses sont réduits de 1"
),
(
    'maitre_bretteur','["cc"]','malus','fixed',2.00,null, 4,"",
    "melee","Maître bretteur","Les Malus appliqués par les actions de contact sont augmentées de 2"
),
(
    'escarmoucheur','["ct"]','malus','fixed',2.00,'{"weapon":["arc","fustibale","arc_long","arc_elfique","arc_ensorcele","sarbacane"]}', 4,"",
    "distance","Escarmoucheur","Les Malus appliqués par les actions de tir avec des armes à munitions sont augmentées de 2"
),
(
    'berserker','["cc"]','att','lostPV',0.10,null, 2,"geant",
    "melee","Berserker","Gagne +1 en CC en attaque tous les 10PV perdus"
),
(
    'mage_sacre','["fm"]','buff','effects',2.00,'{"category":["spell-support"]}', 2,"olympien",
    "spell","Mage sacré","Gagne +2 en FM pour lancer des sorts de soutien par Effet sur lui"
);


INSERT INTO action_conditions (conditionType, parameters, action_id, execution_order, blocking)
VALUES 
/* coup_precis */
(
    'BuffCompute','{"actorRollType":"fm", "targetRollType": "fm"}',63,10,0
),
/* peau_de_granit */
(
    'BuffCompute','{"actorRollType":"fm", "targetRollType": "fm"}',64,10,0
),
/* restauration_mineure */
(
    'BuffCompute','{"actorRollType":"fm", "targetRollType": "fm"}',67,10,0
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
    'MeleeCompute','{"actorRollType":"cc", "targetRollType": "cc/agi", "actorAdvantage":true}',77,10,0
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
    'MeleeCompute','{"actorRollType":"cc", "targetRollType": "cc/agi", "actorRollBonus" : -4}',78,10,0
),
/* saut_attaque */
(
    'RequiresWeaponType','{"type": ["melee"]}',79,1,1
),
(
    'RequiresTraitValue','{"a":1, "pm":10, "mvt":1}',79,5,1
),
(
    'MeleeCompute','{"actorRollType":"cc", "targetRollType": "cc/agi"}',79,10,0
),
/* recuperation */
(
    'RequiresDistance','{"max":1}',80,0,1
),
(
    'RequiresTraitValue','{ "a":1, "pm":4 }',80,3,1
),
(
    'BuffCompute','{"actorRollType":"fm", "targetRollType": "fm"}',80,10,0
),
/* recuperation_superieure */
(
    'RequiresDistance','{"max":1}',81,0,1
),
(
    'RequiresTraitValue','{ "a":1, "pm":10 }',81,3,1
),
(
    'BuffCompute','{"actorRollType":"fm", "targetRollType": "fm"}',81,10,0
),
/* restauration */
(
    'RequiresDistance','{"max":1}',82,0,1
),
(
    'RequiresTraitValue','{ "a":1, "pm":6 }',82,3,1
),
(
    'BuffCompute','{"actorRollType":"fm", "targetRollType": "fm"}',82,10,0
),
/* restauration_majeure*/
(
    'RequiresDistance','{"max":1}',83,0,1
),
(
    'RequiresTraitValue','{ "a":1, "pm":12 }',83,3,1
),
(
    'BuffCompute','{"actorRollType":"fm", "targetRollType": "fm"}',83,10,0
),
/* regeneration */
(
    'RequiresDistance','{"max":1}',84,0,1
),
(
    'RequiresTraitValue','{ "a":1, "pm":4 }',84,3,1
),
(
    'BuffCompute','{"actorRollType":"fm", "targetRollType": "fm"}',84,10,0
),
/* regeneration_acceleree */
(
    'RequiresDistance','{"max":1}',85,0,1
),
(
    'RequiresTraitValue','{ "a": 1, "pm":10 }',85,3,1
),
(
    'BuffCompute','{"actorRollType":"fm", "targetRollType": "fm"}',85,10,0
),
/* puissance_nature */
(
    'RequiresDistance','{"max":1}',86,0,1
),
(
    'RequiresTraitValue','{ "a": 1, "pm":8 }',86,3,1
),
(
    'BuffCompute','{"actorRollType":"fm", "targetRollType": "fm"}',86,10,0
),
/* aide */
(
    'RequiresDistance','{"max":1}',87,0,1
),
(
    'RequiresTraitValue','{ "a": 1, "pm":8 }',87,3,1
),
(
    'BuffCompute','{"actorRollType":"fm", "targetRollType": "fm"}',87,10,0
),
/* reflexes_accruse */
(
    'RequiresDistance','{"max":1}',88,0,1
),
(
    'RequiresTraitValue','{ "a": 1, "pm":8 }',88,3,1
),
(
    'BuffCompute','{"actorRollType":"fm", "targetRollType": "fm"}',88,10,0
),
/* reflexes_accruse */
(
    'RequiresDistance','{"max":1}',89,0,1
),
(
    'RequiresTraitValue','{ "a": 1, "pm":8 }',89,3,1
),
(
    'BuffCompute','{"actorRollType":"fm", "targetRollType": "fm"}',89,10,0
),
/* benediction */
(
    'RequiresDistance','{"max":1}',90,0,1
),
(
    'RequiresTraitValue','{ "a": 1, "pm":15 }',90,3,1
),
(
    'BuffCompute','{"actorRollType":"fm", "targetRollType": "fm"}',90,10,0
),
/* sauvegarde */
(
    'RequiresDistance','{"max":1}',91,0,1
),
(
    'RequiresTraitValue','{ "a": 1, "pm":20 }',91,3,1
),
(
    'BuffCompute','{"actorRollType":"fm", "targetRollType": "fm"}',91,10,0
),
/* virtuose */
(
    'RequiresDistance','{"max":1}',92,0,1
),
(
    'RequiresTraitValue','{ "a": 1, "pm":20 }',92,3,1
),
(
    'BuffCompute','{"actorRollType":"fm", "targetRollType": "fm"}',92,10,0
),
/* armure */
(
    'RequiresDistance','{"max":1}',93,0,1
),
(
    'RequiresTraitValue','{ "a": 1, "pm":8 }',93,3,1
),
(
    'BuffCompute','{"actorRollType":"fm", "targetRollType": "fm"}',93,10,0
),
/* agressivite */
(
    'RequiresDistance','{"max":1}',94,0,1
),
(
    'RequiresTraitValue','{ "a": 1, "pm":6 }',94,3,1
),
(
    'BuffCompute','{"actorRollType":"fm", "targetRollType": "fm"}',94,10,0
),
/* cuirasse */
(
    'RequiresDistance','{"max":1}',95,0,1
),
(
    'RequiresTraitValue','{ "a": 1, "pm":15 }',95,3,1
),
(
    'BuffCompute','{"actorRollType":"fm", "targetRollType": "fm"}',95,10,0
),
/* ferocite */
(
    'RequiresDistance','{"max":1}',96,0,1
),
(
    'RequiresTraitValue','{ "a": 1, "pm":12 }',96,3,1
),
(
    'BuffCompute','{"actorRollType":"fm", "targetRollType": "fm"}',96,10,0
),
/* faiblesse */
(
    'RequiresDistance','{"min":2}',97,0,1
),
(
    'RequiresTraitValue','{ "a": 1, "pm":6 }',97,3,1
),
(
    'SpellCompute','{"actorRollType":"fm", "targetRollType": "fm"}',97,10,0
),
/* fragilite */
(
    'RequiresDistance','{"min":2}',98,0,1
),
(
    'RequiresTraitValue','{ "a": 1, "pm":10 }',98,3,1
),
(
    'SpellCompute','{"actorRollType":"fm", "targetRollType": "fm"}',98,10,0
),
/* friabilite */
(
    'RequiresDistance','{"min":2}',99,0,1
),
(
    'RequiresTraitValue','{ "a": 1, "pm":20 }',99,3,1
),
(
    'SpellCompute','{"actorRollType":"fm", "targetRollType": "fm"}',99,10,0
),
/* anemie */
(
    'RequiresDistance','{"min":2}',100,0,1
),
(
    'RequiresTraitValue','{ "a": 1, "pm":12 }',100,3,1
),
(
    'SpellCompute','{"actorRollType":"fm", "targetRollType": "fm"}',100,10,0
),
/* colere_nature */
(
    'RequiresDistance','{"min":2}',101,0,1
),
(
    'RequiresTraitValue','{ "a": 1, "pm":8 }',101,3,1
),
(
    'SpellCompute','{"actorRollType":"fm", "targetRollType": "fm"}',101,10,0
),
/* fatigue */
(
    'RequiresDistance','{"min":2}',102,0,1
),
(
    'RequiresTraitValue','{ "a": 1, "pm":12 }',102,3,1
),
(
    'SpellCompute','{"actorRollType":"fm", "targetRollType": "fm"}',102,10,0
),
/* malchance */
(
    'RequiresDistance','{"min":2}',103,0,1
),
(
    'RequiresTraitValue','{ "a": 1, "pm":8 }',103,3,1
),
(
    'SpellCompute','{"actorRollType":"fm", "targetRollType": "fm"}',103,10,0
),
/* puissance_lutin */
(
    'RequiresDistance','{"min":2}',104,0,1
),
(
    'RequiresTraitValue','{ "a": 1, "pm":20 }',104,3,1
),
(
    'SpellCompute','{"actorRollType":"fm", "targetRollType": "fm"}',104,10,0
),
/* extenuation */
(
    'RequiresDistance','{"min":2}',105,0,1
),
(
    'RequiresTraitValue','{ "a": 1, "pm":25 }',105,3,1
),
(
    'SpellCompute','{"actorRollType":"fm", "targetRollType": "fm"}',105,10,0
),
/* guigne */
(
    'RequiresDistance','{"min":2}',106,0,1
),
(
    'RequiresTraitValue','{ "a": 1, "pm":20 }',106,3,1
),
(
    'SpellCompute','{"actorRollType":"fm", "targetRollType": "fm"}',106,10,0
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
    'MeleeCompute','{"actorRollType":"cc", "targetRollType": "cc/agi"}',107,10,0
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
    'MeleeCompute','{"actorRollType":"cc", "targetRollType": "cc/agi"}',108,10,0
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
    'MeleeCompute','{"actorRollType":"cc", "targetRollType": "cc/agi"}',109,10,0
),
/* arme_impro */
(
    'RequiresDistance','{"min":2}',110,0,1
),
(
    'RequiresTraitValue','{"a":1, "pm":2}',110,5,1
),
(
    'DistanceCompute','{"actorRollType":"ct", "targetRollType": "cc/agi", "actorRollBonus" : -4}',110,10,0
),
/* bout_portant */
(
    'RequiresDistance','{"max":1}',111,0,1
),
(
    'RequiresWeaponType','{"type": ["jet"]}',111,1,1
),
(
    'RequiresTraitValue','{"a":1, "pm":2}',111,5,1
),
(
    'DistanceCompute','{"actorRollType":"ct", "targetRollType": "cc/agi", "actorRollBonus" : -8}',111,10,0
),
/* tir_ajuste */
(
    'RequiresDistance','{"min":2}',112,0,1
),
(
    'RequiresWeaponType','{"type": ["tir","jet"]}',112,1,1
),
(
    'RequiresTraitValue','{"a":1, "pm":2}',112,5,1
),
(
    'DistanceCompute','{"actorRollType":"ct", "targetRollType": "cc/agi", "actorAdvantage":true}',112,10,0
),
/* jet_sable */
(
    'RequiresDistance','{"max":1}',113,0,1
),
(
    'RequiresTraitValue','{"a":1, "pm":4, "mvt":1}',113,5,1
),
(
    'DistanceCompute','{"actorRollType":"ct", "targetRollType": "cc/agi"}',113,10,0
),
/* arcane_ajustee */
(
    'RequiresDistance','{"min":2}',114,0,1
),
(
    'RequiresTraitValue','{"a":1, "pm":6}',114,5,1
),
(
    'SpellCompute','{"actorRollType":"fm", "targetRollType": "fm", "actorAdvantage":true}',114,10,0
),
/* dard */
(
    'RequiresDistance','{"min":2}',115,0,1
),
(
    'RequiresTraitValue','{"a":1, "pm":3}',115,5,1
),
(
    'SpellCompute','{"actorRollType":"fm", "targetRollType": "fm"}',115,10,0
),
/* drain */
(
    'RequiresDistance','{"min":2}',116,0,1
),
(
    'RequiresTraitValue','{"a":1, "pm":6}',116,5,1
),
(
    'SpellCompute','{"actorRollType":"fm", "targetRollType": "fm"}',116,10,0
),
/* siphon */
(
    'RequiresDistance','{"min":2}',117,0,1
),
(
    'RequiresTraitValue','{"a":1, "pv":5, "mvt":2}',117,5,1
),
(
    'SpellCompute','{"actorRollType":"fm", "targetRollType": "fm"}',117,10,0
),
/* stabilisation */
(
    'RequiresDistance','{"max":1}',118,0,1
),
(
    'RequiresTraitValue','{ "a": 1, "pm":2, "mvt":1 }',118,3,1
),
(
    'BuffCompute','{"actorRollType":"fm", "targetRollType": "fm"}',118,10,0
),
/* renforcement */
(
    'RequiresDistance','{"max":1}',119,0,1
),
(
    'RequiresTraitValue','{ "a": 1, "pm":2, "mvt":1 }',119,3,1
),
(
    'BuffCompute','{"actorRollType":"fm", "targetRollType": "fm"}',119,10,0
),
/* instabilite */
(
    'RequiresDistance','{"min":2}',120,0,1
),
(
    'RequiresTraitValue','{"a":1, "pm":6}',120,5,1
),
(
    'SpellCompute','{"actorRollType":"fm", "targetRollType": "fm"}',120,10,0
),
/* bousculade */
(
    'RequiresDistance','{"max":1}',121,0,1
),
(
    'RequiresTraitValue','{"a":1, "mvt":1}',121,5,1
);

INSERT INTO outcome_instructions (type, parameters, orderIndex, outcome_id)
VALUES 
/* coup_ajuste */
(
    'lifeloss','{ "actorDamagesTrait": "f", "targetDamagesTrait": "e" }',1,87
),
/* coup_epaule */
(
    'lifeloss','{ "actorDamagesTrait": "f", "targetDamagesTrait": "e", "bonusDamagesTrait": -3 }',1,88
),
/* attaque_sautee */
(
    'teleport','{ "coords": "target" }',2,89
),
(
    'lifeloss','{ "actorDamagesTrait": "f", "targetDamagesTrait": "e", "distance": true}',1,89
),
/* recuperation */
(
    'healing','{ "bonusHealingTrait": "r", "divisor": 2 }',2,90
),
/* recuperation_superieure */
(
    'healing','{ "bonusHealingTrait": "r"}',2,91
),
/* restauration */
(
    'removemalus','{ "actorCarac": "r","caracDivisor": 2}',2,92
),
/* restauration_majeure */
(
    'removemalus','{ "actorCarac": "r"}',2,93
),
/* regeneration */
(
    'healing','{ "targetHealingTrait": "r", "divisor": 2 }',2,94
),
/* regeneration_acceleree */
(
    'healing','{ "targetHealingTrait": "r" }',2,95
),
/* pas_leger */
(
    'applystatus','{ "imposture": true, "stackable": true, "value": 1, "player": "target", "duration": 172800}',10,96
),
(
    'applystatus','{ "leger": true, "stackable": false, "value": 1, "player": "target", "duration": 1}',9,96
),
/* puissance_nature */
(
    'applystatus','{ "dexterite": true, "stackable": false, "value": 2, "player": "target", "duration": 86400}',10,97
),
(
    'applystatus','{ "protection": true, "stackable": false, "value": 2, "player": "target", "duration": 86400}',9,97
),
/* aide */
(
    'applystatus','{ "dexterite": true, "stackable": false, "value": 4, "player": "target", "duration": 86400}',10,98
),
/* reflexes_accrus */
(
    'applystatus','{ "protection": true, "stackable": false, "value": 4, "player": "target", "duration": 86400}',10,99
),
/* benediction */
(
    'applystatus','{ "dexterite": true, "stackable": false, "value": 4, "player": "target", "duration": 86400}',10,100
),
(
    'applystatus','{ "protection": true, "stackable": false, "value": 4, "player": "target", "duration": 86400}',9,100
),
/* sauvegarde */
(
    'applystatus','{ "dexterite": true, "stackable": false, "value": 8, "player": "target", "duration": 86400}',10,101
),
/* virtuose */
(
    'applystatus','{ "protection": true, "stackable": false, "value": 8, "player": "target", "duration": 86400}',10,102
),
/* armure */
(
    'applystatus','{ "armure": true, "stackable": false, "value": 1, "player": "target", "duration": 64800}',10,103
),
/* agressivite */
(
    'applystatus','{ "agressivite": true, "stackable": false, "value": 1, "player": "target", "duration": 64800}',10,104
),
/* cuirasse */
(
    'applystatus','{ "armure": true, "stackable": false, "value": 2, "player": "target", "duration": 64800}',10,105
),
/* ferocite */
(
    'applystatus','{ "agressivite": true, "stackable": false, "value": 2, "player": "target", "duration": 64800}',10,106
),
/* fragilite */
(
    'applystatus','{ "fragilite": true, "stackable": false, "value": 1, "player": "target", "duration": 64800}',10,107
),
/* friabilite */
(
    'applystatus','{ "fragilite": true, "stackable": false, "value": 2, "player": "target", "duration": 64800}',10,108
),
/* faiblesse */
(
    'applystatus','{ "faiblesse": true, "stackable": false, "value": 1, "player": "target", "duration": 64800}',10,109
),
/* anemie */
(
    'applystatus','{ "faiblesse": true, "stackable": false, "value": 2, "player": "target", "duration": 64800}',10,110
),
/* colere_nature */
(
    'applystatus','{ "maladresse": true, "stackable": false, "value": 2, "player": "target", "duration": 86400}',10,111
),
(
    'applystatus','{ "vulnerabilite": true, "stackable": false, "value": 2, "player": "target", "duration": 86400}',9,111
),
/* fatigue */
(
    'applystatus','{ "vulnerabilite": true, "stackable": false, "value": 4, "player": "target", "duration": 86400}',10,112
),
/* malchance */
(
    'applystatus','{ "maladresse": true, "stackable": false, "value": 4, "player": "target", "duration": 86400}',10,113
),
/* puissance_lutin */
(
    'applystatus','{ "maladresse": true, "stackable": false, "value": 4, "player": "target", "duration": 86400}',10,114
),
(
    'applystatus','{ "vulnerabilite": true, "stackable": false, "value": 4, "player": "target", "duration": 86400}',9,114
),
/* extenuation */
(
    'applystatus','{ "vulnerabilite": true, "stackable": false, "value": 8, "player": "target", "duration": 86400}',10,115
),
/* guigne */
(
    'applystatus','{ "maladresse": true, "stackable": false, "value": 8, "player": "target", "duration": 86400}',10,116
),
/* attaque_drainante */
(
    'lifeloss','{ "actorDamagesTrait": "f", "targetDamagesTrait": "e", "drain":true }',1,117
),
/* attaque_siphonnante */
(
    'lifeloss','{ "actorDamagesTrait": "f", "targetDamagesTrait": "e", "siphon":true }',1,118
),
/* frappe_tempe */
(
    'lifeloss','{ "actorDamagesTrait": "f", "targetDamagesTrait": "e"}',1,119
),
(
    'manaloss','{ "lossType": "lifeloss" }',2,119
),
/* arme_impro */
(
    'lifeloss','{ "actorDamagesTrait": "f", "targetDamagesTrait": "e", "bonusDamagesTrait": -2}',1,120
),
/* bout_portant */
(
    'lifeloss','{ "actorDamagesTrait": "f", "targetDamagesTrait": "e"}',1,121
),
/* tir_ajuste */
(
    'lifeloss','{ "actorDamagesTrait": "f", "targetDamagesTrait": "e"}',1,122
),
/* jet_sable */
(
    'applystatus','{ "aveuglement": true, "stackable": false, "value": 2, "player": "target", "duration": 86400}',10,123
),
/* arcane_ajustee */
(
    'lifeloss','{ "actorDamagesTrait": "m", "targetDamagesTrait": "m", "bonusDamagesTrait": 3}',1,124
),
/* dard */
(
    'lifeloss','{ "actorDamagesTrait": "m", "targetDamagesTrait": "m", "bonusDamagesTrait": 1}',1,125
),
/* drain */
(
    'lifeloss','{ "actorDamagesTrait": "m", "targetDamagesTrait": "m", "bonusDamagesTrait": 1, "drain":true }',1,126
),
/* siphon */
(
    'lifeloss','{ "actorDamagesTrait": "m", "targetDamagesTrait": "m", "bonusDamagesTrait": 1, "drain":true }',1,127
),
/* stabilisation */
(
    'applystatus','{ "stabilite": true, "stackable": true, "value": 6, "player": "target", "duration": 1}',10,128
),
/* renforcement */
(
    'applystatus','{ "renforcement": true, "stackable": false, "value": 6, "player": "target", "duration": 1}',10,129
),
/* instabilite */
(
    'applystatus','{ "instabilite": true, "stackable": false, "value": 6, "player": "target", "duration": 1}',10,130
),
/* bousculade */
(
    'teleport','{ "coords": "opposite" }',2,131
);

