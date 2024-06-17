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


    // PLAYER
    if($cmdTbl[0] == 'player'){


        if(is_numeric($cmdTbl[1])){

            $player = new Player($cmdTbl[1]);
        }
        else{

            $player = Player::get_player_by_name($cmdTbl[1]);
        }


        if(!isset($cmdTbl[2])){


            exit('id: '. $player->id .'
            name: '. $player->row->name .'
            race: '. $player->row->race .'
            xp: '. $player->row->xp .'
            pi: '. $player->row->pi .'
            pf: '. $player->row->pf .'
            rank: '. $player->row->rank);
        }


        if($cmdTbl[2] == 'respec'){


            $values = array(
                'player_id'=>$player->id
            );

            $db = new Db();

            $db->delete('players_upgrades', $values);


            $sql = 'UPDATE players SET pi = xp WHERE id = ?';

            $db->exe($sql, $player->id);


            exit($player->row->name .' have respec');
        }


        if($cmdTbl[2] == 'cancel'){


            $sql = 'SELECT * FROM players_upgrades WHERE player_id = ? ORDER BY id DESC LIMIT 1';

            $db = new Db();

            $res = $db->exe($sql, $player->id);

            while($row = $res->fetch_object()){


                $sql = 'DELETE FROM players_upgrades WHERE id = ?';

                $db->exe($sql, $row->id);


                $sql = 'UPDATE players SET pi = pi + ? WHERE id = ?';

                $db->exe($sql, array($row->cost, $player->id));


                exit($player->row->name .' last upgrade canceled ('. $row->name .' for '. $row->cost .'Pi)');
            }

            exit($player->row->name .' has no upgrades');
        }
    }


    // MARKET
    if($cmdTbl[0] == 'market'){


        if(is_numeric($cmdTbl[1])){

            $player = new Player($cmdTbl[1]);
        }
        else{

            $player = Player::get_player_by_name($cmdTbl[1]);
        }


        $table = $cmdTbl[2];

        $item = Item::get_item_by_name($cmdTbl[3]);

        $price = $cmdTbl[4];

        $n = $cmdTbl[5];

        $values = array(
            'item_id'=>$item->id,
            'player_id'=>$player->id,
            'price'=>$price,
            'n'=>$n
        );

        $db = new Db();

        $db->insert('items_'. $table, $values);

        exit($player->row->name .' '. $table .' '. $cmdTbl[3] .' pour '. $price .' x'. $n);
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


        if(!empty($cmdTbl[1])){


            list($width, $height, $type, $attr) = getimagesize('img/'. $cmdTbl[1] .'/'. $cmdTbl[2] .'/'. $cmdTbl[2] .'.png');


            $x = $width / 50;
            $y = $height / 50;


            $imgN = 0;

            $db = new Db();


            $player = new Player($_SESSION['playerId']);
            $player->get_coords();

            $imgX = $player->coords->x;
            $imgY = $player->coords->y;


            for($j=0; $j<$y; $j++){

                $imgX = $player->coords->x;

                for($i=0; $i<$x; $i++){


                    $imgX += 1;


                    $coords = (object) array(
                        'x'=>$imgX-1,
                        'y'=>$imgY,
                        'z'=>$player->coords->z,
                        'plan'=>$player->coords->plan
                    );


                    $coordsId = View::get_coords_id($coords);


                    $values = array(
                        'name'=>$cmdTbl[2] .'/'. $cmdTbl[2] .'-0'. $imgN .'',
                        'coords_id'=>$coordsId
                    );

                    $db->insert('map_'. $cmdTbl[1], $values);

                    $imgN++;
                }

                $imgY -= 1;
            }

            exit('add '. $cmdTbl[1] .'/'. $cmdTbl[2] .' '. $x .'x'. $y);


        }


        exit('tiled');
    }
}
