<?php

function SearchInPosts($posts, $search)
{
    $search = strtolower($search);
    foreach($posts as $post)
    {
        $postJson = json()->decode('forum', 'posts/'. $post->name);
        if(strpos(strtolower($postJson->text),$search ) !== false)
        {
            return true;
        }
    }
    return false;
}

$forumJson = json()->decode('forum', 'forums/'. $_GET['forum']);


if(!$forumJson){

    exit('error forum');
}


$ui = new Ui($forumJson->name);


ob_start();


include('scripts/infos.php');
include('scripts/menu.php');


echo '<h1>'. $forumJson->name .'</h1>';


Forum::check_access($player, $forumJson->name);


echo '
<div style="position: relative; text-align: left;">

    <a href="forum.php">Forum ('. $forumJson->category_id .')</a> >
    <a href="forum.php?forum='. $forumJson->name .'">'. $forumJson->name .'</a>

    <button class="newTopic" style="position: absolute; right: 0px; top: -10px;" data-forum="'. $_GET['forum'] .'"><span class="ra ra-quill-ink"></span> Nouveau sujet</button>
</div>';


echo '
<table border="1" align="center" class="marbre">
    ';

    echo '
    <tr>
        <th>Sujet</th>
        <th width="1%">Réponses</th>
        <th width="1%">Dernière</th>
    </tr>
    ';

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

$topicsHtml=array();
$pinnedTopicsHtml=array();
    foreach($topicsTbl as $top){
        $shoulBeDisplayed = true;

        $topJson = json()->decode('forum', 'topics/'. $top->name);
        if(isset($_GET['search']))
        {
            $shoulBeDisplayed = false;
            if(strpos(strtolower($topJson->title), strtolower($_GET['search'])) !== false)
            {
                $shoulBeDisplayed = true;
            }
            else
            {
                $shoulBeDisplayed=SearchInPosts($topJson->posts, $_GET['search']);
            }
        }

        if(!$shoulBeDisplayed)continue;

        $views = (count($topicsViewsTbl)) ? $topicsViewsTbl[$top->name] : Forum::get_views($topJson);



        $author = new Player($topJson->author);

        $author->get_data();

        
        $currentTopicHtml= '
        <tr class="tr-forum">
            ';

        $currentTopicHtml.= '
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

                if(isset($topJson->pined) && $topJson->pined){

                   $topName= '<i class="ra ra-gavel"></i>'.$topName;
                }

                $currentTopicHtml.= implode(' ', $symbolsTbl) . $topName;


                $currentTopicHtml.= '
                <div><i>Par '. $author->data->name .'</i></div>
                ';


                $currentTopicHtml.= '
            </td>
            ';


            $currentTopicHtml.= '
            <td align="center">
                ';


                $currentTopicHtml.= count($topJson->posts);


                $currentTopicHtml.= '
            </td>
            ';


            $postTotal = count($topJson->posts);

            $pagesN = Forum::get_pages($postTotal);


            $currentTopicHtml.= '
            <td
                align="right"
                style="font-size: 88%;"
                data-topic="'. htmlentities($top->name) .'&page='. $pagesN .'#'. $topJson->last->time .'"
                >
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

                $currentTopicHtml.= '
                <div>
                    Par '. $lastAuthor->data->name .'
                    <div>
                        '. $date .'<br />
                        à '. date('H:i', $topJson->last->time) .'
                </div>
                ';


                $currentTopicHtml.= '
            </td>
            ';


            $currentTopicHtml.= '
        </tr>
        ';
        if(isset($topJson->pined) && $topJson->pined)
        {
            $pinnedTopicsHtml[$topJson->last->time]=  $currentTopicHtml;
        }
        else
        {
            $topicsHtml[$topJson->last->time]=  $currentTopicHtml;
        }
    }

    krsort($pinnedTopicsHtml);
    foreach($pinnedTopicsHtml as $currentTopicHtml){
        echo $currentTopicHtml;
    }
    //sort by last post time
    krsort($topicsHtml);
    foreach($topicsHtml as $currentTopicHtml){
        echo $currentTopicHtml;
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
<?php

echo Str::minify(ob_get_clean());
