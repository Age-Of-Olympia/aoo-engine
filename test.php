<?php 
use App\Enum\CoordType;
use Classes\Player;
use Classes\View;
use Classes\PerfTimer;
require_once('config.php');

$player = new Player($_SESSION['playerId']);
$coords=$player->getCoords();

$class=  CoordType::XYZPLAN;
$class2= new View($coords, 20, $class);
$p=20;
$x =100;
$timer =new PerfTimer();
if($_GET['action'] == 'view'){
    $timer2 =new PerfTimer();
    for ($i=0; $i < $x; $i++) { 
           $view = new View($coords,$p);
    }
    echo "<br>". $timer2->stop();
}
else if($_GET['action'] == 'view2'){
    $timer2 =new PerfTimer();
    for ($i=0; $i < $x; $i++) { 
    $inSight = array();
    $inSightId = array();
    View::get_coords_id_arround($inSight, $inSightId, $coords, $p);
    }
    echo "<br>". $timer2->stop();
}
else if($_GET['action'] == 'view3'){
    $timer2 =new PerfTimer();
    for ($i=0; $i < $x; $i++) { 
    $inSight = null;
    $inSightId = array();
    View::get_coords_id_arround($inSight, $inSightId, $coords, $p);
    }
    echo "<br>". $timer2->stop();
}
