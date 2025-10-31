<?php

namespace App\View\Forum;

use App\Service\PlayerService;
use App\Service\ForumService;
use Classes\Ui;
use Classes\Forum;
use Classes\Player;
use Classes\Str;
use App\View\InfosView;
use App\View\MenuView;

class LastPostsView
{
    public static function renderLastPosts(): void
    {
        $playerService = new PlayerService($_SESSION['playerId']);
        $player = $playerService->GetPlayer($_SESSION['playerId']);
        $player->get_data(false);
        ob_start();

        $ui = new Ui('Derniers Messages du Forum');

        InfosView::renderInfos($player);
        MenuView::renderMenu();

        echo <<<HTML
        <h1>Derniers Messages du Forum</h1>
        <div style="display: flex;    width: 500px;    margin: auto;    justify-content: end;">
            <button class="newTopic" onclick="markAllAsRead();" >Tout Marquer Comme `Lu`</button>
        </div>
       
        <table border="1" class="marbre" align="center" width="500" id="forum-last-posts">
            <tr>
                <th>Sujet</th>
                <th width="100px">Dernière</th>
            </tr>
        HTML;
        $forumService = new ForumService();
        $topicsHtml = array();
        $unreadTopics = $forumService->GetAllUnreadTopics($player);
        foreach ($unreadTopics as $topic) {
            $topJson = $topic["topicJson"];
            $forJson = $topic["forumJson"];

            $topicID = htmlentities($topJson->name);
            $topicTitle = htmlentities($topJson->title);
            $postTotal = count($topJson->posts);
            $pagesN = Forum::get_pages($postTotal);
            $author = $playerService->GetPlayer($topJson->last->author);
            $author->get_data(false);
            $date = date('d/m/Y', timestampNormalization($topJson->last->time));

            if ($date == date('d/m/Y', time())) {

                $date = 'Aujourd\'hui';
            } elseif ($date == date('d/m/Y', time() - 86400)) {

                $date = 'Hier';
            }
            $time = date('H:i', timestampNormalization($topJson->last->time));

            $currentTopicHtml = <<<HTML
                    <tr>
                        <td align="left">
                            <b><a href="forum.php?topic={$topicID}">{$topicTitle}</a></b>
                            <br>
                            <i>Dans {$forJson->name}</i>
                        </td>

                        <td style="white-space: nowrap; font-size: 88%;"align="right">
                            <a href="forum.php?topic={$topicID}&page={$pagesN}#{$topJson->last->time}">

                            Par {$author->data->name}
                            <br />
                            {$date}
                            <br />
                            à {$time}

                            </a>
                        </td>
                    </tr>
                    HTML;
            $topicsHtml[timestampNormalization($topJson->last->time)] =  $currentTopicHtml;
        }

        if (empty($topicsHtml)) {
            echo <<<HTML
            <tr>
                <td colspan="2" align="center">
                    Aucun topic non lu.
                </td>
            </tr>
            HTML;
        } else {
            krsort($topicsHtml);
            foreach ($topicsHtml as $currentTopicHtml) {
                echo $currentTopicHtml;
            }
        }
        echo '</table>';
?>
        <script>
            function markAllAsRead() {
                const url = 'api/forum/markAllAsRead.php';
                aooFetch(url, null, 'POST')
                    .then(autoModal)
                    .catch(autoError());
            }
        </script>
<?php
        echo Str::minify(ob_get_clean());
    }
}
