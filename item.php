<?php

define('NO_LOGIN', true);

require_once('config.php');

echo '<a href="#" OnClick="history.go(-1)"><button><span class="ra ra-sideswipe"> Retour</button></a>';

if(!isset($_GET['itemId'])){


    $ui = new Ui('Objets');

    echo '<p><a href="https://age-of-olympia.net/wiki/doku.php?id=univers:objets">Cliquez-ici pour accéder à la liste des objets sur le Wiki.</a></p>';

    exit();
}


if(!is_numeric($_GET['itemId'])){

    exit('error itemId');
}


$item = new Item($_GET['itemId']);

$item->get_data();

$ui = new Ui('Objet '. $item->data->name);


echo '<h1>'. $item->data->name .'</h1>';


echo '<div><img src="img/items/'. $item->row->name .'.webp" /></div>';

echo '<p>'. implode(', ', Item::get_item_carac($item->data)) .'</p>';

echo '<p>'. $item->data->text .'</p>';


echo '<p><img src="img/items/or_mini.webp" style="width: 25px; margin-bottom: -0.5em" />'. $item->data->price .'Po</p>';


echo '<p><a href="https://age-of-olympia.net/wiki/doku.php?id=univers:objets">Liste des objets</a></p>';
