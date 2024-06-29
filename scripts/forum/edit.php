<?php


$postJson = json()->decode('forum', 'posts/'. $_GET['edit']);


if(!$postJson){

    exit('error post');
}


$topJson = json()->decode('forum', 'topics/'. $postJson->top_id);

$forumJson = json()->decode('forum', 'forums/'. $topJson->forum_id);


if(!empty($_POST['text'])){


    $player = new Player($_SESSION['playerId']);


    Forum::check_access($player, $forumJson);


    if(!$player->have_option('isAdmin') && ($postJson->author != $player->id || !empty($topJson->closed))){

        exit('cannot edit this post');
    }


    $postJson->text = $_POST['text'];


    $data = Json::encode($postJson);

    Json::write_json('datas/private/forum/posts/'. $postJson->name .'.json', $data);


    echo $postJson->name;


    exit();
}


$ui = new Ui('Éditer un message');


include('scripts/infos.php');
include('scripts/menu.php');


echo '<h1>'. $topJson->title .'</h1>';


Forum::check_access($player, $topJson);


echo '<h2>Éditer</h2>';


echo '
<div>
<textarea
    class="box-shadow tr-topic1"
    style="width: 100%;"

    rows="20"
    >'. $postJson->text .'</textarea>
</div>
';


echo '<div><button class="submit" data-post="'. $postJson->name .'">Envoyer</button></div>';


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


        var post = $(this).data('post');


        $.ajax({
            type: "POST",
            url: 'forum.php?edit='+ post,
            data: {
                'text': text
            }, // serializes the form's elements.
            success: function(data)
            {
                // alert(data);
                document.location = 'forum.php?topic=<?php echo $postJson->top_id ?>&page=<?php echo $pagesN ?>#'+ data.trim();
            }
        });
    });
});
</script>
