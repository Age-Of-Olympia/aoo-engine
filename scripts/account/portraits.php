<?php

use App\Entity\EntityManagerFactory;
use App\Entity\Race;
use App\Service\RaceService;

$dir = 'img/portraits/'. $player->data->race .'/';


if(!empty($_POST['img'])){


    $url = str_replace('/', '', $_POST['img']);
    $url = str_replace('..', '', $url);
    $url = $dir . $url;

    if(!file_exists($url)){

        exit('error url');
    }


    $sql = 'UPDATE players SET portrait = ? WHERE id = ?';

    $db = new Db();

    $db->exe($sql, array($url, $player->id));


    @unlink('datas/private/players/'. $player->id .'.json');


    exit();
}


echo '<div><a href="account.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a></div>';


echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 210px));">';


foreach(File::scan_dir($dir) as $e){


    if(str_contains($e, '_mini')){

        continue;
    }

    if(str_contains($e, '.sh')){

        continue;
    }

    echo '<img style="cursor: pointer;" src="img/portraits/placeholder.png" data-src="'. $dir . $e .'" data-img="'. $e .'" height="330" />';
}


echo '</div>';


?>
<script src="js/progressive_loader.js"></script>
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

<?php
include("checks/admin-check.php");

echo '<hr>';
echo '<div>Panneau d\'administration pour ajouter un portrait '.$player->data->race.'</div>';

$raceService = new RaceService();

// Fetch Race by name
$race = $raceService->getRaceByName($player->data->race);

$selectedRaceId = $race->getId();
$selectedType   = 'portrait';

include ($_SERVER['DOCUMENT_ROOT'].'/src/Form/upload_image_form.php');