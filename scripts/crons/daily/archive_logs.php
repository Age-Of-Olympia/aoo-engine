<?php


$timeLimit = time() - THREE_DAYS;

// copy
$sql = '
INSERT INTO
players_logs_archives
SELECT *
FROM
players_logs
WHERE
time < ?
AND id NOT IN
(select MAX(id) from players_logs_archives pla WHERE type = \'move\' GROUP BY player_id)
';

$db->exe($sql, $timeLimit);


// delete old
$sql = '
DELETE
FROM
players_logs
WHERE
time < ?
AND id NOT IN
(select MAX(id) from players_logs_archives pla WHERE type = \'move\' GROUP BY player_id)
';

$db->exe($sql, $timeLimit);


echo 'done';
