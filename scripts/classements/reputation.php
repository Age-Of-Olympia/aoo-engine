<?php


echo '<h1>Joueurs les plus Réputés</h1>';


// Fonction de comparaison pour trier par "pr" (Power Rank)
function compareByPr($a, $b) {
    return $b->pr - $a->pr; // Tri décroissant
}


$path = 'datas/public/classements/reputation.html';

if(file_exists($path) && CACHED_CLASSEMENTS){


    echo file_get_contents($path);
}

else{


    ob_start();

    // Trier le tableau en utilisant la fonction de comparaison
    usort($list, 'compareByPr');


    // just as a marker
    $list[0]->showReput = 1;


    print_players($list);


    $data = ob_get_clean();

    $myfile = fopen($path, "w") or die("Unable to open file!");
    fwrite($myfile, $data);
    fclose($myfile);

    echo $data;
}
