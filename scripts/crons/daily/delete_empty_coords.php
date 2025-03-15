<?php

// delete empty coords (except #1)
// $sql = '
// DELETE FROM
// coords
// WHERE
// id NOT IN (
// SELECT coords_id FROM players
// UNION
// SELECT coords_id FROM map_elements
// UNION
// SELECT coords_id FROM map_tiles
// UNION
// SELECT coords_id FROM map_foregrounds
// UNION
// SELECT coords_id FROM map_triggers
// UNION
// SELECT coords_id FROM map_walls
// UNION
// SELECT coords_id FROM map_dialogs
// UNION
// SELECT coords_id FROM map_plants
// UNION
// SELECT coords_id FROM map_items
// UNION
// SELECT coords_id FROM players_logs
// UNION
// SELECT coords_id FROM players_logs_archives
// )
// AND
// id != 1
// ';

// $db->exe($sql);


echo 'done';
