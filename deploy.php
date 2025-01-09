<?php

define('NO_LOGIN', true);

require_once('config.php');
require_once('config/config-console.php');


// check session
include ('checks/admin-check.php');

if (isset($_GET["type"]) && isset($_GET["passphrase"])) {
    echo "Deploying ".$_GET["type"];
    $passPhrase = file_get_contents('/home/'.get_current_user().'/etc/passphrase');

    if ($passPhrase != "" && strcmp($passPhrase, $_GET["passphrase"])) {
        $output=shell_exec("./scripts/deploy_".$_GET["type"].".sh 2>&1 | tee -a /tmp/deploy_".$_GET["type"].".log");
        echo "<br />".$output;
        echo "<br />Done.";
    }
    
}
?>

