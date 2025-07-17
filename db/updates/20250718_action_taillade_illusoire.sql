INSERT INTO `actions` (`id`, `name`, `icon`, `type`, `display_name`, `text`) VALUES (NULL, 'dps/taillade', 'ra-barbed-arrow', 'spell', 'Taillade illusoire', 'Des crocs et des griffes spectraux assaillent votre adversaire de toute part');
INSERT INTO `action_conditions` (`id`, `conditionType`, `parameters`, `action_id`, `execution_order`, `blocking`) VALUES (NULL, 'RequiresDistance', '{\"max\":3}', '42', '0', '1');
INSERT INTO `action_conditions` (`id`, `conditionType`, `parameters`, `action_id`, `execution_order`, `blocking`) VALUES (NULL, 'RequiresTraitValue', '{ \"a\": 1, \"pm\": 10 }', '42', '3', '1');
INSERT INTO `action_conditions` (`id`, `conditionType`, `parameters`, `action_id`, `execution_order`, `blocking`) VALUES (NULL, 'SpellCompute', '{\"actorRollType\":\"fm\", \"targetRollType\": \"fm\"}', '42', '10', '0');
INSERT INTO `action_outcomes` (`id`, `apply_to_self`, `name`, `on_success`, `action_id`) VALUES (NULL, '0', 'spell_damage', '1', '42');
INSERT INTO `outcome_instructions` (`id`, `type`, `parameters`, `orderIndex`, `outcome_id`) VALUES (NULL, 'lifeloss', '{ \"actorDamagesTrait\": \"m\", \"targetDamagesTrait\": \"m\", \"bonusDamagesTrait\": 6 }', '0', '49');
