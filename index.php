<?php


if(isset($_GET['logout'])){

    ob_start();
}


define('NO_LOGIN', true);


require_once('config.php');


$ui = new Ui($title="Index");


if(!isset($_SESSION['playerId']) || isset($_GET['menu'])){

    include('scripts/index.php');
}

elseif(isset($_GET['logout'])){

    unset($_SESSION['mainPlayerId']);
    unset($_SESSION['playerId']);
    session_destroy();

    ob_clean();

    header('location:index.php');
}


?>


<div id="infos"><?php include('scripts/infos.php') ?></div>

<div id="menu"><?php include('scripts/menu.php') ?></div>

<?php include('scripts/view.php') ?>


<?php

echo '<div style="color: red;">';

if(!CACHED_INVENT) echo 'CACHED_INVENT = false<br />';
if(AUTO_GROW) echo 'AUTO_GROW = true<br />';
if(FISHING) echo 'AUTO_GROW = true<br />';

echo '</div>';
