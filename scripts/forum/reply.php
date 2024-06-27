<?php


$topJson = json()->decode('forum', 'topics/'. $_GET['reply']);


if(!$topJson){

    exit('error top');
}


$forumJson = json()->decode('forum', 'forums/'. $topJson->forum_id);


if(!empty($_POST['text'])){


    Forum::check_access($player, $forumJson);


    // create post
    Forum::put_post($player, $topJson, $_POST['text']);


    // delete missives views
    if($topJson->forum_id == 'Missives'){

        $sql = 'UPDATE players_forum_missives SET viewed = 0 WHERE name = ?';

        $db = new Db();

        $db->exe($sql,$topJson->name);
    }
    else{

        Forum::refresh_last_posts();
    }


    echo time();


    exit();
}


$ui = new Ui('Répondre');


echo '<h1>'. $topJson->title .'</h1>';


Forum::check_access($player, $topJson);


echo '<h2>Répondre</h2>';


echo '
<div>
<textarea
    class="box-shadow tr-topic1"
    style="width: 500px;"

    rows="20"
    ></textarea>
</div>
';


echo '<div><button class="submit" data-topic="'. $topJson->name .'">Envoyer</button></div>';


$postTotal = count($topJson->posts)+1;

$pagesN = Forum::get_pages($postTotal);


?>
<script>
$(document).ready(function(e){

    $('.submit').click(function(e){

        var text = $('textarea').val();


        if(text.trim() == ''){

            alert('Le message ne doit pas être vide.');
            return false;
        }


        $(this).prop('disabled', true);


        var topic = $(this).data('topic');


        $.ajax({
            type: "POST",
            url: 'forum.php?reply='+ topic,
            data: {
                'text': text
            }, // serializes the form's elements.
            success: function(data)
            {
                // alert(data);
                document.location = 'forum.php?topic='+ topic +'&page=<?php echo $pagesN ?>#'+ data.trim();
            }
        });
    });
});
</script>
