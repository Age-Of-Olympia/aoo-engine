ALTER TABLE `players`
RENAME COLUMN `fatigue` TO `energie`;

UPDATE `action_conditions` 
SET `parameters` = '{ "a": 1 }' 
WHERE `id` = 30;

UPDATE `action_conditions` 
SET `parameters` = '{ "repos": "effets" }' 
WHERE `id` = 32;

UPDATE `action_conditions` 
SET `parameters` = '{ "energie": "both" }' 
WHERE `id` = 41;