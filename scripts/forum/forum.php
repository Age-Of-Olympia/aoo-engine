<?php


$forumJson = json()->decode('forum', 'forums/'. $_GET['forum']);


if(!$forumJson){

    exit('error forum');
}


$ui = new Ui($forumJson->name);


echo '<h1>'. $forumJson->name .'</h1>';


Forum::check_access($player, $forumJson->name);


echo '
<div style="position: relative; width: 500px; margin: 0 auto; text-align: left;">
    <a href="forum.php">Forum ('. $forumJson->category_id .')</a> >
    <a href="forum.php?forum='. $forumJson->name .'">'. $forumJson->name .'</a>

    <div style="position: absolute; right: 0; top: -10;"><button class="newTopic" data-forum="'. $_GET['forum'] .'"><span class="ra ra-quill-ink"></span> Nouveau sujet</button></div>
</div>';


echo '
<table border="1" align="center" class="marbre" width="500">
    ';

    echo '
    <tr>
        <th>Sujet</th>
        <th width="1%">Réponses</th>
        <th width="1%">Dernière</th>
    </tr>
    ';

    krsort($forumJson->topics);

    $topicsTbl = $forumJson->topics;


    $topicsViewsTbl = array();


    if($forumJson->name == 'Missives'){

        $topicsTbl = array();


        $db = new Db();

        $sql = 'SELECT name, viewed FROM players_forum_missives WHERE player_id = ?';

        $res = $db->exe($sql, $player->id);

        while($row = $res->fetch_object()){


            $topicsTbl[] = (object) array('name'=>$row->name);

            if($row->viewed){


                $topicsViewsTbl[$row->name] = array($player->id);
            }

            else{

                $topicsViewsTbl[$row->name] = array();
            }
        }
    }


    foreach($topicsTbl as $top){


        $topJson = json()->decode('forum', 'topics/'. $top->name);

        $views = (count($topicsViewsTbl)) ? $topicsViewsTbl[$top->name] : Forum::get_views($topJson);


        $author = new Player($topJson->author);

        $author->get_data();


        echo '
        <tr class="tr-forum">
            ';

            echo '
            <td
                data-topic="'. htmlentities($top->name) .'"

                align="left"
                >
                ';


                $symbolsTbl = array();

                $topName = htmlentities($topJson->title);


                if($forumJson->category_id == 'RP' && !isset($topJson->approved)){


                    $topName .= ' <i><font color="red">(en attente de traduction)</font></i>';
                }


                if(!in_array($player->id, $views)){

                    $symbolsTbl[] = '<span class="ra ra-quill-ink"></span>';

                    $topName = '<b>'. htmlentities($topJson->title) .'</b>';
                }


                echo implode(' ', $symbolsTbl) . $topName;


                echo '
                <div><i>Par '. $author->data->name .'</i></div>
                ';


                echo '
            </td>
            ';


            echo '
            <td align="center">
                ';


                echo count($topJson->posts);


                echo '
            </td>
            ';


            echo '
            <td align="right" style="font-size: 88%;">
                ';


                $lastAuthor = new Player($topJson->last->author);

                $lastAuthor->get_data();

                $date = date('d/m/Y', $topJson->last->time);

                if($date == date('d/m/Y', time())){

                    $date = 'Aujourd\'hui';
                }
                elseif($date == date('d/m/Y', time()-86400)){

                    $date = 'Hier';
                }

                echo '
                <div>
                    Par '. $lastAuthor->data->name .'
                    <div>
                        '. $date .'<br />
                        à '. date('H:i', $topJson->last->time) .'
                </div>
                ';


                echo '
            </td>
            ';


            echo '
        </tr>
        ';
    }


    echo '
</table>
';


echo '<div><button class="newTopic" data-forum="'. $_GET['forum'] .'"><span class="ra ra-quill-ink"></span> Nouveau sujet</button></div>';


?>
<script>
$(document).ready(function(e){

    $('td').click(function(e){

        document.location = 'forum.php?topic='+ $(this).data('topic');
    });

    $('.newTopic').click(function(e){

        document.location = 'forum.php?newTopic='+ $(this).data('forum');
    });
});
</script>
