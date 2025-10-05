CREATE TABLE forums_cookie (
    post_name BIGINT(20) NOT NULL,
    player_id INT DEFAULT NULL,
    PRIMARY KEY (post_name,player_id)
);
