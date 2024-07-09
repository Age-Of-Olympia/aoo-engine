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


    public static function get_random_directory($path) {


        // Check if the path is a valid directory
        if (!is_dir($path)) {
            exit("The specified path is not a valid directory.");
        }

        // Open the directory
        $directories = [];
        if ($handle = opendir($path)) {
            // Read the directory entries
            while (false !== ($entry = readdir($handle))) {
                if ($entry != "." && $entry != ".." && is_dir($path . DIRECTORY_SEPARATOR . $entry)) {
                    $directories[] = $entry;
                }
            }
            closedir($handle);
        }

        // Check if any directories were found
        if (empty($directories)) {
            exit("No directories found in the specified path.");
        }

        // Select a random directory
        $random_directory = $directories[array_rand($directories)];
        return $random_directory;
    }


    public static function rrmdir($dir) {


        if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
            if (filetype($dir."/".$object) == "dir")
                self::rrmdir($dir."/".$object);
            else unlink   ($dir."/".$object);
            }
        }
        reset($objects);
        rmdir($dir);
        }
    }


    public static function get_uploaded($player){

        $path='img/ui/forum/uploads/';

        if(!file_exists($path)){

            mkdir($path, 0777);
        }

        $path='img/ui/forum/uploads/'. $player->id;

        if(!file_exists($path)){

            mkdir($path, 0777);
        }

        $uploaded = self::scan_dir($path);

        return $uploaded;
    }


    public static function get_uploaded_max($player){


        if(!isset($player->data)){

            $player->get_data();
        }


        $max = $player->data->pr;

        if($player->have_option('isAdmin')){

            $max *= 100;
        }

        elseif($player->have_option('doubleUpload')){

            $max *= 2;
        }

        return $max;
    }
}
