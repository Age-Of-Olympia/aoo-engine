CREATE TABLE players_reduction_passives (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player_id INT NOT NULL,
    name VARCHAR(50) NOT NULL
);

CREATE TABLE action_passives (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    traits LONGTEXT,
    type VARCHAR(255),
    carac VARCHAR(255),
    value DECIMAL(3,2),
    conditions LONGTEXT,
    level INT NOT NULL,
    race VARCHAR(255)
);

CREATE TABLE players_passives (
    player_id INT NOT NULL,
    passive_id INT NOT NULL,
    
    PRIMARY KEY (player_id, passive_id),

    CONSTRAINT fk_players_passives_passive
        FOREIGN KEY (passive_id)
        REFERENCES action_passives(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

ALTER TABLE actions 
ADD COLUMN level INT NOT NULL DEFAULT 1,
ADD COLUMN race VARCHAR(255);

ALTER TABLE players 
ADD COLUMN visible VARCHAR(255);