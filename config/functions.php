<?php
use Classes\Json;

// DB
function db()
{
    global $link;

    if(!isset($link)){

         //temp lock réutilisation de la connection de doctrine voir bootstrap.php
         echo "Error: Unable to connect to DB." . PHP_EOL;

         exit;
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

function SanitizeIntChecked(&$var, $error = null)
{
    if (is_numeric($var)) {
        $var = (int)$var;
    } else {
        ExitError($error ? $error : INVALID_REQ);
    }
}
define('INVALID_REQ', "Invalid request");

