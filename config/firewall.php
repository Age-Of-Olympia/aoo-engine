<?php


$db = new Db();


// firewall
$sql = 'DELETE FROM players_ips WHERE expTime <= '. time() .'';
$db->exe($sql);

$ip = $_SERVER['REMOTE_ADDR'];

$sql = 'SELECT * FROM players_ips WHERE ip = "'. $ip .'" AND failed > 0 ';
$result = $db->exe($sql);
$row_ip = $result->fetch_assoc();

$haveFailed = ( is_array($row_ip) ) ? $row_ip['failed'] : 0 ;

if( $haveFailed >= 3 ) exit('Trop de tentatives!\nAttendez 5 minutes avant de réessayer.');
