<?php

require_once('config.php');

$ui = new Ui('Carte du Monde');

$player = new Player($_SESSION['playerId']);

$player->get_coords();

$planJson = json()->decode('plans', $player->coords->plan);


if(isset($_GET['local'])){

    include('scripts/map/local.php');
    exit();
}

?>
<div><a href="index.php"><button>Retour</button></a><a href="map.php?local"><button><?php echo $planJson->name ?></button></a></div>


<div id="ui-map">
<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<svg
    xmlns="http://www.w3.org/2000/svg"
    xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1"
    baseProfile="full"

    id="svg-map"

    width="532"
    height="800"

    style="background: url(img/ui/map/parchemin.png);"
    >

    <?php

    foreach(File::scan_dir('img/ui/map/', $without=".png") as $e){

        if($e == 'parchemin'){

            continue;
        }

        $mapJson = json()->decode('plans', $e);


        $opacity = 0.3;

        if($player->coords->plan == $e){

            $opacity = 1;
        }


        echo '
        <image
            x="'. $mapJson->x .'"
            y="'. $mapJson->y .'"
            class="map location"
            href="img/ui/map/'. $e .'.png"
            style="opacity: '. $opacity .'; cursor: pointer;"
            />
        ';
    }

    ?>
</svg>
</div>

<script>
$(document).ready(function(){

    $('.map')
    .on('mouseover', function(e){

        window.old_opacity = $(this).css('opacity');

        $(this).css('opacity','1');
    })
    .on('mouseout', function(e){

        $(this).css('opacity',window.old_opacity);
    });
});
</script>
