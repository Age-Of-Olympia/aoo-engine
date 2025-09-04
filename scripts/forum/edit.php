<?php
use Classes\Forum;
use Classes\Json;
use Classes\Player;
use Classes\Str;
use Classes\Ui;
use App\View\InfosView;
use App\View\MenuView;

$postJson = json()->decode('forum', 'posts/'. $_GET['edit']);


if(!$postJson){

    exit('error post');
}


$topJson = json()->decode('forum', 'topics/'. $postJson->top_id);

$forumJson = json()->decode('forum', 'forums/'. $topJson->forum_id);

$player = new Player($_SESSION['playerId']);

$player->get_data();

Forum::check_access($player, $forumJson);

if(!$player->have_option('isAdmin') && ($postJson->author != $player->id || !empty($topJson->closed))){

    exit('cannot edit this post');
}

if(!empty($_POST['text'])){

    $postJson->text = $_POST['text'];

    $postJson->last_update_date = time();

    if($topJson->forum_id != 'Missives'){
        Forum::put_keywords($postJson->name, $_POST['text'], deleteBefore:true);
    }


    $data = Json::encode($postJson);

    Json::write_json('datas/private/forum/posts/'. $postJson->name .'.json', $data);


    echo $postJson->name;


    exit();
}


$ui = new Ui('Éditer un message');


ob_start();


InfosView::renderInfos();
MenuView::renderMenu();

echo '<h1>'. $topJson->title .'</h1>';





echo '<h2>Éditer</h2>';


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

    echo '>'. $postJson->text .'</textarea>';

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


echo '<div><button class="submit" data-post="'. $postJson->name .'">Envoyer</button></div>';


include('scripts/forum/upload_module.php');


// search post position
$postN = count($topJson->posts);

foreach($topJson->posts as $e){

    if($e->name != $postJson->name){

        continue;
    }

    $postN --;
}


$pagesN = Forum::get_pages($postN);


echo '
<iframe
    id="older-iframe"
    src="forum.php?topic='. $topJson->name .'&page='. $pagesN .'&hideMenu=1#'. $postJson->name .'"
></iframe>
';

echo Str::minify(ob_get_clean());

?>
<script src="js/autosave.js?20241016"></script>
<script>
window.topId = <?php echo $postJson->top_id ?>;
window.pagesN = <?php echo $pagesN ?>;
</script>
<script src="js/forum_edit.js"></script>
