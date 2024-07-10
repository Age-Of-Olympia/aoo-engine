<?php

/*
 * ce script delete les missives sans destinataires
 */


$sql = '
DELETE FROM players_forum_missives
WHERE name IN (
    SELECT name
    FROM players_forum_missives
    WHERE
    name < ?
    GROUP BY name
    HAVING COUNT(*) = 1
)
';

$db = new Db();

$timeLimit = time() - ONE_DAY;

$db->exe($sql, $timeLimit);
