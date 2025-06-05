<?php
use Classes\Db;

define('NO_LOGIN', true);

require_once('config.php');


// db link
$db = new Db();


// firewall
include('config/firewall.php');


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
if( !password_verify($_POST['psw'], $row['psw'])){


    // reccord the fail for firewall
    include('config/firewall_block.php');

    exit('Mauvais mot de passe.');
}


// last login time
$sql = '
UPDATE
players
SET
lastLoginTime = '. time() .'
WHERE
id = ?
';

$db->exe($sql, $row['id']);


// set sessions var
$_SESSION['mainPlayerId'] = $row['id'];

$_SESSION['playerId'] = $row['id'];
$_SESSION['originalPlayerId'] = $row['id'];
unset($_SESSION['nonewturn']);

$values = array(
    'player_id'=> $row['id'],
    'ip'=>md5($ip),
    'footprint'=>md5( $_POST['footprint']),
    'time'=>time()
);

$db = new Db();

$db->insert('players_connections', $values);


$sql = '
SELECT * FROM players_banned
WHERE player_id = ?
';

$res = $db->exe($sql, $_SESSION['playerId']);

if($res->num_rows){

    $row = $res->fetch_object();

    $_SESSION['banned'] = $row->text;

    $bannedIps = explode(',', $row->ips);

    if(!in_array($ip, $bannedIps)){


        array_push($bannedIps, $ip);

        $bannedIps = array_filter($bannedIps, function($value) {
            return $value !== ""; // Filtre les valeurs qui ne sont pas des chaînes vides
        });

        $sql = 'UPDATE players_banned SET ips = ? WHERE player_id = ?';

        $db->exe($sql, array(implode(',', $bannedIps), $_SESSION['playerId']));
    }
}
