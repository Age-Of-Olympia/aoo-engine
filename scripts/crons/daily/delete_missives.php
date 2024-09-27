<?php

/*
 * ce script delete les missives sans destinataires
 */


$sql = '
SELECT name
FROM players_forum_missives
GROUP BY name
HAVING COUNT(*) = 1
';

$db = new Db();

$res = $db->exe($sql);

$deleteTbl = array();

$sevenDaysAgo = time() - (7 * 24 * 60 * 60);

while($row = $res->fetch_object()){

    $deleteTbl[] = $row->name;

    $topJson = json()->decode('forum/topics', $row->name);

    $latestPostUpdate = 0;

    // Find latest post updated among all posts of topic.
    foreach ($topJson->posts as $e) {
        if (isset($e->last_update_date) && $e->last_update_date > $latestPostUpdate) {
            $latestPostUpdate = $e->last_update_date;
        }
    }

    if ($latestPostUpdate < $sevenDaysAgo) {
        //if latest post older than 7 days, delete topic and posts of missive.

        foreach ($topJson->posts as $e) {
            echo 'post '. $e->name .' deleted<br />';
            @unlink(__DIR__ .'/../../../datas/private/forum/posts/'. $e->name .'.json');
        }

        echo 'topic '. $row->name .' deleted<br />';
        @unlink(__DIR__ .'/../../../datas/private/forum/topics/'. $row->name .'.json');
    } else {
        // Sinon, on ne fait rien
        echo 'No deletion, last post is less than 7 days old<br />';
    }

}


if(count($deleteTbl)){

    $sql = '
    DELETE FROM players_forum_missives
    WHERE name IN ('. Db::print_in($deleteTbl) .')
    ';

    $db->exe($sql, $deleteTbl);
}


echo 'done';
