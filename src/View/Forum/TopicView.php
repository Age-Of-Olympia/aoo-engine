<?php

namespace App\View\Forum;

use Classes\Forum;
use Classes\Db;
use Classes\Ui;
use Classes\bbcode;
use Classes\Str;
use App\Service\PlayerService;
use App\View\InfosView;
use App\View\MenuView;
use App\View\Forum\CookieView;


class TopicView
{
    public static function renderTopic(): void
    {
        $playerService = new PlayerService($_SESSION['playerId']);
        $topJson = json()->decode('forum', 'topics/' . $_GET['topic']);


        if (!$topJson) {

            exit('error top');
        }


        $ui = new Ui(htmlentities($topJson->title), true);


        ob_start();

    
        $player = $playerService->GetPlayer($_SESSION['playerId']);
        $player->get_data(false);
        echo '<div id="elebata"><a href="#"><img src="img/ui/forum/up.webp" /></a><br /><a href="#last"><img src="img/ui/forum/down.webp" /></a></div>';


        if (!isset($_GET['hideMenu'])) {

            InfosView::renderInfos($player);
            MenuView::renderMenu();
        }


        $postMin = 0;
        $postMax = 4;
        $page = 1;

        $postTotal = count($topJson->posts);

        $pagesN = Forum::get_pages($postTotal);


        if (!empty($_GET['page'])) {


            if (!is_numeric($_GET['page']) || $_GET['page'] < 1 || $_GET['page'] > $pagesN) {

                exit('error page');
            }


            $page = $_GET['page'];

            $postMin += ($_GET['page'] - 1) * 5;
            $postMax += ($_GET['page'] - 1) * 5;
        }

        $forumJson = json()->decode('forum', 'forums/' . $topJson->forum_id);

        Forum::check_access($player, $forumJson);

        if (!isset($_GET['hideMenu'])) {

            echo '<h1>' . htmlentities($topJson->title) . '</h1>';
        }

        if ($topJson->forum_id == 'Missives') {

           MissiveView::renderMissive($topJson, $player, $playerService);
        }


        if (Forum::put_view($topJson) && $topJson->forum_id == 'Missives') {


            // put viewed in db

            $sql = 'UPDATE players_forum_missives SET viewed = 1 WHERE player_id = ? AND name = ?';

            $db = new Db();

            $db->exe($sql, array($player->id, $topJson->name));
        }


        if (!isset($_GET['hideMenu'])) {

            echo '
    <div>
        <a href="forum.php">Forum (' . $forumJson->category_id . ')</a> >
        <a href="forum.php?forum=' . $topJson->forum_id . '">' . $topJson->forum_id . '</a> >
        <a href="forum.php?topic=' . htmlentities($topJson->name) . '">' . htmlentities($topJson->title) . '</a>
    </div>';
        }


        echo '
<table border="0" align="center" class="marbre box-shadow" cellspacing="0" style="width: 100%">
    ';


        foreach ($topJson->posts as $postN => $post) {


            if ($postN < $postMin) {

                continue;
            }


            $postJson = json()->decode('forum', 'posts/' . $post->name);


            echo '
        <tr class="tr-topic2 box-shadow">
            ';

            echo '
            <td
                width="50"
                >
                ';


            $author = $playerService->GetPlayer($postJson->author);

            $author->get_data(false);

            echo '<a href="infos.php?targetId=' . $author->id . '"><img class="box-shadow" src="' . $author->data->mini . '" width="50" /></a>';


            echo '
            </td>
            ';


            echo '
            <td
                align="left"
                valign="top"

                style="position: relative"
                >
                ';

            echo '<div><a href="infos.php?targetId=' . $author->id . '">' . $author->data->name . '</a>, mat.' . $author->id . '</div>';


            $raceJson = json()->decode('races', $author->data->race);
            $pnjText = $author->id < 0 ? ' - PNJ' : '';

            echo '<div style="font-size: 88%;"><i>' . $raceJson->name . $pnjText . ' - <a href="infos.php?targetId=' . $author->id . '&reputation">' . Str::get_reput(floor($author->data->pr/COEFFICIENT_PR)) . '</a> Rang ' . $author->data->rank . '</i></div>';



            if (isset($forumJson->factions) && in_array($author->data->secretFaction, $forumJson->factions)) {
                $factionJson = json()->decode('factions', $author->data->secretFaction);
                echo '<div style="font-size: 88%;"><a href="faction.php?faction=' . $author->data->secretFaction . '">' . $factionJson->name . '</a> <span style="font-size: 1.3em" class="ra ' . $factionJson->raFont . '"></span> (<i>' . $factionJson->role[$author->data->secretFactionRole]->name . '</i>)</div>';
            } else {

                $factionJson = json()->decode('factions', $author->data->faction);

                echo '<div style="font-size: 88%;"><a href="faction.php?faction=' . $author->data->faction . '">' . $factionJson->name . '</a> <span style="font-size: 1.3em" class="ra ' . $factionJson->raFont . '"></span> (<i>' . $factionJson->role[$author->data->factionRole]->name . '</i>)</div>';
            }


            $date = date('d/m/Y', timestampNormalization($postJson->name));

            if ($date == date('d/m/Y', time())) {

                $date = 'Aujourd\'hui';
            } elseif ($date == date('d/m/Y', time() - 86400)) {

                $date = 'Hier';
            }


            echo '
                <div
                    id="' . $postJson->name . '"

                    style="position: absolute; right: 0; top: 0; text-align: right"
                    >
                        <a href="forum.php?topic=' . htmlentities($_GET['topic']) . '&page=' . $page . '#' . $postJson->name . '">#' . $postN + 1 . '</a>
                        <br />
                        <span style="color: grey; font-size: 75%;">' . $date . '<br />' . date('H:i', timestampNormalization($postJson->name)) . '</span>
                    </div>';


            echo '
            </td>
            ';


            echo '
        </tr>
        ';

            echo '
        <tr class="tr-topic1">
            ';

            echo '
            <td
                data-post="' . $post->name . '"

                colspan="2"

                align="left"
                valign="top"
                >
                ';


            $text = $postJson->text;

            if ($forumJson->category_id == 'RP' && !isset($topJson->approved)) {


                $player = $playerService->GetPlayer($_SESSION['playerId']);

                if (!$player->have_option('isAdmin')) {


                    $text = '[i]Texte dans une langue qui vous est inconnue.[/i]

                        hrp: ce texte est en attente de l\'approbation d\'un modérateur.';
                }
            }


            $bbcode = new bbcode();

            echo '<div style="padding: 10px;">' . $bbcode->render(htmlentities($text)) . '</div>';
            echo '
                <div class="post-rewards-container">';
               
                echo '
                    <div class="post-rewards">

                        ';

            //recompenses
            if (!empty($postJson->rewards)) {


                foreach ($postJson->rewards as $e) {


                    $giver = $playerService->GetPlayer($e->player_id);
                    $giver->get_data(false);


                    echo '<img src="' . $e->img . '" title="' . $giver->data->name . ' (' . $e->pr . 'Pr)" /><span>&nbsp;</span>';
                }
            }

           

            if ($postJson->author != $_SESSION['playerId']) {

                echo '
                        <img
                            data-post="' . $post->name . '"
                            class="give-reward"
                            src="img/ui/forum/gift.webp"
                        />
                        <span
                            class="give-reward-span"
                        >';
                if ($player->have_option('isSuperAdmin')) {
                    echo '<a href="forum.php?edit=' . $post->name . '">Edit</a>';
                }
                echo '</span>';
            } elseif ($postJson->author == $_SESSION['playerId'] && !isset($_GET['hideMenu'])) {


                echo '
                    <span class="give-reward-span">

                        <a href="forum.php?edit=' . $post->name . '">Edit</a>
                    </span>';
            }

            echo '
                </div>';
                 if ($topJson->forum_id != 'Missives') {
                     CookieView::displayCookieView($postJson,$player);
                }
                
        echo '  </div>
                ';

            echo '
            </td>
            ';

            echo '
        </tr>
        ';

            if ($postN == $postMax) {

                break;
            }
        }


        echo '
</table>
';


        echo '<div id="last"></div>';

        $topicClosed = isset($topJson->closed) && $topJson->closed;
        if (!$topicClosed || $player->have_option('isAdmin')) {


            if (!isset($_GET['hideMenu'])) {

                $replyTitle = 'Répondre';
                if ($topicClosed) {
                    $replyTitle .= ' (admin only)';
                }
                echo '<div>
        <button class="reply" data-topic="' . htmlentities($_GET['topic']) . '">' . $replyTitle . '
        </button>
        </div>';
            }
        } else {

            echo '<input type="hidden" class="reply" data-topic="' . htmlentities($_GET['topic']) . '" />';
        }

        if ($topicClosed) {
            echo '<div>Sujet fermé.</div>';
        }


        $hideMenu = (!isset($_GET['hideMenu'])) ? '' : '&hideMenu=1';


        echo $postTotal . ' posts: <a href="forum.php?topic=' . htmlentities($_GET['topic']) . $hideMenu . '&page=' . $pagesN . '#last">dernier</a><br />';


        for ($i = 1; $i <= $pagesN; $i++) {
            $preText = "";
            $postText = "";
            if ($i == $page) {
                $preText = '<u><b>';
                $postText = '</b></u>';
            }

            echo '<a href="forum.php?topic=' . htmlentities($_GET['topic']) . $hideMenu . '&page=' . $i . '">' . $preText . 'page ' . $i . $postText . '</a> ';
        }


        echo Str::minify(ob_get_clean());

?>
        <script>
            window.topicName = <?php echo $topJson->name ?>;
            window.pageN = <?php echo $page ?>;
        </script>
        <script src="js/forum_topic.js?v=20251017"></script>
<?php
    }
}
