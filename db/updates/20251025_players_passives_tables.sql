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