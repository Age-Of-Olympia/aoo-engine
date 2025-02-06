<?php

// DB
function db()
{
    global $link;

    if(!isset($link)){


        // db credentials are sotcked in config/db_constants.php
        $link = @mysqli_connect(DB_CONSTANTS['host'], DB_CONSTANTS['user'], DB_CONSTANTS['psw'], DB_CONSTANTS['db']);

        if(!$link){

            // error msg & retry
            echo "Error: Unable to connect to DB." . PHP_EOL;

            exit;
        }

        // set charset tot utf8
        if (!$link->set_charset("utf8"))
            printf("Error loading character set utf8: %s\n", $link->error);
    }

    return $link;
}

// json
function json()
{
    global $json;

    if(!isset($json)){

        $json = new Json();
    }

    return $json;
}

// printr
function printr($text){

    echo '<pre>';

    print_r($text);

    echo '</pre>';
}

// sqln
function sqln(){

    global $sqln;

    if(!isset($sqln)){

        $sqln = 1;
    }
    else{

        $sqln++;
    }

    return $sqln;
}

function removeComments($jsonString) {
    // Supprime les commentaires de type //
    $jsonString = preg_replace('/\/\/[^\n]*\n/', '', $jsonString);
    // Supprime les commentaires de type /* */
    $jsonString = preg_replace('/\/\*.*?\*\//s', '', $jsonString);
    return $jsonString;
}

function timestampNormalization($timestamp){
    if ($timestamp > 32503680000) {
        return (int) ($timestamp / 1000); // Conversion de millisecondes en secondes
    } else {
        return (int) $timestamp; // Le timestamp est déjà en secondes
    }
}

//Helper For Json Reply
function ExitError($error){
    exit(json_encode(["error" => $error]));
}

function ExitSuccess($success){
    exit(json_encode(["result" => $success]));
}

