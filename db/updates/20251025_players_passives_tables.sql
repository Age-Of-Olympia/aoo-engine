CREATE TABLE players_reduction_passives (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player_id INT NOT NULL,
    name VARCHAR(50) NOT NULL
);

CREATE TABLE players_passives (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player_id INT NOT NULL,
    name VARCHAR(255) NOT NULL
);

CREATE TABLE action_passives (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    traits LONGTEXT,
    type VARCHAR(255),
    carac VARCHAR(255),
    value DECIMAL(3,2),
    conditions LONGTEXT,
    niveau INT NOT NULL,
    race VARCHAR(255)
);

ALTER TABLE actions 
ADD COLUMN niveau INT NOT NULL DEFAULT 1,
ADD COLUMN race VARCHAR(255);