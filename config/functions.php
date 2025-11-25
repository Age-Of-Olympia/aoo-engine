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

/**
 * Get next available entity ID for a specific entity type
 * Uses ID range allocation to keep real players sequential
 *
 * @param string $type Entity type: 'real', 'tutorial', 'npc', 'building'
 * @return int Next available ID in the type's range
 */
function getNextEntityId(string $type): int {
    $ranges = ENTITY_ID_RANGES;

    if (!isset($ranges[$type])) {
        throw new InvalidArgumentException("Invalid entity type: $type");
    }

    $range = $ranges[$type];

    // Use Doctrine connection via db() function
    $connection = db();

    if ($type === 'npc') {
        // Negative IDs: get MIN and decrement
        $result = $connection->executeQuery(
            "SELECT MIN(id) as min_id FROM players WHERE player_type = 'npc'"
        );
        $row = $result->fetchAssociative();
        $minId = $row['min_id'] ?? -1;
        return $minId - 1;
    } else {
        // Positive ranges: get MAX in range and increment
        $result = $connection->executeQuery(
            "SELECT MAX(id) as max_id FROM players WHERE id BETWEEN ? AND ?",
            [$range['start'], $range['end']]
        );
        $row = $result->fetchAssociative();
        $maxId = $row['max_id'] ?? ($range['start'] - 1);
        return $maxId + 1;
    }
}

/**
 * Get next display ID (sequential within entity type)
 *
 * @param string $type Entity type: 'real', 'tutorial', 'npc', 'building'
 * @return int Next sequential display ID for this type
 */
function getNextDisplayId(string $type): int {
    // Use Doctrine connection via db() function
    $connection = db();

    $result = $connection->executeQuery(
        "SELECT MAX(display_id) as max_display FROM players WHERE player_type = ?",
        [$type]
    );
    $row = $result->fetchAssociative();
    $maxDisplay = $row['max_display'] ?? 0;

    return $maxDisplay + 1;
}

