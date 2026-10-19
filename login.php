<?php
use Classes\Db;
use App\Service\FirewallService;

define('NO_LOGIN', true);

require_once('config.php');


// db link
$db = new Db();


// firewall
$firewall = new FirewallService();
$firewall->TryPassFirewall();


// login
if( empty( $_POST['name'] ) ) exit('Renseignez un nom ou un matricule.');
if( empty( $_POST['psw'] ) ) exit('Renseignez un mot de passe.');


// login with name
if( !is_numeric( $_POST['name'] ) ){


    $_POST['name'] = trim($_POST['name']);


    $nameTbl = explode(' ', $_POST['name']);

    foreach($nameTbl as $k=>$e){


        $nameTbl[$k] = ucfirst($e);
    }

    $_POST['name'] = implode(' ', $nameTbl);


    // name exist?
    $sql = '
    SELECT *
    FROM
    players
    WHERE name = ?
    ';

    $result = $db->exe($sql, $_POST['name']);

    if( !$result->num_rows ) exit('Aucun personnage ne porte ce nom.');
}


// login with mat
elseif( is_numeric( $_POST['name'] ) ){


    // name exist?
    $result = $db->get_single('players', $_POST['name']);

    if( !$result->num_rows ) exit('Aucun personnage ne porte matricule.');
}


$row = $result->fetch_assoc();


// wrong password
$accounts = new \App\Service\AccountService();
$storedPassword = $accounts->passwordHashOf((int) $row['id']);

if( $storedPassword === null || !password_verify($_POST['psw'], $storedPassword)){
    $firewall->RecordFailedAttempt();
    exit('Mauvais mot de passe.');
}


$footprint = md5($_POST['footprint'] ?? '');
$login = new \App\Service\LoginService();
$login->open((int) $row['id'], $footprint);

if(!empty($_POST['remember'])){

    $login->remember((int) $row['id'], $footprint);
}
