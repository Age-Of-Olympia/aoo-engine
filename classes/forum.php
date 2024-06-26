<?php

class Forum{


    public static function get_views($topJson){


        if(!isset($topJson->views)){

            return array();
        }

        return $topJson->views;
    }


    public static function put_view($topJson){


        if(!isset($topJson->views)){

            $topJson->views = array();
        }

        if(in_array($_SESSION['playerId'], $topJson->views)){


            return false;
        }


        $topJson->views[] = $_SESSION['playerId'];


        $data = Json::encode($topJson);

        Json::write_json('datas/private/forum/topics/'. $topJson->name .'.json', $data);


        return true;
    }


    public static function put_reward($postJson, $reward){


        if(!isset($postJson->rewards)){

            $postJson->rewards = array();
        }


        $postJson->rewards[] = (object) $reward;


        $data = Json::encode($postJson);

        Json::write_json('datas/private/forum/posts/'. $postJson->name .'.json', $data);


        return true;
    }


    public static function approve($topJson){


        if(!empty($topJson->approved)){

            return false;
        }


        $topJson->approved = 1;


        $data = Json::encode($topJson);

        Json::write_json('datas/private/forum/topics/'. $topJson->name .'.json', $data);


        return true;
    }


    public static function delete_views($topJson){


        $topJson->views = array();

        $data = Json::encode($topJson);

        Json::write_json('datas/private/forum/topics/'. $topJson->name .'.json', $data);
    }


    public static function get_pages($postTotal){


        $pagesN = floor($postTotal / 5);

        if($postTotal > $pagesN*5){

            $pagesN++;
        }

        return $pagesN;
    }


    public static function refresh_last_posts(){

        // Répertoire de départ
        $directory = 'datas/private/forum/topics/'; // Remplacez par le répertoire souhaité

        // Récupère le fichier le plus récemment modifié
        $mostRecentFile = self::get_most_recent($directory);

        $topName = 'Aucun';

        if ($mostRecentFile !== null) {

            $topJson = json()->decode('forum', 'topics/'. $mostRecentFile);

            if(strlen($topJson->title) > 10){

                $topName = htmlentities(substr($topJson->title, 0, 10)) .'...';
            }

            else{

                $topName = htmlentities($topJson->title) .'';
            }


            $postJson = json()->decode('forum', 'posts/'. end($topJson->posts)->name);

            $author = new Player($postJson->author);
            $author->get_data();

            $pageN = self::get_pages($postTotal=count($topJson->posts));

            $topName = 'Dans <a href="forum.php?topic='. htmlentities($topJson->name) .'&page='. $pageN .'#'. $postJson->name .'">'. $topName .'</a> par '. $author->data->name;
        }


        $lastPosts = json()->decode('forum', 'lastPosts');


        $forumJson = json()->decode('forum', 'forums/'. $topJson->forum_id);


        if(!empty($forumJson->factions)){


            foreach($forumJson->factions as $faction){


                $lastPosts->$faction->text = $topName;
                $lastPosts->$faction->time = time();
            }
        }

        else{


            $lastPosts->general->text = $topName;
            $lastPosts->general->time = time();
        }

        $data = Json::encode($lastPosts);

        Json::write_json('datas/private/forum/lastPosts.json', $data);
    }


    public static function get_most_recent($dir) {

        $latestFile = null;
        $latestTime = 0;

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $fileTime = $file->getMTime();
                if ($fileTime > $latestTime) {
                    $latestTime = $fileTime;
                    $latestFile = $file->getFilename();
                }
            }
        }

        if ($latestFile !== null) {
            return pathinfo($latestFile, PATHINFO_FILENAME); // Retourne le nom sans l'extension
        }

        return null;
    }


    public static function check_access($player, $forumJson){


        if(!empty($forumJson->factions)){


            if(!in_array($player->data->faction, $forumJson->factions)){

                exit('Accès refusé');
            }
        }
    }

    public static function put_topic($player, $forumJson, $title, $text){


        $path = 'datas/private/forum/topics/'. time() .'.json';

        $data = (object) array(

            "name"=>time(),

            "title"=>$title,

            "author"=>$player->id,

            "forum_id"=>$forumJson->name,

            "last"=>(object) array("author"=>"", "time"=>0),

            "posts"=>array()
        );

        $data = Json::encode($data);

        Json::write_json($path, $data);


        $topJson = json()->decode('forum', 'topics/'. time());


        // create post
        self::put_post($player, $topJson, $text);

        self::put_last_author($player, $topJson);


        return $topJson;
    }

    public static function put_post($player, $topJson, $text){


        $path = 'datas/private/forum/posts/'. time() .'.json';

        $data = (object) array(

            "name"=>time(),

            "author"=>$player->id,

            "top_id"=>$topJson->name,

            "text"=>$text
        );

        $data = Json::encode($data);

        Json::write_json($path, $data);


        self::add_post_in_topic($name=time(), $topJson);
    }


    public static function put_last_author($player, $topJson){


        $path = 'datas/private/forum/topics/'. $topJson->name .'.json';

        $topJson->last->author = $player->id;
        $topJson->last->time = time();

        $data = Json::encode($topJson);

        Json::write_json($path, $data);
    }


    public static function add_post_in_topic($name, $topJson){


        $path = 'datas/private/forum/topics/'. $topJson->name .'.json';

        $topJson->posts[] = (object) array('name'=>$name);

        $data = Json::encode($topJson);

        Json::write_json($path, $data);


        self::delete_views($topJson);
    }


    public static function add_topic_in_forum($name, $forumJson){


        $path = 'datas/private/forum/forums/'. $forumJson->name .'.json';

        $forumJson->topics[] = (object) array('name'=>$name);

        $data = Json::encode($forumJson);

        Json::write_json($path, $data);
    }


    public static function get_top_dest($topJson){


        $db = new Db();

        $sql = 'SELECT player_id FROM players_forum_missives WHERE name = ?';

        $res = $db->exe($sql, $topJson->name);

        $destTbl = array();

        while($row = $res->fetch_object()){


            $destTbl[] = $row->player_id;
        }

        return $destTbl;
    }


    public static function add_dest($dest, $topJson, $destTbl=false){


        if(!$destTbl){

            $destTbl = self::get_top_dest($topjson);
        }


        if(in_array($dest, $destTbl)){

            exit('error already in dest');
        }

        if(!is_numeric($dest)){

            exit('error dest');
        }

        $dest = new Player($dest);
        $dest->get_data();

        $player = new Player($_SESSION['playerId']);
        $player->get_data();

        if($dest->data->race != $player->data->race){

            exit('error dest forbidden');
        }


        $db = new Db();

        $values = array('player_id'=>$dest->id, 'name'=>$topJson->name);

        $db->insert('players_forum_missives', $values);
    }


    public static function remove_dest($dest, $topJson, $destTbl=false){


        if(!$destTbl){

            $destTbl = self::get_top_dest($topjson);
        }


        if(in_array($dest, $destTbl)){


            $dest = new Player($dest);

            $db = new Db();

            $values = array('player_id'=>$dest->id, 'name'=>$topJson->name);

            $db->delete('players_forum_missives', $values);
        }
    }
}
