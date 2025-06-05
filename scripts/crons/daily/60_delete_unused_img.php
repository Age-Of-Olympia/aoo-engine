<?php
use Classes\File;

/*
 * ce script unlink toutes les images uploadées qui ne sont pas retrouvées dans les posts du forum.
 */


function search_img($text, &$result){


    // Expression régulière pour capturer le contenu entre [img] et [/img]
    $pattern = '/\[img\](.*?)\[\/img\]/';

    // Tableau pour stocker les correspondances
    $matches = array();

    // Utilisation de preg_match_all pour capturer toutes les correspondances
    if (preg_match_all($pattern, $text, $matches)) {
        // Les correspondances sont dans $matches[1]
        foreach ($matches[1] as $match) {

            if(str_starts_with($match,"img/ui/forum/uploads/")) // keep only file name and use dict for faster check
                $result[str_replace("img/ui/forum/uploads/","",$match)]=true;
        }
    }
}


$result = array();


foreach(File::scan_dir(__DIR__ .'/../../../datas/private/forum/posts/', without:'.json') as $e){

    $postJson = json()->decode('forum/posts', $e);

    search_img($postJson->text, $result);
}


// printr($result);


$dir = __DIR__ .'/../../../img/ui/forum/uploads/';


// Fonction pour parcourir récursivement un dossier et retourner les chemins des images trouvées
function getImagesFromDir($dir) {
    $images = array();
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $filePath = $file->getPathname();
            // Vérifier si le fichier est une image en fonction de son extension
            if (preg_match('/\.(jpg|jpeg|png|gif)$/i', $filePath)) {
                $fileName= str_replace($dir, '', $filePath);
                $images[] = $fileName;
            }
        }
    }

    return $images;
}


function verifyImages($dir, $ImagesUsed) {
    $imagesOnDisk = getImagesFromDir($dir);
    $allImagesExist = true;

    foreach ($imagesOnDisk as $image) {
        // On considère que $result contient les chemins complets ou les URLs des images
        if (!array_key_exists($image, $ImagesUsed)) {
            echo "L'image $image n'est pas dans la liste et a été supprimée.<br />";

            unlink($dir.$image);

            $allImagesExist = false;
        }
    }

    return $allImagesExist;
}

// Exécution de la vérification
if (verifyImages($dir, $result)) {
    echo "Toutes les images de $dir sont présentes dans la liste.\n";
} else {
    echo "Certaines images de $dir ne sont pas présentes dans la liste.\n";
}
