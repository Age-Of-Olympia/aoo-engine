INSERT INTO `actions` (id, name, icon, type, display_name, text)
VALUES 
(43, 'brancardier', 'ra-cut-palm', 'heal', 'Brancardier', 'Va falloir soigner tout ça...'),
(44, 'bibinouze', 'ra-cut-palm', 'heal', 'Bibinouze', 'Ca fait du bien là où ça passe.');

INSERT INTO `action_outcomes` (id, apply_to_self, name, on_success, action_id)
VALUES 
(50, 0, 'technique_healing', 1, 43),
(51, 0, 'technique_healing', 1, 44);

INSERT INTO `outcome_instructions` (id, type, parameters, orderIndex, outcome_id)
VALUES 
(67, 'healing', '{ "actorHealingTrait": 50 }', 0, 50),
(68, 'healing', '{ "actorPMHealingTrait": 50 }', 0, 51);