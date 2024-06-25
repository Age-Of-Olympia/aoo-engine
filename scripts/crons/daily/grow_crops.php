<?php


$sql = '
SELECT
map_items.id AS id,
map_items.item_id,
map_items.coords_id,
coords.z AS z
FROM
map_items
INNER JOIN
coords
ON
coords_id = coords.id
WHERE
n = 1
';

$db = new Db();

$res = $db->exe($sql);

while($row = $res->fetch_object()){


    $item = new Item($row->item_id);
    $item->get_data();


    if($item->data->growZMin > $row->z){

        continue;
    }

    if(!empty($item->data->type) && $item->data->type == 'graine'){


        $growTo = $item->data->growTo;

        shuffle($growTo);

        $growTo = array_pop($growTo);


        if(AUTO_GROW || rand(1,$growTo->chance) == 1){


            $values = array(
                'name'=>$growTo->name,
                'coords_id'=>$row->coords_id
            );

            $db->insert('map_'. $growTo->table, $values);


            $values = array('id'=>$row->id);

            $db->delete('map_items', $values);
        }
    }
}
