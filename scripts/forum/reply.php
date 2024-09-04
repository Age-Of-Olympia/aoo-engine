<?php


$topJson = json()->decode('forum', 'topics/'. $_GET['reply']);


if(!$topJson){

    exit('error top');
}


$forumJson = json()->decode('forum', 'forums/'. $topJson->forum_id);


if(!empty($_POST['text'])){


    $player = new Player($_SESSION['playerId']);

    $player->get_data();

    Forum::check_access($player, $forumJson);

    if(isset($topJson->closed) && $topJson->closed && !$player->have_option('isAdmin')){

        exit('error topic is closed only admin can post');
    }

    if($forumJson->category_id == 'RP' && !isset($topJson->approved)){


        if(!$player->have_option('isAdmin')){

            exit('error topic must be approved by admin');
        }


        // approve this topic
        $topJson->approved = 1;

        $topData = Json::encode($topJson);
        Json::write_json('datas/private/forum/topics/'. $topJson->name .'.json', $topData);
    }


    // create post
    Forum::put_post($player, $topJson, $_POST['text']);


    // delete missives views
    if($topJson->forum_id == 'Missives'){

        $sql = 'UPDATE players_forum_missives SET viewed = 0 WHERE name = ?';

        $db = new Db();

        $db->exe($sql,$topJson->name);
    }
    else{


        Forum::put_keywords(time(), $_POST['text']);

        Forum::refresh_last_posts();
    }


    // delete autosave
    @unlink('datas/private/players/'. $player->id .'.save');


    echo time();


    exit();
}


$ui = new Ui('Répondre');

ob_start();

include('scripts/infos.php');
include('scripts/menu.php');


echo '<h1>'. $topJson->title .'</h1>';

$player->get_data();

Forum::check_access($player, $topJson);


echo '<h2>Répondre</h2>';


$autoSave = Forum::get_autosave($player);


echo '
<div>
<textarea
    class="box-shadow tr-topic1"
    style="width: 100%;"

    rows="20"
    ';

    echo Str::minify(ob_get_clean());

    echo '>'. $autoSave .'</textarea>';

    ob_start();

    echo '
</div>
';


echo '
<div class="forum-bottom-textarea">
    <a href="#bottom" id="upload">Image</a>
    <a href="#" id="delete">Effacer</a>
    <a href="#bottom" id="add-rows">Agrandir</a>
</div>
';


echo '<div><button class="submit" data-topic="'. $topJson->name .'">Envoyer</button></div>';


include('scripts/forum/upload_module.php');


$postTotal = count($topJson->posts)+1;

$pagesN = Forum::get_pages($postTotal);


echo '
<iframe
    id="older-iframe"
    src="forum.php?topic='. $topJson->name .'&page='. $pagesN .'&hideMenu=1#end"
></iframe>
';

echo Str::minify(ob_get_clean());

?>
<script src="js/autosave.js"></script>
<script>
window.pagesN = <?php echo $pagesN ?>;
</script>
<script src="js/forum_reply.js"></script>
