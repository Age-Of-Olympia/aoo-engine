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


        echo '
<table border="0" align="center" width="500">
    ';


        foreach (array('RP', 'Privés', 'HRP') as $cat) {


            $catJson = json()->decode('forum', 'categories/' . $cat);


            echo '
        <tr>
            <th width="50" height="50"></th>
            <th>' . $catJson->name . '</th>
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


                echo '' . $forJson->name . '';


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


        echo '<div><a href="forum.php?lastPosts"><button>Derniers messages</button></a> <a href="forum.php?search"><button>Recherche</button></a></div>';


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
            });
        </script>
<?php

        echo Str::minify(ob_get_clean());
    }
}
