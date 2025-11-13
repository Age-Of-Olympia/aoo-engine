<?php
use Classes\Ui;
use Classes\Str;
use App\View\InfosView;
use App\View\MainView;
use App\View\MenuView;
use App\View\NewTurnView;
use Classes\Player;

if(isset($_GET['logout'])){

    ob_start();
}


define('NO_LOGIN', true);


require_once('config.php');


$ui = new Ui($title="Index");


if(!empty($_SESSION['banned'])){

    echo '<h1>Vous avez été banni.</h1>';

    exit($_SESSION['banned']);
}


if(!isset($_SESSION['playerId']) || isset($_GET['menu'])){

    include('scripts/index.php');
}

elseif(isset($_GET['logout'])){

    unset($_SESSION['mainPlayerId']);
    unset($_SESSION['playerId']);
    unset($_SESSION['nonewturn']);
    session_destroy();

    ob_clean();

    header('location:index.php');
    exit();
}


ob_start();

// DEBUG: Show session state (remove this later)
if ($_SESSION['playerId'] == 7) {
    error_log("INDEX.PHP SESSION DEBUG:");
    error_log("  playerId: " . ($_SESSION['playerId'] ?? 'NOT SET'));
    error_log("  in_tutorial: " . ($_SESSION['in_tutorial'] ?? 'NOT SET'));
    error_log("  tutorial_player_id: " . ($_SESSION['tutorial_player_id'] ?? 'NOT SET'));
}

// Use tutorial character if in tutorial mode
$playerId = $_SESSION['playerId'];
if (!empty($_SESSION['in_tutorial']) && !empty($_SESSION['tutorial_player_id'])) {
    $playerId = $_SESSION['tutorial_player_id'];
    error_log("  USING TUTORIAL PLAYER: $playerId");
} else {
    error_log("  USING REAL PLAYER: $playerId");
}

$player = new Player($playerId);
$player->get_data(false);
?>
<div id="new-turn"><?php NewTurnView::renderNewTurn($player) ?></div>

<div id="infos"><?php InfosView::renderInfos($player);?></div>

<div id="menu"><?php MenuView::renderMenu(); ?></div>

<?php MainView::render($player) ?>


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
