<?php

if($position == 'last'){

    $sql = '
    UPDATE
    map_foregrounds
    SET
    coords_id = ?
    WHERE
    id = ?
    ';

    $db->exe($sql, array($player->data->coords_id, $foreground_id));
}

elseif($position == 'on'){

    $sql = '
    UPDATE
    map_foregrounds
    SET
    coords_id = ?
    WHERE
    id = ?
    ';

    $db->exe($sql, array($coordsId, $foreground_id));
}
