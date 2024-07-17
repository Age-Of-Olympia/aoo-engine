<?php

require_once('config.php');


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

elseif(!empty($_GET['edit'])){


    include('scripts/forum/edit.php');

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

elseif(isset($_GET['approve'])){


    include('scripts/forum/approve.php');

    exit();
}

elseif(isset($_GET['search'])){


    include('scripts/forum/search.php');

    exit();
}

elseif(isset($_GET['autosave']) && !empty($_POST['text'])){


    if(trim($_POST['text']) != ''){


        Forum::put_autosave($_SESSION['playerId'], $_POST['text']);
    }

    exit();
}


$ui = new Ui('Forum');


include('scripts/infos.php');
include('scripts/menu.php');


echo '<h1>Forums</h1>';


echo '
<table border="0" align="center" width="500">
    ';


    foreach(array('RP','Privés','HRP') as $cat){


        $catJson = json()->decode('forum', 'categories/'. $cat);


        echo '
        <tr>
            <th width="50" height="50"></th>
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
                <td class="forum" data-forum="'. $forJson->name .'"²><img src="img/ui/forum/'. $img .'.png" width="50" /></td>
                ';

                echo '
                <td class="forum" data-forum="'. $forJson->name .'">
                    ';


                    echo ''. $forJson->name .'';


                    echo '
                </td>
                ';

                echo '
                <td class="forum" data-forum="'. $forJson->name .'" align="center">
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
$(document).ready(function(e){

    $('.forum').click(function(e){

        document.location = 'forum.php?forum='+ $(this).data('forum');
    });
});
</script>
