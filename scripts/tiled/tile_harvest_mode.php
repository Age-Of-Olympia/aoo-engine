<?php
use Classes\Db;

$db = new Db();

$infos ='';

$db = new Db();
/* Le cycle revient à 0 : normal → récoltable → épuisé → normal.
 *
 * Il bouclait entre -1 et -2, sans jamais repasser par 0. Un clic de
 * trop sur un mur ordinaire le rendait donc récoltable POUR TOUJOURS,
 * du moins depuis ce bouton — c'est ce qui a laissé un piédestal
 * s'annoncer récoltable, et c'est ce qui l'a exclu du passage des murs
 * en entités, qui ne prenait que damages >= 0. */
$sql = "UPDATE map_resources 
    SET damages = CASE
    WHEN damages = 0 THEN -1
    WHEN damages = -1 THEN -2
    WHEN damages = -2 THEN 0
    ELSE damages END
     where coords_id = ?";
$db->exe($sql,$coordsId);



