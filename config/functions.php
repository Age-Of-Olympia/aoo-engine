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
        // Pick the first unused negative id walking down from -1.
        // The previous MIN(id)-1 algorithm let one stray insert push
        // every later NPC down with it (we ended up creating NPCs at
        // -1,000,023 in dev because a now-deleted row had once sat
        // near -1,000,000). The anti-join finds the highest negative
        // id whose predecessor is missing — i.e. the first available
        // slot — so deleted-NPC gaps are reused.
        //
        // Anchored on -1 always being present in production (the
        // permanent 'Lutin de test' seed); the -2 fallback only
        // fires for empty test fixtures.
        $result = $connection->executeQuery(
            "SELECT MAX(t.id) - 1 AS next_id
             FROM players t
             LEFT JOIN players p2 ON p2.id = t.id - 1
             WHERE t.id < 0 AND p2.id IS NULL"
        );
        $row = $result->fetchAssociative();
        return $row['next_id'] ?? -2;
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

