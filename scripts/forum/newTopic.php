<?php


$forumJson = json()->decode('forum', 'forums/'. $_GET['newTopic']);


if(!$forumJson){

    exit('error forum');
}

if(!empty($_POST['text']) && !empty($_POST['name'])){


    $player = new Player($_SESSION['playerId']);

    $player->get_data();

    Forum::check_access($player, $forumJson);


    // create topic
    $topJson = Forum::put_topic($player, $forumJson, $title=$_POST['name'], $_POST['text']);


    // missives
    if($topJson->forum_id == 'Missives'){


        // add missive in db

        $db = new Db();

        $values = array('player_id'=>$player->id, 'name'=>time());

        $db->insert('players_forum_missives', $values);


        if(!empty($_POST['destId'])){


            $target = new Player($_POST['destId']);

            if($player->check_missive_permission($target)){


                $values = array(
                    'player_id'=>$target->id,
                    'name'=>$topJson->name
                );

                $db->insert('players_forum_missives', $values);
            }
        }
    }

    else{


        // edit forum
        Forum::add_topic_in_forum($topJson->name, $forumJson);

        Forum::refresh_last_posts();
    }


    echo time();


    exit();
}


$ui = new Ui('Nouveau sujet');


ob_start();


include('scripts/infos.php');
include('scripts/menu.php');


echo '<h1>'. $forumJson->name .'</h1>';


if(!empty($forumJson->factions)){


    if(!in_array($player->data->faction, $forumJson->factions) && !in_array($player->data->secretFaction, $forumJson->factions)){

        exit('Accès refusé');
    }
}


if($forumJson->name == 'Missives'){


    $title = '<h2>Nouvelle Missive</h2>';

    if(!empty($_GET['targetId'])){


        $target = new Player($_GET['targetId']);

        if($player->check_missive_permission($target)){


            $title = '<h2>Nouvelle Missive à '. $target->data->name .'</h2>';

            echo '<input type="hidden" id="dest" value="'. $target->id .'" />';
        }
    }

    echo $title;
}

else{

    echo '<h2>Nouveau Sujet</h2>';
}


$autosave = Forum::get_autosave($player);


$msg = 'Message';

if($autosave != ''){

    $msg = $autosave;
}


echo '
<div>
<input
    type="text"
    class="name tr-topic2"
    style="width: 100%"
    value="Titre du sujet"
    />
<br />
<textarea
    class="box-shadow tr-topic1"
     style="width: 100%"
    rows="20"
    ';

    echo Str::minify(ob_get_clean());

    echo '>'. $msg .'</textarea>';

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


echo '<div><button class="submit" data-forum="'. $forumJson->name .'">Envoyer</button></div>';


include('scripts/forum/upload_module.php');


echo Str::minify(ob_get_clean());


?>
<script src="js/autosave.js"></script>
<script src="js/forum_newTopic.js"></script>
