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

INSERT INTO outcome_instructions (type, parameters, orderIndex, outcome_id)
VALUES 
(
    'applystatus','{ "discretion": true, "stackable": false, "value": 1, "player": "target", "duration": 1}',8,78
)

DELETE FROM action_conditions 
WHERE parameters = '{ "repos": "effets" }';

DELETE FROM outcome_instructions
WHERE parameters = '{"carac":"malus", "player": "actor"}';

DELETE FROM outcome_instructions
WHERE parameters = '{ "finished": true, "player": "actor" }';

INSERT INTO actions (name, icon, type, display_name, text, level, race, category, cost, prerequisites)
VALUES 
(
    'recuperation','ra-medical-pack','buff','Récupération',
    'Soin(R/2)',3, null,'spell-support',
    '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">4 PM</span>',null
),
(
    'recuperation_superieure','ra-medical-pack','buff','Récupération supérieure',
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
)

INSERT INTO action_outcomes (id,apply_to_self, name, on_success, action_id)
VALUES 
(
    TODO
)

INSERT INTO action_conditions (conditionType, parameters, action_id, execution_order, blocking)
VALUES 
(
    'RequiresTraitValue','{ "remainingNullable": "a" }',TODO,2,1
),
(
    'RequiresTraitValue','{ "remainingNullable": "mvt" }',TODO,2,1
),
(
    'RequiresTraitValue','{ "a": 1, "imposture": [2,1] }',TODO,10,1
),
(
    'RequiresDistance','{"max":0}',TODO,10,1
);

INSERT INTO outcome_instructions (type, parameters, orderIndex, outcome_id)
VALUES 
(
    'applystatus','{ "imposture": true, "stackable": true, "value": 1, "player": "target", "duration": 172800}',10,TODO
),
(
    'applystatus','{ "leger": true, "stackable": false, "value": 1, "player": "target", "duration": 1}',9,TODO
);
