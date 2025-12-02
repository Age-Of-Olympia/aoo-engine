<?php
use Classes\Db;
/*
 * ce script delete les forums_keywords si le fichier json n'existe pas
 * */

$sql = "SELECT DISTINCT postName FROM forums_keywords";

$db = new Db();

$res = $db->exe($sql);


while($row = $res->fetch_object()){

    $postName = $row->postName;

    $filePath = __DIR__ .'/../../../datas/private/forum/posts/'. $postName . '.json';

    // Si le fichier correspondant n'existe pas, supprimer l'entrée
    if (!file_exists($filePath)) {
        $deleteSql = "DELETE FROM forums_keywords WHERE postName = ?";
        $db->exe($deleteSql, [$postName]);
        echo "lines deleted for postName: $postName <br />";
    }
}


echo 'done';
