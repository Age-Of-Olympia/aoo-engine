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


    public static function give_reward($postJson, $reward){


        if(!isset($postJson->rewards)){

            $postJson->rewards = array();
        }


        $target = new Player($postJson->author);

        $target->put_pr($reward->pr);


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


    public static function refresh_last_posts($topicName){



        $topJson = json()->decode('forum', 'topics/'. $topicName);

        if(strlen($topJson->title) > 10){
            $topName = mb_substr($topJson->title, 0, 10);
            $topName = htmlentities($topName,ENT_HTML5, "UTF-8") .'...';
        }

        else{

            $topName = htmlentities($topJson->title,ENT_HTML5, "UTF-8") .'';
        }


        $postJson = json()->decode('forum', 'posts/'. end($topJson->posts)->name);

        $author = new Player($postJson->author);
        $author->get_data();

        $pageN = self::get_pages(count($topJson->posts));

        $topName = 'Dans <a href="forum.php?topic='. htmlentities($topJson->name) .'&page='. $pageN .'#'. $postJson->name .'">'. $topName .'</a> par '. $author->data->name;


        $lastPosts = json()->decode('forum', 'lastPosts');


        $forumJson = json()->decode('forum', 'forums/'. $topJson->forum_id);


        if(!empty($forumJson->factions)){


            foreach($forumJson->factions as $faction){


                if(!isset($lastPosts->$faction)){

                    $lastPosts->$faction = (object) array();
                }


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


        if(!isset($player->data)){

            $player->get_data();
        }

        if(!empty($forumJson->factions)){

            if(!in_array($player->data->faction, $forumJson->factions) && !in_array($player->data->secretFaction, $forumJson->factions)){

                exit('Accès refusé');
            }
        }
    }

    public static function put_topic($player, $forumJson, $title, $text){

        $topicId = round(microtime(true) * 1000);

        $path = 'datas/private/forum/topics/'. $topicId .'.json';

        if(file_exists($path)){
            exit('topic file already exists, please retry');
        }

        $data = (object) array(

            "name"=>$topicId,

            "title"=>$title,

            "author"=>$player->id,

            "forum_id"=>$forumJson->name,

            "last"=>(object) array("author"=>"", "time"=>0),

            "posts"=>array()
        );

        $data = Json::encode($data);

        Json::write_json($path, $data);


        $topJson = json()->decode('forum', 'topics/'. $topicId);


        // create post
        self::put_post($player, $topJson, $text);

        self::put_last_author($player, $topJson);


        return $topJson;
    }

    public static function put_post($player, $topJson, $text){

        $postId = round(microtime(true) * 1000);

        $path = 'datas/private/forum/posts/'. $postId .'.json';

        if(file_exists($path)){
            exit('post file already exists, please retry');
        }

        $data = (object) array(

            "name"=>$postId,

            "author"=>$player->id,

            "top_id"=>$topJson->name,

            "last_update_date"=>time(),

            "text"=>$text
        );

        $data = Json::encode($data);

        Json::write_json($path, $data);


        if($topJson->forum_id != 'Missives'){


            $player->put_pr(1);

            Forum::put_keywords($postId, $text);
        }


        self::add_post_in_topic($postId, $topJson);
        return $postId;
    }


    public static function put_reward($player){


        function draw_random_reward() {


            // Define the rewards and their weights
            $rewards = [1, 2, 3, 4];
            $weights = [4, 2, 1.33, 1];

            // Normalize the weights
            $total_weight = array_sum($weights);
            $normalized_weights = array_map(function($weight) use ($total_weight) {
                return $weight / $total_weight;
            }, $weights);

            // Create cumulative weights
            $cumulative_weights = [];
            $cumulative_sum = 0;
            foreach ($normalized_weights as $weight) {
                $cumulative_sum += $weight;
                $cumulative_weights[] = $cumulative_sum;
            }

            // Generate a random number between 0 and 1
            $rand = mt_rand() / mt_getrandmax();

            // Find the reward corresponding to the random number
            foreach ($cumulative_weights as $index => $cumulative_weight) {
                if ($rand < $cumulative_weight) {
                    return $rewards[$index];
                }
            }

            // Fallback return (should not be reached)
            return end($rewards);
        }


        $reward = draw_random_reward();

        $path = 'img/ui/forum/rewards/';

        $directory = File::get_random_directory($path);

        $url = $path .'/'. $directory .'/'. $reward .'.png';


        $values = array(
            'from_player_id'=>$player->id,
            'to_player_id'=>$player->id,
            'img'=>$url,
            'pr'=>$reward
        );

        $db = new Db();

        $db->insert('players_forum_rewards', $values);
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


        $postJson = json()->decode('forum/posts', $name);


        $topJson->last = (object) array("author"=>$postJson->author, "time"=>$name);


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


        if(is_numeric($dest)){
            $dest = new Player($dest);
        }else{
            $dest = Player::get_player_by_name($dest);
        }
        
        $dest->get_data();

        if(!$destTbl){

            $destTbl = self::get_top_dest($topJson);
        }


        if(in_array($dest->id, $destTbl)){

            return 'error already in dest';
        }


        $player = new Player($_SESSION['playerId']);

        $player->get_data();


        if(
            !$player->check_missive_permission($dest)
        ){

            return 'error dest forbidden';

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


    public static function put_autosave($playerId, $text){


        if(!is_numeric($playerId)){

            $playerId = $playerId->id;
        }

        $myfile = fopen('datas/private/players/'. $playerId .'.save', "w") or die("Unable to open file!");
        fwrite($myfile, htmlentities($text));
        fclose($myfile);
    }


    public static function get_autosave($playerId) : string{


        if(!is_numeric($playerId)){

            $playerId = $playerId->id;
        }


        $file = 'datas/private/players/'. $playerId .'.save';

        if(file_exists($file)){

            return file_get_contents($file);
        }

        else{

            return '';
        }
    }


    public static function extract_keywords($content) {


        // Convertir en minuscule et retirer la ponctuation
        $content = strtolower($content);
        $content = preg_replace('/[^\p{L}\p{N}\s]/u', '', $content);
        $content = preg_replace('/[\r\n]+/', ' ', $content); // Remplacer les sauts de ligne par des espaces


        // Séparer les mots et retirer les doublons
        $words = array_unique(explode(' ', $content));

        // Liste de mots vides (stop words) exhaustive
        $stopWords = [
            'au', 'aux', 'avec', 'ce', 'ces', 'dans', 'de', 'des', 'du', 'elle', 'en', 'et', 'eux', 'il', 'je', 'la', 'le',
            'leur', 'lui', 'ma', 'mais', 'me', 'même', 'mes', 'moi', 'mon', 'ne', 'nos', 'notre', 'nous', 'on', 'ou', 'par',
            'pas', 'pour', 'qu', 'que', 'qui', 'sa', 'se', 'ses', 'son', 'sur', 'ta', 'te', 'tes', 'toi', 'ton', 'tu', 'un',
            'une', 'vos', 'votre', 'vous', 'c\'', 'd\'', 'j\'', 'l\'', 'à', 'm\'', 'n\'', 's\'', 't\'', 'y', 'été', 'étée',
            'étées', 'étés', 'étant', 'suis', 'es', 'est', 'sommes', 'êtes', 'sont', 'serai', 'seras', 'sera', 'serons', 'serez',
            'seront', 'serais', 'serait', 'serions', 'seriez', 'seraient', 'étais', 'était', 'étions', 'étiez', 'étaient', 'fus',
            'fut', 'fûmes', 'fûtes', 'furent', 'sois', 'soit', 'soyons', 'soyez', 'soient', 'fusse', 'fusses', 'fût', 'fussions',
            'fussiez', 'fussent', 'ayant', 'eu', 'eue', 'eues', 'eus', 'ai', 'as', 'avons', 'avez', 'ont', 'aurai', 'auras',
            'aura', 'aurons', 'aurez', 'auront', 'aurais', 'aurait', 'aurions', 'auriez', 'auraient', 'avais', 'avait', 'avions',
            'aviez', 'avaient', 'eut', 'eûmes', 'eûtes', 'eurent', 'aie', 'aies', 'ait', 'ayons', 'ayez', 'aient', 'eusse',
            'eusses', 'eût', 'eussions', 'eussiez', 'eussent', 'ceci', 'cela', 'celà', 'cet', 'cette', 'ici', 'ils', 'les',
            'leurs', 'quel', 'quels', 'quelle', 'quelles', 'sans', 'soi'
        ];


        // Retirer les mots vides (stop words) et les mots ne contenant qu'une lettre ou un chiffre
        $keywords = array_filter($words, function($word) use ($stopWords) {
            return !in_array($word, $stopWords) && mb_strlen($word) > 1;
        });

        return $keywords;
    }


    public static function put_keywords($postName, $text, $deleteBefore=false){


        $db = new Db();


        if($deleteBefore){


            // edit : delte before
            $values = array('postName'=>$postName);

            $db->delete('forums_keywords', $values);
        }


        // Extraire les mots-clés du message
        $keywords = self::extract_keywords($text);


        foreach ($keywords as $e) {


            $values = array(
                'name'=>$e,
                'postName'=>$postName
            );

            $db->insert('forums_keywords', $values);
        }
    }


    public static function search($keywords) : array {


        $return = array();

        $values = explode(' ', $keywords);


        foreach($values as $e){

            if(strlen($e) < 2){

                exit('Vos mot-clés doivent être formés d\'au moins 2 charactères.');
            }
        }

        $db = new Db();

        $sql = '
        SELECT
        postName
        FROM
        forums_keywords
        WHERE
        name IN('. Db::print_in($values) .')
        GROUP BY
        postName
        LIMIT 10
        ';

        $res = $db->exe($sql, $values);

        while($row = $res->fetch_object()){


            $return[] = $row->postName;
        }

        return $return;
    }
}
