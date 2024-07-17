<?php


$val = 'type';


$db = new Db();

$sql = 'SELECT * FROM items';

$res = $db->exe($sql);

while($row = $res->fetch_object()){


    echo $row->name .': ';


    $itemV4 = json()->decode('items', $row->name);


    $itemV3 = json()->decode('item', $row->name);


    echo '<br />';
}
