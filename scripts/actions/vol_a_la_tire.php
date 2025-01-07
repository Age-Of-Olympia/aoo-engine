<?php

const MIN_GOLD_STOLEN = 5;
const MAX_XP = 3;

if(!empty($success) && $success == true){

    $gold = new Item(1);
    $goldInTargetInventory = $gold->get_n($target);
    $takenFromInventory = floor($goldInTargetInventory * 0.1);

    if($takenFromInventory < 1){
        $takenFromInventory = 1;
    }

    // Here we take from the target inventory. 
    $res = $gold->give_item($target, $player, $takenFromInventory);

    $gain = $takenFromInventory;
    // if we took less than MIN_GOLD_STOLEN po from target player we add the difference to the inventory and to the gain
    if ($takenFromInventory < MIN_GOLD_STOLEN) {
        // If target had enough gold in his pockets
        if ($res) {
            $goldAddedToComplete = MIN_GOLD_STOLEN - $takenFromInventory;
            $gold->add_item($player, $goldAddedToComplete);
            $gain += $goldAddedToComplete; 
        }
        // Pockets were empty or the gold could not be given
        else if ($goldInTargetInventory == 0 || !$res) {
            $gold->add_item($player, MIN_GOLD_STOLEN);
            $gain = MIN_GOLD_STOLEN;
        } 
    }

    echo '<div>Vous obtenez '. $gain .'Po grâce à votre larcin sur '. $target->data->name .'</div>';

    $playerXp = $player->get_action_xp($target);

    if ($playerXp > MAX_XP) {
        $playerXp = MAX_XP;
    }

    echo '
        <div class="action-details">
            '. $player->data->name .' (rang '. $playerRank .') +'. $playerXp .'Xp
        </div>
    ';
}

else{

    $playerXp = 0;
    $targetXp = 2;

    echo '<div>Vous êtes pris la main dans le sac!</div>';
}
