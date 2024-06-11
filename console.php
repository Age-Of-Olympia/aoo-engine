<?php

require_once('config.php');


if(!empty($_POST['getLastCmd'])){

    echo $_SESSION['lastCmd'] ?? '';

    exit();
}


if(!empty($_POST['cmd'])){


    $cmd = $_SESSION['lastCmd'] = $_POST['cmd'];


    $cmdTbl = explode(' ', $cmd);


    // CREATE
    if($cmdTbl[0] == 'create'){


        // PLAYER
        if($cmdTbl[1] == 'player'){


            $lastId = Player::put_player($cmdTbl[2], $cmdTbl[3]);


            exit('Player '. $cmdTbl[2] .' créé ('. $cmdTbl[3] .', mat.'. $lastId .')');
        }

        // ALTAR
        if($cmdTbl[1] == 'altar'){


            if(is_numeric($cmdTbl[2])){

                $player = new Player($cmdTbl[2]);
            }
            else{

                $player = Player::get_player_by_name($cmdTbl[2]);
            }

            $values = array(
                'player_id'=>$player->id,
                'wall_id'=>$cmdTbl[3]
            );

            $db = new Db();

            $db->insert('altars', $values);

            exit('Altar du dieu '. $player->row->name .' ajouté à wall #'. $cmdTbl[3] .'');
        }

        // ITEM
        if($cmdTbl[1] == 'item'){


            $private = (!empty($cmdTbl[3])) ? 1 : 0;


            $lastId = Item::put_item($cmdTbl[2], $private);


            $dir = ($private) ? 'private' : 'public';


            $data = (object) array(
                'id'=>$lastId,
                'name'=>$cmdTbl[2],
                "private"=>$private,
                'price'=>1,
                'text'=>"Description de l'objet."
            );


            Json::write_json('datas/'. $dir .'/items/'. $cmdTbl[2] . '.json', Json::encode($data));


            exit('Item '. $cmdTbl[2] .' créé (id.'. $lastId .')');
        }

        // JSON
        if($cmdTbl[1] == 'json'){

            $data = (object) array(
                'name'=>$cmdTbl[4]
            );

            Json::write_json('datas/'. $cmdTbl[2] .'/'. $cmdTbl[3] .'/'. $cmdTbl[4] . '.json', Json::encode($data));


            exit('datas/'. $cmdTbl[2] .'/'. $cmdTbl[3] .'/'. $cmdTbl[4] . '.json a bien été créé');
        }

    }


    // EDIT
    if($cmdTbl[0] == 'edit'){


        $json = json()->decode($cmdTbl[1], $cmdTbl[2]);

        if(!$json){

            exit($cmdTbl[1] .' '. $cmdTbl[2] .'.json not found');
        }

        exit($cmdTbl[1] .','. $cmdTbl[2] .',json');
    }


    // XP
    if($cmdTbl[0] == 'xp'){


        if(is_numeric($cmdTbl[1])){

            $player = new Player($cmdTbl[1]);
        }
        else{

            $player = Player::get_player_by_name($cmdTbl[1]);
        }

        $player->put_xp($cmdTbl[2]);

        exit($cmdTbl[2] .'Xp et Pi ajoutés à '. $player->row->name);
    }


    // LOGS
    if($cmdTbl[0] == 'log'){


        if(is_numeric($cmdTbl[1])){

            $player = new Player($cmdTbl[1]);
        }
        else{

            $player = Player::get_player_by_name($cmdTbl[1]);
        }

        if(is_numeric($cmdTbl[2])){

            if($cmdTbl[2] == 0){

                $target = 0;
            }

            $target = new Player($cmdTbl[2]);
        }
        else{

            $target = Player::get_player_by_name($cmdTbl[2]);
        }

        unset($cmdTbl[0]);
        unset($cmdTbl[1]);
        unset($cmdTbl[2]);

        $text = implode(' ', $cmdTbl);

        Log::put($player, $target, $text);

        exit($text);
    }


    // EFFECT
    if($cmdTbl[0] == 'effect'){


        if(is_numeric($cmdTbl[1])){

            $player = new Player($cmdTbl[1]);
        }
        else{

            $player = Player::get_player_by_name($cmdTbl[1]);
        }


        if($player->have('effects', $cmdTbl[2])){


            $player->end_effect($cmdTbl[2]);

            exit('Effet '. $cmdTbl[2] .' enlevé à '. $player->row->name .'');
        }

        else{

            // duration
            $duration = (!empty($cmdTbl[3])) ? $cmdTbl[3] : 0;

            $player->add_effect($cmdTbl[2], $duration);

            exit('Effet '. $cmdTbl[2] .' ajouté à '. $player->row->name .'');
        }

    }

    // ACTION
    if($cmdTbl[0] == 'action'){


        if(is_numeric($cmdTbl[1])){

            $player = new Player($cmdTbl[1]);
        }
        else{

            $player = Player::get_player_by_name($cmdTbl[1]);
        }


        if($player->have('actions', $cmdTbl[2])){


            $player->end_action($cmdTbl[2]);

            exit('Action '. $cmdTbl[2] .' enlevé à '. $player->row->name .'');
        }

        else{

            // duration
            $duration = (!empty($cmdTbl[3])) ? $cmdTbl[3] : 0;

            $player->add_action($cmdTbl[2]);

            exit('Action '. $cmdTbl[2] .' ajouté à '. $player->row->name .'');
        }

    }

    // OPTION
    if($cmdTbl[0] == 'option'){


        if(is_numeric($cmdTbl[1])){

            $player = new Player($cmdTbl[1]);
        }
        else{

            $player = Player::get_player_by_name($cmdTbl[1]);
        }


        if($player->have('options', $cmdTbl[2])){


            $player->end_option($cmdTbl[2]);

            exit('Option '. $cmdTbl[2] .' enlevé à '. $player->row->name .'');
        }

        else{

            // duration
            $duration = (!empty($cmdTbl[3])) ? $cmdTbl[3] : 0;

            $player->add_option($cmdTbl[2]);

            exit('Option '. $cmdTbl[2] .' ajouté à '. $player->row->name .'');
        }

    }


    // ADD
    if($cmdTbl[0] == 'add'){


        if(is_numeric($cmdTbl[1])){

            $player = new Player($cmdTbl[1]);
        }
        else{

            $player = Player::get_player_by_name($cmdTbl[1]);
        }


        if(is_numeric($cmdTbl[2])){

            $item = new Item($cmdTbl[2]);
        }
        else{

            $item = Item::get_item_by_name($cmdTbl[2]);
        }


        $item->add_item($player, $cmdTbl[3]);


        exit('Item '. $item->row->name .' x'. $cmdTbl[3] .' ajouté à '. $player->row->name .'');
    }


    // TP
    if($cmdTbl[0] == 'tp'){


        if(is_numeric($cmdTbl[1])){

            $player = new Player($cmdTbl[1]);
        }
        else{

            $player = Player::get_player_by_name($cmdTbl[1]);
        }


        $player->get_coords();


        $coordsTbl = explode(',', $cmdTbl[2]);

        $coords = (object) array();

        $coords->x = $coordsTbl[0];
        $coords->y = $coordsTbl[1];

        $coords->z = (!empty($coordsTbl[2])) ? $coordsTbl[2] : $player->coords->z;
        $coords->plan = (!empty($coordsTbl[3])) ? $coordsTbl[3] : $player->coords->plan;


        $player->go($coords);


        exit($player->row->name .' téléporté en '. implode(',', (array) $coords) .'');
    }


    // SESSION
    if($cmdTbl[0] == 'session'){


        // OPEN
        if($cmdTbl[1] == 'open'){


            $login = $cmdTbl[2];

            if(!is_numeric($login)){


                $player = Player::get_player_by_name($login);
            }
            else{


                $player = new Player($login);
            }


            $_SESSION['playerId'] = $player->id;


            exit('Session ouverte pour joueur '. $player->row->name .'.');
        }
    }


    // EDITOR
    if($cmdTbl[0] == 'editor'){


        exit('editor');
    }


    // MAP
    if($cmdTbl[0] == 'tiled'){


        exit('tiled');
    }
}
