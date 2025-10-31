<?php

namespace App\View\Forum;

use App\Service\PlayerService;
use Classes\Db;
use Classes\Forum;
use Classes\Str;
use Classes\Ui;
use App\View\InfosView;
use App\View\MenuView;

class ForumView
{
    public static function renderForum(): void
    {
        $playerService = new PlayerService($_SESSION['playerId']);
        $player = $playerService->GetPlayer($_SESSION['playerId']);
        $player->get_data(false);
        $forumJson = json()->decode('forum', 'forums/' . $_GET['forum']);


        if (!$forumJson) {

            exit('error forum');
        }


        $ui = new Ui($forumJson->name);


        ob_start();




        InfosView::renderInfos($player);
        MenuView::renderMenu();

        echo '<h1>' . $forumJson->name . '</h1>';


        Forum::check_access($player, $forumJson);


        echo '
<div style="position: relative; text-align: left;">

    <a href="forum.php">Forum (' . $forumJson->category_id . ')</a> >
    <a href="forum.php?forum=' . $forumJson->name . '">' . $forumJson->name . '</a>

    <a href="forum.php?newTopic=' . $_GET['forum'] . '" style="position: absolute; right: 0px; top: -10px;"><button class="newTopic" ><span class="ra ra-quill-ink"></span> Nouveau sujet</button></a>
</div>';


        echo '
<table border="1" align="center" class="marbre">
    ';

        echo '
    <tr>
        <th>Sujet</th>
        <th width="1%">Réponses</th>
        <th width="100px">Dernière</th>
    </tr>
    ';

        $topicsTbl = $forumJson->topics;


        $topicsViewsTbl = array();


        if ($forumJson->name == 'Missives') {

            if ($player->id > 0 && ($player->id != $_SESSION['originalPlayerId'])) {
                exit('Accès refusé');
            }

            $topicsTbl = array();


            $db = new Db();

            $sql = 'SELECT name, viewed, last_post FROM players_forum_missives WHERE player_id = ?';

            $res = $db->exe($sql, $player->id);

            while ($row = $res->fetch_object()) {


                $topicsTbl[] = (object) array('name' => $row->name);

                $lastPostCompat=$row->last_post;
                if($row->viewed && $row->last_post==0)
                {
                    $lastPostCompat=-1; // we can detect migration case
                }
               
                $topicsViewsTbl[$row->name] = (object)array($player->id=>$lastPostCompat);
                
            }
        }

        $topicsHtml = array();
        $pinnedTopicsHtml = array();
        foreach ($topicsTbl as $top) {
            $shoulBeDisplayed = true;

            $topJson = json()->decode('forum', 'topics/' . $top->name);
            if (isset($_GET['search'])) {
                $shoulBeDisplayed = false;
                if (strpos(strtolower($topJson->title), strtolower($_GET['search'])) !== false) {
                    $shoulBeDisplayed = true;
                } else {
                    $shoulBeDisplayed = self::SearchInPosts($topJson->posts, $_GET['search']);
                }
            }

            if (!$shoulBeDisplayed) continue;

            $views = (count($topicsViewsTbl)) ? $topicsViewsTbl[$top->name] : Forum::get_views($topJson);

            if (!$topJson) {
                continue;
            }

            $author = $playerService->GetPlayer($topJson->author);

            $author->get_data(false);
            $topicID =htmlentities($top->name);
            $postTotal = count($topJson->posts);
            $lastPost = $topJson->last->time;
            $lastReadedPost =ForumView::GetLastReadedPost($player->id, $lastPost, $views);
            $isTopicUnread = $lastReadedPost < $lastPost;

            $nextUnreadedpageLink="";
            if ($isTopicUnread && $lastReadedPost > 0) {
                $nextPostData = ForumView::GetNextPostData($lastReadedPost, $topJson);
                $nextUnreadedpageLink="&page={$nextPostData['page']}#{$nextPostData['post']}";
            }

            $pagesN = Forum::get_pages($postTotal);
            $lastPageLink = "&page={$pagesN}#{$lastPost}";
            $pageLink=$isTopicUnread ? $nextUnreadedpageLink : $lastPageLink;
            //title and author
            {

                $topName = htmlentities($topJson->title);
                $extra = '';

                if ($isTopicUnread) {
                    $topName = '<span class="ra ra-quill-ink"></span><b>' . htmlentities($topJson->title) . '</b>';
                }
                if ($forumJson->category_id == 'RP' && !isset($topJson->approved)) {
                    $extra .= ' <i><font color="red">(en attente de traduction)</font></i>';
                }

                if (isset($topJson->pined) && $topJson->pined) {

                    $topName = '<i class="ra ra-gavel"></i>' . $topName;
                }

                $currentTopicHtml =<<<HTML
            <tr class="tr-forum">
                <td align="left">
                    <a href="forum.php?topic={$topicID}{$pageLink}">
                        <div>{$topName}</div>
                        {$extra}
                        <i>Par {$author->data->name}</i>
                    </a>
                </td>
            HTML;
            }
            //reply count
            {
                $currentTopicHtml .=<<<HTML
                <td align="center">
                    <a href="forum.php?topic={$topicID}{$pageLink}">
                        {$postTotal}
                    </a>
                </td>
                HTML;
            }



            //last post
            {
                $lastAuthor = $playerService->GetPlayer($topJson->last->author);

                $lastAuthor->get_data(false);

                $date = date('d/m/Y', timestampNormalization($topJson->last->time));
                $time =date('H:i', timestampNormalization($topJson->last->time));
                if ($date == date('d/m/Y', time())) {

                    $date = 'Aujourd\'hui';
                } elseif ($date == date('d/m/Y', time() - 86400)) {

                    $date = 'Hier';
                }


                $currentTopicHtml .= <<<HTML
                    <td align="right" style="font-size: 88%;">
                        <a href="forum.php?topic={$topicID}{$lastPageLink}">
                            <span>Par  {$lastAuthor->data->name}</span>
                            <div>
                                {$date }<br />
                                à {$time}
                            </div>
                        </a>
                    </td> 
                </tr>
                HTML;
            }

            if (isset($topJson->pined) && $topJson->pined) {
                $pinnedTopicsHtml[timestampNormalization($topJson->last->time)] =  $currentTopicHtml;
            } else {
                $topicsHtml[timestampNormalization($topJson->last->time)] =  $currentTopicHtml;
            }
        }

        krsort($pinnedTopicsHtml);
        foreach ($pinnedTopicsHtml as $currentTopicHtml) {
            echo $currentTopicHtml;
        }
        //sort by last post time
        krsort($topicsHtml);
        foreach ($topicsHtml as $currentTopicHtml) {
            echo $currentTopicHtml;
        }

        echo '
</table>
';


        echo '<div><a href="forum.php?newTopic=' . $_GET['forum'] . '"><button class="newTopic" ><span class="ra ra-quill-ink"></span> Nouveau sujet</button></a></div>';

        echo Str::minify(ob_get_clean());
    }
    public static function SearchInPosts($posts, $search)
    {
        $search = strtolower($search);
        foreach ($posts as $post) {
            $postJson = json()->decode('forum', 'posts/' . $post->name);
            if (strpos(strtolower($postJson->text), $search) !== false) {
                return true;
            }
        }
        return false;
    }

   
    public static function GetLastReadedPost(int $playerId,int $lastPost, $TopicView): int
    {
        if(is_array($TopicView) && array_is_list($TopicView))
        {
            return in_array($playerId, $TopicView) ? $lastPost : -1;
        }
        else
        {
            if(isset($TopicView->$playerId))
            {
               return $TopicView->$playerId < 0 ? $lastPost : $TopicView->$playerId;
            }
            return -1;
        }
    }
     public static function GetNextPostData(int $postId, $data): array
     {
        $posts = $data->posts;
        $index = array_search($postId, array_column($posts, 'name'));
        if(isset($posts[$index+1]))
        {
           $index++;
        }
        if ($index === false) {
            $index= 0; // Post not found, return first page as default
        }
        $nextPostId= $posts[$index]->name;
        return array('post'=>$nextPostId, 'page'=> (int)floor(($index) / Forum::$PostsPerPage) + 1);
     }
}