<?php


// delete altar trigger (it's not linked to a structure anymore)
$db->delete('map_triggers', array('id'=>$triggerId));
