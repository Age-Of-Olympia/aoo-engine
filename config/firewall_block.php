<?php


$db = new Db();

$ip = $_SERVER['REMOTE_ADDR'];

$expTime = time() + 300;

// reccord the fail for firewall
if( $haveFailed ){

    $sql = 'UPDATE players_ips SET failed = failed + 1, expTime = '. $expTime .' WHERE ip = "'. $ip .'" ';
    $db->exe($sql);
}
else{

    $sql = '
    INSERT INTO players_ips
    (`ip`,`expTime`,`failed`)
    VALUES("'. $ip .'",'. $expTime .',1);
    ';
    $db->exe($sql);
}


