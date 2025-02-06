ALTER TABLE `players_forum_missives`
MODIFY COLUMN `name` BIGINT NOT NULL;

ALTER TABLE `forums_keywords`
    MODIFY COLUMN `postName` BIGINT NOT NULL;


