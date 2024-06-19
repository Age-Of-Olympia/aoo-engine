<?php


class File{


    // real path for cron compatibility
    public static function inc($file){

        include(dirname(__FILE__) .'/../'. $file);
    }

    // real path for cron compatibility
    public static function req_once($file){

        require_once(dirname(__FILE__) .'/../'. $file);
    }

    // preload image or list of images
    public static function preload($images){


        // single img : to array
        if(!is_array($images)){

            $images = array($images);
        }


        echo '
        <div id="file-preload">
            ';

            foreach($images as $e){


                echo '<img src="'. $e .'" />';
            }

            echo '
        </div>
        ';
    }


    // write
    public static function write($path, $data){

        // write file .json
        $myfile = fopen($path, "w") or die("Unable to open file!");
        fwrite($myfile, $data);
        fclose($myfile);
    }

    public static function scan_dir($path, $without = 0) : array{


        $files = scandir($path);

        // exclude
        $files = array_diff(scandir($path), array('.', '..', 'tmp'));

        $return = array();

        foreach( $files as $e ){

            // remove some char
            if(!empty($without)){
                $return[] = substr($e, 0, -strlen($without));
                continue;
            }

            $return[] = $e;
        }

        return $return;
    }


    public static function refresh_player_cache($ext) {


        $dir = __DIR__ .'/../datas/private/players';

        if(!file_exists($dir)){

            exit('error dir');
        }

        // Utilise DirectoryIterator pour itérer à travers les fichiers du répertoire
        $iterator = new DirectoryIterator($dir);
        foreach ($iterator as $fileinfo) {
            if ($fileinfo->isFile() && $fileinfo->getExtension() === $ext) {
                $filePath = $fileinfo->getPathname();
                // Supprime le fichier
                if (unlink($filePath)) {
                    echo "Fichier supprimé : $filePath\n";
                } else {
                    echo "Erreur lors de la suppression du fichier : $filePath\n";
                }
            }
        }
    }

    // Répertoire de départ (remplacez par le répertoire souhaité)
    $directory = __DIR__ . '/path/to/directory';

    // Supprime tous les fichiers .json dans le répertoire spécifié
    deleteJsonFiles($directory);
}
