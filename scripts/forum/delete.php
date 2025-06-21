<?php
use Classes\Forum;
use Classes\Json;
use Classes\Player;
use Classes\Str;
use Classes\Ui;

$postJson = json()->decode('forum', 'posts/'. $_GET['delete']);

if(!$postJson){

    exit('error post');
}

$topJson = json()->decode('forum', 'topics/'. $postJson->top_id);

$player = new Player($_SESSION['playerId']);

$player->get_data();

if(!$player->have_option('isSuperAdmin')){

    exit('cannot delete this post');
}



Forum::remove_post($_GET['delete'], $topJson);

if(isset($_GET['page'])){
    header("Location: forum.php?topic=". $topJson->name ."&page=".$_GET['page'] );
}else{
    header("Location: forum.php?topic=". $topJson->name );
}

