<?php
/**
 * Images des bâtiments (admin dashboard → Bâtiments → Images) : le second
 * VISAGE du stock d'images — les types de bâtiments (sorte « structure »),
 * séparés des races de personnages comme Races × Types. La première image
 * du stock d'un type est son sprite sur le plateau
 * (BuildingService::resolveAvatar). Toute la mécanique vit dans
 * admin/avatars-portraits.php ; ce wrapper pose le mode avant de l'inclure.
 */
$_GET['kind'] = 'structure';

require __DIR__ . '/avatars-portraits.php';
