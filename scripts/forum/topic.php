<?php

$topJson = json()->decode('forum', 'topics/'. $_GET['topic']);


if(!$topJson){

    exit('error top');
}


$ui = new Ui(htmlentities($topJson->title));


include('scripts/infos.php');
include('scripts/menu.php');


$postMin = 0;
$postMax = 4;
$page = 1;

$postTotal = count($topJson->posts);

$pagesN = Forum::get_pages($postTotal);


if(!empty($_GET['page'])){


    if(!is_numeric($_GET['page']) || $_GET['page'] < 1 || $_GET['page'] > $pagesN){

        exit('error page');
    }


    $page = $_GET['page'];

    $postMin += ($_GET['page']-1)*5;
    $postMax += ($_GET['page']-1)*5;
}


echo '<h1>'. htmlentities($topJson->title) .'</h1>';


$forumJson = json()->decode('forum', 'forums/'. $topJson->forum_id);


Forum::check_access($player, $forumJson);


if($topJson->forum_id == 'Missives'){


    include('scripts/forum/missives.php');
}


if(Forum::put_view($topJson) && $topJson->forum_id == 'Missives'){


    // put viewed in db

    $sql = 'UPDATE players_forum_missives SET viewed = 1 WHERE player_id = ? AND name = ?';

    $db = new Db();

    $db->exe($sql, array($player->id, $topJson->name));
}


echo '
<div>
    <a href="forum.php">Forum ('. $forumJson->category_id .')</a> >
    <a href="forum.php?forum='. $topJson->forum_id .'">'. $topJson->forum_id .'</a> >
    <a href="forum.php?topic='. htmlentities($topJson->name) .'">'. htmlentities($topJson->title) .'</a>
</div>';


echo '
<table border="0" align="center" class="marbre box-shadow" cellspacing="0" style="width: 100%">
    ';


    foreach($topJson->posts as $postN=>$post){


        if($postN < $postMin){

            continue;
        }


        $postJson = json()->decode('forum', 'posts/'. $post->name);


        echo '
        <tr class="tr-topic2 box-shadow">
            ';

            echo '
            <td
                width="50"
                >
                ';


                $author = new Player($postJson->author);

                $author->get_data();

                echo '<a href="infos.php?targetId='. $author->id .'"><img class="box-shadow" src="'. $author->data->mini .'" width="50" /></a>';


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

                echo '<div><a href="infos.php?targetId='. $author->id .'">'. $author->data->name .'</a>, mat.'. $author->id .'</div>';


                $raceJson = json()->decode('races', $author->data->race);

                echo '<div style="font-size: 88%;"><i>'. $raceJson->name .' Rang '. $author->data->rank .'</i></div>';


                $factionJson = json()->decode('factions', $author->data->faction);

                echo '<div style="font-size: 88%;">'. $factionJson->name .' <img style="display: inline-block; width: 1.5em; vertical-align: middle;" src="'. $author->data->faction_mini .'" width="25" /> (<i>Citoyen</i>) </div>';


                echo '
                <div
                    id="'. $postJson->name .'"

                    style="position: absolute; right: 0; top: 0;"
                    >
                        <a href="forum.php?topic='. htmlentities($_GET['topic']) .'&page='. $page .'#'. $postJson->name .'">#'. $postN+1 .'</a>
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
                data-post="'. $post->name .'"

                colspan="2"

                align="left"
                valign="top"
                >
                ';


                $text = $postJson->text;

                if($forumJson->category_id == 'RP' && !isset($topJson->approved)){


                    $player = new Player($_SESSION['playerId']);

                    if(!$player->have_option('isAdmin')){


                        $text = '[i]Texte dans une langue qui vous est inconnue.[/i]

                        hrp: ce texte est en attente de l\'approbation d\'un modérateur.';
                    }
                }


                $bbcode = new bbcode();

                echo '<div style="padding: 10px;">'. $bbcode->render(htmlentities($text)) .'</div>';

                echo '
                <div class="post-rewards">

                    ';

                    //recompenses
                    if(!empty($postJson->rewards)){


                        foreach($postJson->rewards as $e){


                            $giver = new Player($e->player_id);
                            $giver->get_data();


                            echo '<img src="'. $e->img .'" title="'. $giver->data->name .' ('. $e->pr .'Pr)" /><span>&nbsp;</span>';
                        }
                    }


                if($postJson->author != $_SESSION['playerId']){


                    echo '
                        <img
                            data-post="'. $post->name .'"
                            class="give-reward"
                            src="img/ui/forum/gift.png"
                        />
                        <span
                            class="give-reward-span"
                        ></span>';
                }

                elseif($postJson->author == $_SESSION['playerId']){


                    echo '
                    <span class="give-reward-span">

                        <a href="forum.php?edit='. $post->name .'">Edit</a>
                    </span>';
                }

                    echo '
                </div>
                ';

                echo '
            </td>
            ';

            echo '
        </tr>
        ';

        if($postN == $postMax){

            break;
        }
    }


    echo '
</table>
';


echo '<div id="last"></div>';


if(!isset($topJson->closed)){


    echo '<div>
    <button class="reply" data-topic="'. htmlentities($_GET['topic']) .'">
    <span class="ra ra-cycle"></span> Répondre
    </button>
    </div>';
}
else{

    echo '<input type="hidden" class="reply" data-topic="'. htmlentities($_GET['topic']) .'" />';
    echo '<div>Sujet fermé.</div>';
}


echo $postTotal .' posts: <a href="forum.php?topic='. htmlentities($_GET['topic']) .'&page='. $pagesN .'#last">dernier</a><br />';


for($i=1; $i<=$pagesN; $i++){


    echo '<a href="forum.php?topic='. htmlentities($_GET['topic']) .'&page='. $i .'">page '. $i .'</a> ';
}


?>
<script>
$(document).ready(function(e){


    window.topicName = <?php echo $topJson->name ?>;
    window.pageN = <?php echo $page ?>;


    $('.reply').click(function(e){


        document.location = 'forum.php?reply='+ $(this).data('topic');
    });


    $('.post-rewards img:not(.give-reward)').click(function(e){


        $('.post-rewards span:not(.give-reward-span)').html('');

        $(this).next('span').html($(this).attr('title'));
    });

    $('.give-reward').click(function(e){


        var $this = $(this);


        $.ajax({
            type: "POST",
            url: 'forum.php?rewards',
            data: {
                'post': $this.data('post')
            }, // serializes the form's elements.
            success: function(data)
            {
                htmlContent = $('<div>').html(data).find('#data').html();
                // alert(htmlContent);
                $this.next('span').html(htmlContent);
            }
        });
    });
});
</script>
