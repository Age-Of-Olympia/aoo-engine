<?php

namespace App\View\Forum;

use Classes\Player;
use Classes\Str;
use Classes\Ui;
use App\View\InfosView;
use App\View\MenuView;

class ForumHomeView
{
    public static function renderHomeView(): void
    {
        $ui = new Ui('Forum');
        $player = new Player($_SESSION['playerId']);
        $player->get_data(false);
        InfosView::renderInfos($player);
        MenuView::renderMenu();


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


        echo '<div><a href="forum.php?search"><button>Recherche</button></a></div>';


?>
        <script>
            $(document).ready(function(e) {

                $('.forum').click(function(e) {

                    document.location = 'forum.php?forum=' + $(this).data('forum');
                });
            });
        </script>
<?php

        echo Str::minify(ob_get_clean());
    }
}
