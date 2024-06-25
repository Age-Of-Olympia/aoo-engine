<?php

if(!empty($_GET['triggerId'])){


    $db = new Db();

    $res = $db->get_single('map_triggers', $_GET['triggerId']);

    if(!$res->num_rows){


        exit('error trigger id');
    }

    $row = $res->fetch_object();

    $triggerId = $row->id;

    $coords = View::get_coords('triggers', $triggerId);

    $distance = View::get_distance($player->coords, $coords);

    if($distance <= 1){


        $dir = explode(':', $row->params)[1];

        foreach($planJson->exits->$dir as $e){


            if(!empty($_POST['goPlan'])){


                ob_clean();


                if($_POST['goPlan'] == $e){


                    $fromDir = Str::get_from_dir($dir);

                    $goPlanJson = json()->decode('plans', $e);


                    // war
                    if(!empty($goPlanJson->war)){


                        exit('error war');
                    }

                    // enter coords
                    $sql = '
                    SELECT
                    x,y,z
                    FROM
                    coords
                    INNER JOIN
                    map_triggers
                    ON
                    coords.id = map_triggers.coords_id
                    WHERE
                    name = "enter"
                    AND
                    plan = ?
                    AND
                    z = ?
                    AND
                    params = ?
                    ';


                    $res = $db->exe($sql, array(
                        $e,
                        $player->coords->z,
                        $params="direction:$fromDir"
                    ));


                    if($res->num_rows){


                        $row = $res->fetch_object();


                        $coords = (object) array(
                            'x'=>$row->x,
                            'y'=>$row->y,
                            'z'=>$row->z,
                            'plan'=>$e
                        );
                    }
                    else{


                        $coords = (object) array(
                            'x'=>0,
                            'y'=>0,
                            'z'=>0,
                            'plan'=>$e
                        );
                    }


                    // travel price
                    $item = new Item(1); // or
                    if($item->get_n($player) >= TRAVEL_COST){


                        $item->add_item($player, -TRAVEL_COST);
                    }
                    else{


                        $player->add_effect('fatigue', $duration=ONE_DAY);
                    }


                    $player->go($coords);

                    $player->get_data();


                    $text = $player->data->name .' a voyagé de '. $planJson->name .' à '. $goPlanJson->name .'.';
                    Log::put($player, $player, $text, $type="travel");

                    $player->coords->plan = $e;

                    Log::put($player, $player, $text, $type="travel");


                    exit();
                }
            }


            ?>

            $('.map[data-plan="<?php echo $e ?>"]')
            .css('opacity', 1)
            .data('opacity', 1)
            .addClass('blink');

            $('.text[data-plan="<?php echo $e ?>"]')
            .show()
            .css('opacity', 1)
            .data('opacity', 1)
            .addClass('blink');

            <?php
        }
    }
}
