<?php
use App\Service\PlantsService;
use Classes\Db;
use Classes\Item;

// cells holding a single item, one unit, on free soil: anything else on the
// ground, a stack included, keeps the seed from growing
$sql = '
SELECT
SUM(map_items.n) as total,
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
WHERE ' . PlantsService::freeSoilSql('map_items.coords_id') . '
GROUP BY map_items.coords_id
HAVING total =1
';

$db = new Db();

$res = $db->exe($sql);

$grown = array();

while($row = $res->fetch_object()){


    $item = new Item($row->item_id);
    $item->get_data();


    /* isset, pas !empty : growZMin = 0 est une contrainte (« ne germe
     * pas sous le niveau de la mer »), pas une absence de contrainte.
     * Avec !empty, une graine réglée sur 0 germait aussi en z négatif —
     * le champ admin était sans effet à cette valeur. */
    if(isset($item->data->growZMin) && $item->data->growZMin > $row->z){

        continue;
    }

    if(!empty($item->data->type) && $item->data->type == 'graine'){


        $growTo = $item->data->growTo;

        shuffle($growTo);

        $growTo = array_pop($growTo);


        if(AUTO_GROW || rand(1,$growTo->chance) == 1){


            PlantsService::sprout($growTo->name, $growTo->table, (int) $row->coords_id);


            $values = array('id'=>$row->id);

            $db->delete('map_items', $values);

            $grown[] = (int) $row->coords_id;
        }
    }
}

\App\Service\Map\BoardChanges::cellId(...$grown);


echo 'done';
