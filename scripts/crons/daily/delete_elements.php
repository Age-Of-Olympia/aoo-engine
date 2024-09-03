<?php

$sql = 'DELETE FROM map_elements WHERE endTime != 0 AND endTime <= ?';
$db->exe($sql, time());

echo 'done';
