<?php

require_once('config.php');


include('scripts/infos.php');
include('scripts/menu.php');


function get_pages($postTotal){


    $pagesN = floor($postTotal / 5);

    if($postTotal > $pagesN*5){

        $pagesN++;
    }

    return $pagesN;
}


function refresh_last_posts(){

    // Répertoire de départ
    $directory = 'datas/private/forum/topics/'; // Remplacez par le répertoire souhaité

    // Récupère le fichier le plus récemment modifié
    $mostRecentFile = get_most_recent($directory);

    $topName = 'Aucun';

    if ($mostRecentFile !== null) {

        $topJson = json()->decode('forum', 'topics/'. $mostRecentFile);

        if(strlen($topJson->name) > 10){

            $topName = htmlentities(substr($topJson->name, 0, 10)) .'...';
        }

        else{

            $topName = htmlentities($topJson->name) .'';
        }


        $postJson = json()->decode('forum', 'posts/'. end($topJson->posts)->name);

        $author = new Player($postJson->author);
        $author->get_data();

        $pageN = get_pages($postTotal=count($topJson->posts));

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


function get_most_recent($dir) {

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


if(!isset($_SESSION['history'])){

    $_SESSION['history'] = array();
}


if(!empty($_GET['forum'])){


    include('scripts/forum/forum.php');

    exit();
}

elseif(!empty($_GET['topic'])){


    include('scripts/forum/topic.php');

    exit();
}

elseif(!empty($_GET['reply'])){


    include('scripts/forum/reply.php');

    exit();
}

elseif(!empty($_GET['newTopic'])){


    include('scripts/forum/newTopic.php');

    exit();
}


$ui = new Ui('Forum');


echo '<h1>Forums</h1>';


echo '
<table border="0" align="center" width="500">
    ';


    foreach(array('RP','Privés','HRP') as $cat){


        $catJson = json()->decode('forum', 'categories/'. $cat);


        echo '
        <tr>
            <th width="1%"></th>
            <th>'. $catJson->name .'</th>
            <th width="1%">Sujets</th>
        </tr>
        ';


        foreach($catJson->forums as $forum){


            $forJson = json()->decode('forum', 'forums/'. $forum->name);


            $img = $forJson->name;

            if($catJson->name == 'Privés'){


                if(!empty($forJson->factions)){


                    if(!in_array($player->data->faction, $forJson->factions)){

                        continue;
                    }
                }


                $img = 'Privés';
            }


            echo '
            <tr>
                ';

                echo '
                <td><img src="img/ui/forum/'. $img .'.png" width="50" /></td>
                ';

                echo '
                <td data-forum="'. $forJson->name .'">
                    ';


                    echo ''. $forJson->name .'';


                    echo '
                </td>
                ';

                echo '
                <td align="center">
                    ';


                    echo count($forJson->topics);


                    echo '
                </td>
                ';

                echo '
            </tr>
            ';
        }
    }


    echo '
</table>
';


?>
<script>
$(document).ready(function(e){

    $('td').click(function(e){

        document.location = 'forum.php?forum='+ $(this).data('forum');
    });
});
</script>
