<?php


if(!empty($success) && $success == true){


    $gold = new Item(1);

    $goldN = $gold->get_n($target);

    $gain = floor($goldN * 0.1);

    if($gain < 1){

        $gain = 1;
    }


    $gold->give_item($target, $player, $gain);


    echo '<div>Vous dérobez '. $gain .'Po à '. $target->data->name .'</div>';
}

else{


    echo '<div>Vous êtes pris la main dans le sac!</div>';
}
