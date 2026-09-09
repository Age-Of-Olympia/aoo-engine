<?php

namespace App\View\Forum;

use App\Factory\PlayerFactory;
use App\Service\PlayerService;
use Classes\Forum;
use Classes\Ui;

class SearchView
{
    public static function renderSearch(): void
    {

        $ui = new Ui('Forum - Recherche');

        $player = PlayerFactory::active();
        $player->get_data(false);


        echo '<div><a href="forum.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a></div>';


        $search = !empty($_POST['keywords']) ? Forum::search($_POST['keywords']) : [];


        echo '
<h1>Rechercher dans les Forums</h1>
';

        echo '<p>Insérez un ou plusieurs mot-clés (2 charactères min.) séparés par des espaces.</p>';

        $keywords = (!empty($_POST['keywords'])) ? htmlentities($_POST['keywords']) : '';


        echo '
<form method="POST" action="forum.php?search">
    <input style="width: 300px;" type="text" name="keywords" value="' . $keywords . '" /> <input type="submit" value="Chercher" />
</form>
';


        if (!empty($_POST['keywords'])) {


            echo '<p>Résultats:</p>';


            echo '
    <table border="1" class="marbre" align="center">
        ';

            foreach ($search as $e) {


                $postJson = json()->decode('forum/posts', $e);
                if (!$postJson){
                    continue;
                } 

                $topJson = json()->decode('forum/topics', $postJson->top_id);
                if (!$topJson) {
                    continue;
                }

                $forumJson = json()->decode('forum/forums', $topJson->forum_id);
                if (!$forumJson)
                { 
                    continue;
                }

                if ($forumJson->category_id == 'RP' && !isset($topJson->approved)) {

                    continue;
                }


                if (!empty($forumJson->factions) && !in_array($player->data->faction, $forumJson->factions)) {

                    continue;
                }


                $link = 'forum.php?topic=' . htmlentities($topJson->name) . '#' . htmlentities($postJson->name);

                echo '
            <tr>

                <th><a href="' . $link . '">' . htmlentities($topJson->title) . '</a></th>

            </tr>
            ';

                echo '
            <tr>

                <td align="left">' . self::excerpt($postJson->text, $_POST['keywords']) . ' <a href="' . $link . '">[...]</a></td>

            </tr>
            ';
            }


            echo '
    </table>
    ';
        }
    }

    /**
     * First line of a post with each searched word highlighted. The line is
     * escaped before the highlight is added, so the highlight is the only
     * markup that reaches the page.
     */
    public static function excerpt(string $text, string $keywords): string
    {
        $line = htmlentities(explode("\n", $text)[0]);

        foreach (explode(' ', $keywords) as $word) {
            if ($word === '') {
                continue;
            }
            $word = htmlentities($word);
            $line = str_replace(' ' . $word . ' ', '<font color="red"> ' . $word . ' </font>', $line);
        }

        return $line;
    }
}
