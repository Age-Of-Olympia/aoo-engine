<?php


require_once('config.php');


$player = new Player($_SESSION['playerId']);

if(!$player->have_option('isAdmin')){

    exit('error admin');
}


function getAllJsonKeys($directory) {
    $keys = [];

    // Crée un itérateur pour parcourir tous les fichiers et dossiers de manière récursive
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

    // Parcourt chaque élément trouvé
    foreach ($iterator as $file) {
        // Vérifie si le fichier a l'extension .json
        if ($file->getExtension() === 'json') {
            // Lit le contenu du fichier
            $content = file_get_contents($file->getPathname());
            // Décode le contenu JSON
            $json = json_decode($content, true);
            // Si le contenu est bien un tableau associatif, extrait les clés
            if (is_array($json)) {
                $keys = array_merge($keys, array_keys_recursive($json));
            }
        }
    }

    // Supprime les doublons
    $uniqueKeys = array_unique($keys);

    // Affiche le résultat
    echo "Dans le dossier $directory les .json ont les clés :\n| " . implode(" |\n| ", $uniqueKeys) . " |\n";
}

function array_keys_recursive($array) {
    $keys = [];
    foreach ($array as $key => $value) {
        if(is_numeric($key)){
            continue;
        }
        $keys[] = $key;
        if (is_array($value)) {
            $keys = array_merge($keys, array_keys_recursive($value));
        }
    }
    return $keys;
}

echo '<textarea style="width: 100%; height: 100%">';


foreach(array('plans','players','dialogs','races') as $e){



    // Appel de la fonction avec le répertoire courant
    getAllJsonKeys('./datas/private/'. $e .'/');

    echo "\n";
}

foreach(array('items','actions','factions',) as $e){



    // Appel de la fonction avec le répertoire courant
    getAllJsonKeys('./datas/public/'. $e .'/');

    echo "\n";
}

echo '</textarea>';

?>
