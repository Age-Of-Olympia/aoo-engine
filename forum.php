<?php

require_once('config.php');


include('scripts/infos.php');
include('scripts/menu.php');


if(!empty($_GET['forum'])){


    include('scripts/forum/forum.php');

    exit();
}

elseif(!empty($_GET['topic'])){


    include('scripts/forum/topic.php');

    exit();
}

elseif(!empty($_GET['reply'])){


    include('scripts/forum/reply.php');

    exit();
}

elseif(!empty($_GET['newTopic'])){


    include('scripts/forum/newTopic.php');

    exit();
}

elseif(isset($_GET['rewards'])){


    include('scripts/forum/rewards.php');

    exit();
}


$ui = new Ui('Forum');


echo '<h1>Forums</h1>';


echo '
<table border="0" align="center" width="500">
    ';


    foreach(array('RP','Privés','HRP') as $cat){


        $catJson = json()->decode('forum', 'categories/'. $cat);


        echo '
        <tr>
            <th width="1%"></th>
            <th>'. $catJson->name .'</th>
            <th width="1%">Sujets</th>
        </tr>
        ';


        foreach($catJson->forums as $forum){


            $forJson = json()->decode('forum', 'forums/'. $forum->name);


            $img = $forJson->name;

            if($catJson->name == 'Privés'){


                if(!empty($forJson->factions)){


                    if(!in_array($player->data->faction, $forJson->factions)){

                        continue;
                    }
                }


                $img = 'Privés';
            }


            echo '
            <tr class="tr-cat">
                ';

                echo '
                <td><img src="img/ui/forum/'. $img .'.png" width="50" /></td>
                ';

                echo '
                <td data-forum="'. $forJson->name .'">
                    ';


                    echo ''. $forJson->name .'';


                    echo '
                </td>
                ';

                echo '
                <td align="center">
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


?>
<script>
$(document).ready(function(e){

    $('td').click(function(e){

        document.location = 'forum.php?forum='+ $(this).data('forum');
    });
});
</script>
