<?php

require_once('config.php');

$ui = new Ui('Carte du Monde');

$player = new Player($_SESSION['playerId']);

$player->get_coords();

$planJson = json()->decode('plans', $player->coords->plan);
$planJson->id = $player->coords->plan;
$planJson->fromCoords = $player->coords;


ob_start();


// hors map
if(!$planJson){

    echo '<div><a href="index.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a></div><br />';


    $url = 'img/ui/illustrations/'. $player->coords->plan .'.webp';

    if(!file_exists($url)){

        $url = 'img/ui/illustrations/gaia.webp';
    }


    echo '<img class="box-shadow" src="'. $url .'" />';

    exit();
}


if(isset($_GET['local'])){

    include('scripts/map/local.php');

    echo Str::minify(ob_get_clean());

    exit();
}

?>
<div><a href="index.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a><a href="map.php"><button>Monde</button></a><a href="map.php?local"><button><?php echo $planJson->name ?></button></a></div>


<?php echo Ui::print_map($player, $planJson) ?>


<script>
window.coordsPlan = "<?php echo $player->coords->plan ?>";
window.allMap = <?php echo (isset($_GET['allMap'])) ? 'true' : 'false' ?>;
window.triggerId = <?php echo (!empty($_GET['triggerId']) && is_numeric($_GET['triggerId'])) ? $_GET['triggerId'] : 'false' ?>;
<?php include('scripts/map/travel.php') ?>
</script>
<script src="js/map.js"></script>
<?php

echo Str::minify(ob_get_clean());
