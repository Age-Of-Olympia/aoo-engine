<?php
namespace Classes;

class Json{
    public ?string $id = null;
    private $paths;
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

        $str = removeComments($str);

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

    public function get_all($type, $s2 = false) {
        $paths = [
            dirname(__FILE__) . '/../datas/public/' . $type . '/',
            dirname(__FILE__) . '/../datas/private/' . $type . '/'
        ];
        
        $allData = [];
        
        foreach ($paths as $path) {
            if (!is_dir($path)) continue;
            
            $pattern = $s2 ? '*_s2.json' : '*.json';
            $files = glob($path . $pattern);
            
            foreach ($files as $file) {
                $name = basename($file, '.json');
                $data = $this->decode($type, $name);
                if ($data) {
                    $allData[$name] = $data;
                }
            }
        }
        
        return $allData;
    }
}
