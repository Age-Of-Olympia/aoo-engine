CREATE TABLE forums_cookie (
    post_name VARCHAR(20) NOT NULL,
    player_id INT DEFAULT NULL,
    PRIMARY KEY (post_name,player_id)
);

UPDATE players set pr = pr*10;