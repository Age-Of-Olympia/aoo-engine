<?php


$ui = new Ui('Forum - Recherche');


$player = new Player($_SESSION['playerId']);

$player->get_data();


echo '<div><a href="forum.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a></div>';


if(!empty($_POST['keywords'])){


    $search = Forum::search($_POST['keywords']);
}


echo '
<h1>Rechercher dans les Forums</h1>
';

echo '<p>Insérez un ou plusieurs mot-clés (2 charactères min.) séparés par des espaces.</p>';

$keywords = (!empty($_POST['keywords'])) ? htmlentities($_POST['keywords']) : '';


echo '
<form method="POST" action="forum.php?search">
    <input style="width: 300px;" type="text" name="keywords" value="'. $keywords .'" /> <input type="submit" value="Chercher" />
</form>
';


if(!empty($_POST['keywords'])){


    echo '<p>Résultats:</p>';


    echo '
    <table border="1" class="marbre" align="center">
        ';

        foreach($search as $e){


            $postJson = json()->decode('forum/posts', $e);
            if(!$postJson)continue;

            $topJson = json()->decode('forum/topics', $postJson->top_id);
            if(!$topJson)continue;

            $forumJson = json()->decode('forum/forums', $topJson->forum_id);
            if(!$forumJson)continue;

            if($forumJson->category_id == 'RP' && !isset($topJson->approved)){

                continue;
            }


            if(!empty($forumJson->factions) && !in_array($player->data->faction, $forumJson->factions)){

                continue;
            }


            echo '
            <tr>

                <th><a href="forum.php?topic='. $topJson->name .'#'. $postJson->name .'">'. $topJson->title .'</a></th>

            </tr>
            ';

            $text = $postJson->text;

            foreach(explode(' ', $_POST['keywords']) as $w){


                $text = str_replace(' '. $w .' ', '<font color="red"> '. $w .' </font>', $text);
            }


            echo '
            <tr>

                <td align="left">'. explode("\n", $text)[0] .' <a href="forum.php?topic='. $topJson->name .'#'. $postJson->name .'">[...]</a></td>

            </tr>
            ';
        }


        echo '
    </table>
    ';
}
