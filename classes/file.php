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
}
