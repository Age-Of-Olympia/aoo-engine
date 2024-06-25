<?php


$paramsTbl = explode(',', $params);

$forbid = false;

foreach($paramsTbl as $e){


    $needsTbl = explode(':', $e);


    if($needsTbl[0] == 'item'){


        $item = new Item($needsTbl[1]);


        $n = (!empty($needsTbl[2])) ? $needsTbl[2] : 1;


        if($item->get_n($player) < $n){


            $forbid = true;

            break;
        }
    }

    elseif($needsTbl[0] == 'spell'){


        if(!$player->have_spell($needsTbl[1])){


            $forbid = true;

            break;
        }
    }
}


if($forbid){


    echo '<script>alert("Le passage reste clos.");</script>';

    exit();
}
