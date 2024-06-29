<?php


$dir = 'img/avatars/'. $player->data->race .'/';


if(!empty($_POST['img'])){


    $player->change_avatar($_POST['img']);

    exit();
}


echo '<div><a href="account.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a></div>';


foreach(File::scan_dir($dir) as $e){

    echo '<img style="cursor: pointer" src="img/ui/fillers/50.png" data-src="'. $dir . $e .'" data-img="'. $e .'" width="50" />';
}

?>
<script src="js/progressive_loader.js"></script>
<script>
$(document).ready(function(){

    $('img').click(function(e){

        let img = $(this).data('img');

        $.ajax({
            type: "POST",
            url: 'account.php?avatars',
            data: {'img':img}, // serializes the form's elements.
            success: function(data)
            {
                alert('Avatar changé avec succès!');
                document.location = 'account.php';
            }
        });
    });
});
</script>

