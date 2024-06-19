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


                if($_POST['goPlan'] == $e->plan){


                    $fromDir = Str::get_from_dir($dir);

                    $goPlanJson = json()->decode('plans', $e->plan);


                    // war
                    if(!empty($goPlanJson->war)){


                        exit('error war');
                    }


                    if(!empty($goPlanJson->enters->$fromDir)){


                        $coords = (object) array(
                            'x'=>$goPlanJson->enters->$fromDir->x,
                            'y'=>$goPlanJson->enters->$fromDir->y,
                            'z'=>$goPlanJson->enters->$fromDir->z,
                            'plan'=>$e->plan
                        );
                    }
                    else{


                        $coords = (object) array(
                            'x'=>0,
                            'y'=>0,
                            'z'=>0,
                            'plan'=>$e->plan
                        );
                    }

                    $player->go($coords);


                    $text = $player->row->name .' a voyagé de '. $planJson->name .' à '. $goPlanJson->name .'.';
                    Log::put($player, $player, $text, $type="travel");

                    $player->coords->plan = $e;

                    Log::put($player, $player, $text, $type="travel");


                    exit();
                }
            }


            ?>

            $('.map[data-plan="<?php echo $e->plan ?>"]')
            .css('opacity', 1)
            .data('opacity', 1)
            .addClass('blink');

            $('.text[data-plan="<?php echo $e->plan ?>"]')
            .show()
            .css('opacity', 1)
            .data('opacity', 1)
            .addClass('blink');

            <?php
        }
    }
}
