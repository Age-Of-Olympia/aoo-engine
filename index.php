<?php

require_once('config.php');


$ui = new Ui($title="Index");


?>
<!-- <a href="index.php"><img src="img/ui/bg/banner.png" height="100" /></a> -->

<div id="infos"><?php include('scripts/infos.php') ?></div>

<div id="menu"><?php include('scripts/menu.php') ?></div>

<div id="view"><?php include('scripts/view.php') ?></div>


<?php

echo '<div style="color: red;">';

if(!CACHED_INVENT) echo 'CACHED_INVENT = false<br />';
if(AUTO_GROW) echo 'AUTO_GROW = true<br />';
if(FISHING) echo 'AUTO_GROW = true<br />';

echo '</div>';
