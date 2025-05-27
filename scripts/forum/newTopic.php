<?php


$forumJson = json()->decode('forum', 'forums/'. $_GET['newTopic']);


if(!$forumJson){

    ExitError('error forum');
}

if(!empty($_POST['text']) && !empty($_POST['name'])){

    if($_POST['currentSessionId'] != $_SESSION['playerId']){

        ExitError('error session swich');
    }

    $player = new Player($_SESSION['playerId']);

    $player->get_data();

    Forum::check_access($player, $forumJson);


    // create topic
    $topJson = Forum::put_topic($player, $forumJson, title:$_POST['name'], text:$_POST['text']);


    // missives
    if($topJson->forum_id == 'Missives'){


        // add missive in db

        $db = new Db();

        $values = array('player_id'=>$player->id, 'name'=>$topJson->name);

        $db->insert('players_forum_missives', $values);


        if(!empty($_POST['destId'])){

            $destTbl = Forum::get_top_dest($topJson);
            $desti = new Player($_POST['destId']);
            $desti->get_data();
            if($player->check_missive_permission($desti)){

                Forum::add_dest( $player,$desti, $topJson, $destTbl) ;
                //Ajouter l'animateur si la faction est différente
                if($player->data->faction != $desti->data->faction 
                    &&
                        (($player->data->secretFaction == "") ||
                         ($player->data->secretFaction != "" && $player->data->secretFaction != $desti->data->secretFaction))){
                    $raceJson = json()->decode('races', $desti->data->race);
                    Forum::add_dest($player,$raceJson->animateur, $topJson, $destTbl);    
                }
            }
        }
    }

    else{


        // edit forum
        Forum::add_topic_in_forum($topJson->name, $forumJson);

        Forum::refresh_last_posts($topJson->name);
    }


    ExitSuccess($topJson->name);
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
        else{

            echo '<font color="red">Vous ne pouvez pas envoyer directement de message au personnage '. $target->data->name .', car celui-ci ne fait pas parti de votre Faction.</font>';
            echo '<br />';
            echo '<span style="font-size: 88%;">Demandez à l\'Animateur de sa Faction de superviser votre échange.</span>';
        }
    }
    else{



        echo '<font color="blue">Rédigez, puis envoyez votre Missive.<br /> Après cela, vous pourrez ajouter des destinataires.</font>';
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

echo '<div id="currentSessionId" style="display:none;">'.$_SESSION['playerId'].'</div>';
echo '
<div id="forum-textarea">
';

include('scripts/forum/tools.php');

echo '
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
<script src="js/autosave.js?20241016"></script>
<script src="js/forum_newTopic.js?20241016"></script>
