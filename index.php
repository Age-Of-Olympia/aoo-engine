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


ob_start();

?>
<div id="new-turn"><?php include('scripts/new_turn.php') ?></div>

<div id="infos"><?php include('scripts/infos.php') ?></div>

<div id="menu"><?php include('scripts/menu.php') ?></div>

<?php include('scripts/view.php') ?>


<?php

echo '<div style="color: red;">';

if(!CACHED_INVENT) echo 'CACHED_INVENT = false<br />';
if(!CACHED_KILLS) echo 'CACHED_KILLS = false<br />';
if(!CACHED_CLASSEMENTS) echo 'CACHED_CLASSEMENTS = false<br />';
if(!CACHED_QUESTS) echo 'CACHED_QUESTS = false<br />';
if(AUTO_GROW) echo 'AUTO_GROW = true<br />';
if(FISHING) echo 'AUTO_GROW = true<br />';
if(ITEM_DROP > 10) echo 'ITEM_DROP = '. ITEM_DROP .'<br />';
if(DMG_CRIT > 10) echo 'DMG_CRIT = '. DMG_CRIT .'<br />';
if(AUTO_BREAK) echo 'AUTO_BREAK = true<br />';
if(AUTO_FAIL) echo 'AUTO_FAIL = true<br />';

echo '</div>';

echo Str::minify(ob_get_clean());
