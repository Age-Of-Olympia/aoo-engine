<?php
use Classes\Player;
use Classes\Forum;
use Classes\Ui;
use Classes\Str;
use Classes\Json;
use Classes\Db;

$topJson = json()->decode('forum', 'topics/'. $_GET['reply']);


if(!$topJson){

    exit('error top');
}


$forumJson = json()->decode('forum', 'forums/'. $topJson->forum_id);

$player = new Player($_SESSION['playerId']);

$player->get_data();

Forum::check_access($player, $forumJson);

if(!empty($_POST['text'])){

    if($_POST['currentSessionId'] != $_SESSION['playerId']){

        ExitError('error session swich');
    }
    if(isset($topJson->closed) && $topJson->closed && !$player->have_option('isAdmin')){

        ExitError('error topic is closed only admin can post');
    }

    if($forumJson->category_id == 'RP' && !isset($topJson->approved)){


        if(!$player->have_option('isAdmin')){

            ExitError('error topic must be approved by admin');
        }


        // approve this topic
        $topJson->approved = 1;

        $topData = Json::encode($topJson);
        Json::write_json('datas/private/forum/topics/'. $topJson->name .'.json', $topData);
    }


    // create post
    $replyId = Forum::put_post($player, $topJson, $_POST['text']);


    // delete missives views
    if($topJson->forum_id == 'Missives'){

        $sql = 'UPDATE players_forum_missives SET viewed = 0 WHERE name = ?';

        $db = new Db();

        $db->exe($sql,$topJson->name);
    }
    else{


        Forum::put_keywords(time(), $_POST['text']);

        Forum::refresh_last_posts($topJson->name);
    }


    // delete autosave
    $file = 'datas/private/players/'. $player->id .'.save';
    if (file_exists($file)) {
        unlink($file); // Delete the file
    }


    ExitSuccess($replyId);
}


$ui = new Ui('Répondre');

ob_start();

include('scripts/infos.php');
include('scripts/menu.php');


echo '<h1>'. $topJson->title .'</h1>';


echo '<h2>Répondre</h2>';


$autoSave = Forum::get_autosave($player);


echo '
<div id="forum-textarea">
';

include('scripts/forum/tools.php');
echo '<div id="currentSessionId" style="display:none;">'.$_SESSION['playerId'].'</div>';
echo '
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


$postTotal = count($topJson->posts);

$pagesN = Forum::get_pages($postTotal);


echo '
<iframe
    id="older-iframe"
    src="forum.php?topic='. $topJson->name .'&page='. $pagesN .'&hideMenu=1#last"
></iframe>
';


$nextPagesN = Forum::get_pages($postTotal+1);


echo Str::minify(ob_get_clean());

?>
<script src="js/autosave.js?20241016"></script>
<script>
window.pagesN = <?php echo $nextPagesN ?>;
</script>
<script src="js/forum_reply.js?20241016"></script>
