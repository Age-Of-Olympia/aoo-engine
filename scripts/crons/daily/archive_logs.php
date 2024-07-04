<?php


$timeLimit = time() - ONE_DAY;

// copy
$sql = '
INSERT INTO
players_logs_archives
SELECT *
FROM
players_logs
WHERE
time < ?
';

$db->exe($sql, $timeLimit);


// delete old
$sql = '
DELETE
FROM
players_logs
WHERE
time < ?
';

$db->exe($sql, $timeLimit);
