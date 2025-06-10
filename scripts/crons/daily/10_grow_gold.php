<?php

$where = '
item_id = 1
AND player_id NOT IN (
    SELECT player_id
    FROM players_effects
    WHERE name = "adrenaline" AND player_id IS NOT NULL
)
AND n >= 100
';

// display gains
$sql = '
SELECT
i.player_id AS playerId,
i.n AS n
FROM players_items_bank AS i
WHERE '. $where .'
';

$res = $db->exe($sql);

if ($res) {
    while($row = $res->fetch_object()){
        $gain = floor($row->n * BANK_PCT / 100);
        // Limitation a 5PO/j de bénéfices max
        if($gain > 5){
            $gain = 5;
        }
        echo '#'. $row->playerId .': '. $row->n .' + '. $gain .'<br />';
    }
} else {
    echo "Erreur dans la requête SQL.";
}

// update bank
$sql = '
UPDATE players_items_bank
SET n = n +  LEAST(FLOOR(n * '. BANK_PCT .' / 100),5)
WHERE '. $where .'
';

$db->exe($sql);


echo 'done';
