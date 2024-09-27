<?php

ob_start();

$ui = new Ui('Derniers Messages du Forum');

include('scripts/infos.php');

include('scripts/menu.php');


if(!empty($player->data->registerTime)){

    $registerTime = $player->data->registerTime;
}
else{

    $registerTime = 0;
}


echo '<h1>Derniers Messages du Forum</h1>';

echo '
<table border="1" class="marbre" align="center" width="500" id="forum-last-posts">
';


foreach(array('RP','Privés','HRP') as $cat){


    $catJson = json()->decode('forum', 'categories/'. $cat);


    foreach($catJson->forums as $forum){


        $forJson = json()->decode('forum', 'forums/'. $forum->name);


        if($catJson->name == 'Privés'){


            if(!empty($forJson->factions)){


                if(!in_array($player->data->faction, $forJson->factions) && !in_array($player->data->secretFaction, $forJson->factions)){

                    continue;
                }
            }
        }


        foreach($forJson->topics as $topics){


            $topJson = json()->decode('forum/topics', $topics->name);


            // hide topics created previously to the register
            if($topJson->last->time < $registerTime){

                continue;
            }


            if(in_array($player->id, $topJson->views)){


                continue;
            }

            echo '
            <tr>
                <td align="left">
                ';

                    echo '<b><a href="forum.php?topic='. htmlentities($topJson->name) .'">'. htmlentities($topJson->title) .'</a></b>';

                    echo '<br>';

                    echo '<i>Dans '. $forJson->name .'</i>';

                    echo '
                </td>
                ';

                $postTotal = count($topJson->posts);

                $pagesN = Forum::get_pages($postTotal);

                echo '
                <td
                    width="1%"
                    style="white-space: nowrap; font-size: 88%;"
                    align="right"
                    >
                    ';

                    $author = new Player($topJson->last->author);

                    $author->get_data();


                    $date = date('d/m/Y', timestampNormalization($topJson->last->time));

                    if($date == date('d/m/Y', time())){

                        $date = 'Aujourd\'hui';
                    }
                    elseif($date == date('d/m/Y', time()-86400)){

                        $date = 'Hier';
                    }

                    echo '<a href="forum.php?topic='. htmlentities($topJson->name) .'&page='. $pagesN .'#'. $topJson->last->time .'">';

                    echo 'Par '. $author->data->name;
                    echo '<br />';
                    echo $date;
                    echo '<br />';
                    echo 'à '. date('H:i', $topJson->last->time);

                    echo '</a>';

                    echo '
                </td>
            </tr>
            ';
        }
    }
}


echo '
</table>
';

echo Str::minify(ob_get_clean());



