/*Up Jet de sable */
UPDATE actions 
SET text = 'Attaque sans dégâts et sans arme. Aveuglement (x2)'
WHERE id = 113;

UPDATE actions 
SET cost = '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">10 PM</span>, <span style="color: #27ae60;">1 Mvt</span>'
WHERE id = 113;

UPDATE action_conditions 
SET parameters = '{"a":1, "pm":10, "mvt":1}'
WHERE id = 354;

UPDATE action_passives 
SET name = 'dur_cuire'
WHERE id = 3;

UPDATE action_passives 
SET display_name = 'Dur à cuire'
WHERE id = 3;

UPDATE action_passives 
SET level = 3
WHERE id = 3;