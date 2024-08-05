<?php

/*
 * ce script delete les missives sans destinataires
 */


$sql = '
SELECT name
FROM players_forum_missives
WHERE
name < ?
GROUP BY name
HAVING COUNT(*) = 1
';

$db = new Db();

$timeLimit = time() - ONE_DAY;

$res = $db->exe($sql, $timeLimit);

$deleteTbl = array();

while($row = $res->fetch_object()){


    $deleteTbl[] = $row->name;

    $topJson = json()->decode('forum/topics', $row->name);

    foreach($topJson->posts as $e){


        echo 'post '. $e->name .' deleted<br />';

        @unlink('datas/private/forum/posts/'. $e->name .'.json');
    }

    echo 'topic '. $row->name .' deleted<br />';

    @unlink('datas/private/forum/topics/'. $row->name .'.json');
}


if(count($deleteTbl)){

    $sql = '
    DELETE FROM players_forum_missives
    WHERE name IN ('. Db::print_in($deleteTbl) .')
    ';

    $db->exe($sql, $deleteTbl);
}


echo 'done';
