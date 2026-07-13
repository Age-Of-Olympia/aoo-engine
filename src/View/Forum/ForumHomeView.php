<?php

namespace App\View\Forum;

use App\Factory\PlayerFactory;
use Classes\Str;
use Classes\Ui;
use App\View\InfosView;
use App\View\MenuView;

class ForumHomeView
{
    /** Rendu en fragment (panneau HUD) : pas d'enveloppe Ui/Infos/Menu. */
    public static bool $fragment = false;

    public static function renderHomeView(): void
    {
        $player = PlayerFactory::active();
        $player->get_data(false);

        if (!self::$fragment) {

            $ui = new Ui('Forum');
            InfosView::renderInfos($player);
            MenuView::renderMenu();
        }


        ob_start();


        echo '<h1>Forums</h1>';

        /* Pastilles orange : sujets non lus par forum, sommés sur la
         * catégorie — même couleur que la pastille du bouton Forum. */
        $unreadByForum = (new \App\Service\ForumService())->GetUnreadCountByForum($player);

        $unreadBadge = function (int $n): string {
            return $n > 0
                ? ' <span class="cartouche bulle-mini forum-unread-mini" style="background:#d9720f;">' . $n . '</span>'
                : '';
        };

        echo '
<table border="0" align="center" width="500">
    ';


        foreach (array('RP', 'Privés', 'HRP') as $cat) {


            $catJson = json()->decode('forum', 'categories/' . $cat);


            $catUnread = 0;
            foreach ($catJson->forums as $forum) {
                $catUnread += $unreadByForum[$forum->name] ?? 0;
            }


            echo '
        <tr>
            <th width="50" height="50"></th>
            <th>' . $catJson->name . $unreadBadge($catUnread) . '</th>
            <th width="1%">Sujets</th>
        </tr>
        ';


            foreach ($catJson->forums as $forum) {


                $forJson = json()->decode('forum', 'forums/' . $forum->name);


                $img = $forJson->name;

                if ($catJson->name == 'Privés') {


                    if (!empty($forJson->factions)) {


                        if (!in_array($player->data->faction, $forJson->factions) && !in_array($player->data->secretFaction, $forJson->factions)) {

                            continue;
                        }
                    }


                    $img = 'Privés';
                }


                echo '
            <tr class="tr-cat">
                ';

                echo '
                <td class="forum" data-forum="' . $forJson->name . '"><img src="img/ui/forum/' . $img . '.webp" width="50" height="50" /></td>
                ';

                echo '
                <td class="forum" data-forum="' . $forJson->name . '">
                    ';


                echo '' . $forJson->name . $unreadBadge($unreadByForum[$forJson->name] ?? 0) . '';


                echo '
                </td>
                ';

                echo '
                <td class="forum" data-forum="' . $forJson->name . '" align="center">
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


        echo '<div><a href="forum.php?lastPosts"><button>Derniers messages</button></a> <a href="forum.php?search"><button>Recherche</button></a> <button id="forum-mark-all-read">Tout marquer comme lu</button></div>';


?>
        <script>
            $(document).ready(function(e) {

                $('.forum').click(function(e) {

                    var forum = $(this).data('forum');

                    /* HUD : le forum s'ouvre dans le panneau (le nom du
                     * forum en titre) ; habillage hérité : pleine page. */
                    if(window.hudOpenPanel){

                        window.hudOpenPanel('load_forum.php?forum=' + encodeURIComponent(forum), forum);
                        return;
                    }

                    document.location = 'forum.php?forum=' + forum;
                });

                /* Tout marquer comme lu : en panneau HUD on recharge
                 * l'accueil (les pastilles par forum sont recomptées au
                 * rendu) au lieu de suivre le redirect de l'API, qui
                 * renverrait sur la pleine page héritée. */
                $('#forum-mark-all-read').click(function(e) {

                    aooFetch('api/forum/markAllAsRead.php', null, 'POST')
                        .then(function(data) {

                            if(window.hudOpenPanel){

                                if(window.hudRefreshForumBadge){
                                    window.hudRefreshForumBadge();
                                }
                                if(window.refreshMailBadges){
                                    window.refreshMailBadges();
                                }
                                window.hudOpenPanel('load_forum.php', 'Forum');
                                return;
                            }

                            autoModal(data);
                        })
                        .catch(autoError());
                });
            });
        </script>
<?php

        echo Str::minify(ob_get_clean());
    }
}
