<?php

if($position == 'last'){

    $sql = '
    UPDATE
    map_tiles
    SET
    coords_id = ?
    WHERE
    id = ?
    ';

    $db->exe($sql, array($player->data->coords_id, $tile_id));
}

elseif($position == 'on'){

    $sql = '
    UPDATE
    map_tiles
    SET
    coords_id = ?
    WHERE
    id = ?
    ';

    $db->exe($sql, array($coordsId, $tile_id));
}
