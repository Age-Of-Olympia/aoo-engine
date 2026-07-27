<?php
use Classes\File;

/*
 * ce script unlink toutes les images uploadées qui ne sont pas retrouvées dans les posts du forum.
 */

const UPLOADS_PREFIX = 'img/ui/forum/uploads/';

function normalize_upload_key($path){

    $path = str_replace('\\', '/', trim($path));
    $path = preg_replace('#/+#', '/', $path); // 'uploads//x.png' -> 'uploads/x.png'
    $path = ltrim($path, '/');                // '/img/ui/...'    -> 'img/ui/...'

    if(!str_starts_with($path, UPLOADS_PREFIX))
        return null;

    $key = substr($path, strlen(UPLOADS_PREFIX));

    return $key !== '' ? $key : null;
}

function search_img($text, &$result){
    $pattern = '/\[img\](.*?)\[\/img\]/';
    $matches = array();

    if (preg_match_all($pattern, $text, $matches)) {
        foreach ($matches[1] as $match) {
            $key = normalize_upload_key($match);
            if($key !== null)
                $result[$key] = true;
        }
    }
}

$result = array();
$postsRead = 0;

foreach(File::scan_dir(__DIR__ .'/../../../datas/private/forum/posts/', without:'.json') as $e){
    $postJson = json()->decode('forum/posts', $e);
    if (!isset($postJson->text) || !is_string($postJson->text))
        continue;

    $postsRead++;
    search_img($postJson->text, $result);
}

$dir = __DIR__ .'/../../../img/ui/forum/uploads/';

if ($postsRead === 0) {
    echo "Aucun post lisible : suppression annulée par sécurité.\n";
    return;
}

function getImagesFromDir($dir) {
    $images = array();
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $filePath = $file->getPathname();
            if (preg_match('/\.(jpg|jpeg|png|gif|webp|webm|jfif|avif)$/i', $filePath)) {
                $fileName = str_replace($dir, '', $filePath);
                $fileName = ltrim(preg_replace('#/+#', '/', str_replace('\\', '/', $fileName)), '/');
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
