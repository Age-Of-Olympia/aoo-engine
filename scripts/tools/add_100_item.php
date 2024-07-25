<?php


$player = new Player($_SESSION['playerId']);

if(!$player->have_option('isAdmin')){

    exit('error admin');
}


$db = new Db();

// Define the players and the time ranges
$players = [
    (object)['id' => 1, 'coords' => (object)['plan' => 'Plan1']],
    (object)['id' => 2, 'coords' => (object)['plan' => 'Plan2']],
    (object)['id' => 3, 'coords' => (object)['plan' => 'Plan3']],
    (object)['id' => 4, 'coords' => (object)['plan' => 'Plan4']]
];
$dateRange = 10; // Last 10 days

// Loop through the last 10 days
for ($i = 0; $i < $dateRange; $i++) {
    $date = date('Y-m-d', strtotime("-50 day"));

    foreach ($players as $player) {
        // Define the time range based on the player
        if ($player->id == 1 || $player->id == 4) {
            $hour = rand(8, 23);
        } else if ($player->id == 2 || $player->id == 4) {
            $hour = rand(18, 19);
        } else {
            $hour = rand(8, 23);
        }

        $minute = rand(0, 59);

        // Generate a timestamp for the player
        $timestamp = strtotime("$date $hour:$minute:00");

        // Prepare values for insertion
        $values = array(
            'player_id' => $player->id,
            'target_id' => rand(1, 4), // Random target_id for demonstration
            'text' => 'Log entry for player ' . $player->id,
            'plan' => $player->coords->plan,
            'time' => $timestamp,
            'type' => 'ActionType' // Example type
        );

        // Insert the data
        $db->insert('players_logs', $values);
    }
}

echo "Data generation complete.";


echo 'done!';
