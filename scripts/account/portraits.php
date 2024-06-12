<?php


$dir = 'img/portraits/'. $player->row->race .'/';


if(!empty($_POST['img'])){


    $url = str_replace('/', '', $_POST['img']);
    $url = str_replace('..', '', $url);
    $url = $dir . $url;

    if(!file_exists($url)){

        exit('error url');
    }


    $sql = 'UPDATE players SET portrait = ? WHERE id = ?';

    $db = new Db();

    $db->exe($sql, array(&$url, &$player->id));


    @unlink('datas/private/players/'. $player->id .'.json');


    exit();
}


echo '<div><a href="account.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a></div>';


foreach(File::scan_dir($dir) as $e){

    echo '<img style="cursor: pointer" src="'. $dir . $e .'" data-img="'. $e .'" height="330" />';
}

?>
<script>
$(document).ready(function(){

    $('img').click(function(e){

        let img = $(this).data('img');

        $.ajax({
            type: "POST",
            url: 'account.php?portraits',
            data: {'img':img}, // serializes the form's elements.
            success: function(data)
            {
                alert('Portrait changé avec succès!');
                document.location = 'account.php';
            }
        });
    });
});
</script>
