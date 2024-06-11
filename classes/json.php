<?php

class json{

    public function decode($type, $name){


        // id
        $this->id = $name;

        // path
        $path = dirname(__FILE__) .'/../datas/public/'. $type .'/'. $name .'.json';

        // not public
        if(!file_exists($path)){

            // private
            $path = dirname(__FILE__) .'/../datas/private/'. $type .'/'. $name .'.json';
        }

        // already decoded?
        if(!empty($this->paths[$path])){

            return $this->paths[$path];
        }

        // check if exists
        if(!file_exists($path)){

            return false;
        }

        // open
        $str = file_get_contents($path);

        // error not json
        if(!$this->isJson($str)){

            return false;
        }

        // decode
        $json = json_decode($str);

        // store decoded path
        $this->paths[$path] = $json;

        return $json;
    }


    public function isJson($string) {


        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }


    public static function create_json($path){


        $myfile = fopen(dirname(__FILE__) .'/../'. $path, "w") or die("Unable to open file!");
        fwrite($myfile, '{"id":"new"}');
        fclose($myfile);
    }


    public static function write_json($path, $data){


        $myfile = fopen(dirname(__FILE__) .'/../'. $path, "w") or die("Unable to open file!");
        fwrite($myfile, $data);
        fclose($myfile);
    }


    public static function encode($data){


        return json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
    }

}
