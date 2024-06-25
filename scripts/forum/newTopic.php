<?php


$forumJson = json()->decode('forum', 'forums/'. $_GET['newTopic']);


if(!$forumJson){

    exit('error forum');
}


if(!empty($_POST['text']) && !empty($_POST['name'])){


    Forum::check_access($player, $forumJson);


    // create topic
    $topJson = Forum::put_topic($player, $forumJson, $title=$_POST['name'], $_POST['text']);


    // missives
    if($topJson->forum_id == 'Missives'){


        // add missive in db

        $db = new Db();

        $values = array('player_id'=>$player->id, 'name'=>time());

        $db->insert('players_forum_missives', $values);
    }

    else{


        // edit forum
        Forum::add_topic_in_forum($topJson->name, $forumJson);

        Forum::refresh_last_posts();
    }

    echo time();


    exit();
}


$ui = new Ui('Nouveau sujet');


echo '<h1>'. $forumJson->name .'</h1>';


if(!empty($forumJson->factions)){


    if(!in_array($player->data->faction, $forumJson->factions)){

        exit('Accès refusé');
    }
}


echo '<h2>Nouveau sujet</h2>';


echo '
<div>
<input
    type="text"
    class="name tr-topic2"
    style="width: 500px;"
    value="Titre du sujet"
    />
<br />
<textarea
    class="box-shadow tr-topic1"
    style="width: 500px;"

    rows="20"
    >Message</textarea>
</div>
';


echo '<div><button class="submit" data-forum="'. $forumJson->name .'">Envoyer</button></div>';


?>
<script>
$(document).ready(function(e){

    $('.name').click(function(e){

        if($(this).val() == 'Titre du sujet'){

            $(this).val('');
        }
    });

    $('textarea').click(function(e){

        if($(this).val() == 'Message'){

            $(this).val('');
        }
    });

    $('.submit').click(function(e){


        $(this).prop('disabled', true);

        var name = $('.name').val();

        if(name.trim() == ''){

            alert('Votre titre doit contenir du texte.');

            $(this).prop('disabled', false);

            return false;
        }

        var text = $('textarea').val();

        if(text.trim() == ''){

            alert('Votre message doit contenir du texte.');

            $(this).prop('disabled', false);

            return false;
        }

        var forum = $(this).data('forum');


        $.ajax({
            type: "POST",
            url: 'forum.php?newTopic='+ forum,
            data: {
                'text': text,
                'name': name
            }, // serializes the form's elements.
            success: function(data)
            {
                alert(data);
                document.location = 'forum.php?topic='+ data.trim();
            }
        });
    });
});
</script>
