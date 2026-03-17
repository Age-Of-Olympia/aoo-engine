ALTER TABLE actions
ADD COLUMN category VARCHAR(50);

ALTER TABLE action_passives
ADD COLUMN category VARCHAR(50);

ALTER TABLE players_actions
DROP COLUMN charges;
