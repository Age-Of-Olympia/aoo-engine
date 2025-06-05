<?php
use Classes\Db;
/*
 * ce script delete les forums_keywords si le fichier json n'existe pas
 * */

$sql = "SELECT id, postName FROM forums_keywords";

$db = new Db();

$res = $db->exe($sql);


while($row = $res->fetch_object()){

    $postName = $row->postName;

    $filePath = 'datas/private/forum/posts/'. $postName . '.json';

    // Si le fichier correspondant n'existe pas, supprimer l'entrée
    if (!file_exists($filePath)) {
        $deleteSql = "DELETE FROM forums_keywords WHERE id = ?";
        $db->exe($deleteSql, [$row->id]);
        echo "line deleted for postName: $postName <br />";
    }
}


echo 'done';
