ALTER TABLE players_kills
ADD COLUMN is_inactive BOOLEAN NOT NULL DEFAULT FALSE AFTER assist;