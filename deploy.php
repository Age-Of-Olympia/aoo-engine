<?php

define('NO_LOGIN', true);

require_once('config.php');
require_once('config/config-console.php');


// check session
if(!isset($_SESSION['playerId'])){

    echo 'login required';
    exit();
}


// check admin (only once per session)
if(!isset($_SESSION['isAdmin'])){

    // check admin
    $player = new Player($_SESSION['playerId']);
    if(!$player->have_option('isAdmin')){

        echo 'admin account required';
        exit();
    }
    else{

        $_SESSION['isAdmin'] = true;
    }
}

if (isset($_GET["type"]) && isset($_GET["passphrase"])) {
    echo "Deploying ".$_GET["type"];
    shell_exec("scripts/handle_passphrase.sh ".$_GET["passphrase"]." ".$_GET["type"]." 2>&1 | tee -a /tmp/deploy_".$_GET["type"].".log 2>/dev/null >/dev/null &");
    echo $output;
    echo "<br />Done.";
}
?>

