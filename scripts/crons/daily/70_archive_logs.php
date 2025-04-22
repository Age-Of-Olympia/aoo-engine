<?php

$timeLimit = time() - THREE_DAYS;

try {
    // Start a transaction
    $db->start_transaction("archives");

    // 1: Temporary table to store the max move IDs
    $sql = '
    CREATE TEMPORARY TABLE temp_max_ids AS
    SELECT MAX(id) AS max_id
    FROM players_logs_archives
    WHERE type = \'move\'
    GROUP BY player_id
    ';

    $db->exe($sql);

    // 2: Copy records to the archive table in batches
    $batchSize = 500;
    $offset = 0;

    echo "\nInserting: ";
    while (true) {
        echo '##'.$offset; 
        $sql = '
        INSERT IGNORE INTO players_logs_archives
        SELECT pl.*
        FROM players_logs pl
        LEFT JOIN temp_max_ids tmi ON pl.id = tmi.max_id
        WHERE pl.time < ?
        AND tmi.max_id IS NULL
        LIMIT ? OFFSET ?
        ';

        $rowsAffected = $db->exe($sql, [$timeLimit, $batchSize, $offset], getAffectedRows:true);

        echo '-'.$rowsAffected; 
        if ($rowsAffected == 0) {
            break;
        }

        $offset += $batchSize;
    }

    // 3: Delete old records from the logs table in batches
    $offset = 0;

    echo "\nDeleting : ";
    while (true) {
        
        $sql = '
        DELETE pl FROM players_logs pl
        LEFT JOIN temp_max_ids tmi ON pl.id = tmi.max_id
        WHERE pl.time < ?
        AND tmi.max_id IS NULL
        ';

        $rowsAffected = $db->exe($sql, [$timeLimit], getAffectedRows:true);
        echo '#';
        if ($rowsAffected == 0) {
            break;
        }
    }
    echo "\n";

    // 4: Drop the temporary table
    $sql = 'DROP TEMPORARY TABLE temp_max_ids';
    $db->exe($sql);
    $db->commit_transaction("archives");

    echo 'done';
} catch (Exception $e) {
    $db->rollback_transaction("archives");
    echo 'Error: ' . $e->getMessage();
}

?>
