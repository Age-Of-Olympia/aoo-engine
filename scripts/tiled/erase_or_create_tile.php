<?php
use Classes\Db;

if($_POST['type'] == 'eraser'){
    include 'erase_map.php';
} else {


    if(!in_array($_POST['type'], array('tiles','foregrounds','walls','triggers','elements','dialogs','plants','routes'))){

        exit('error type');
    }


    $values = array(
        'name'=>$_POST['src'],
        'coords_id'=>$coordsId
    );

    $db = new Db();

    echo $_POST['type'];

    $db->insert('map_'. $_POST['type'], $values);

    if(!empty($_POST['params'])){
        
        $lastId = $db->get_last_id('map_'. $_POST['type']);

        if( $_POST['type'] == 'walls'){
            //cas particulier des walls (resources avec damages)
    
            $sql = 'UPDATE map_walls SET damages = ? WHERE id = ?';
    
            $db->exe($sql, array($_POST['params'], $lastId));
    
        } else {
            //Autres tiles 
            $sql = 'UPDATE map_'. $_POST['type'] .' SET params = ? WHERE id = ?';
    
            $db->exe($sql, array($_POST['params'], $lastId));
    
            echo '
                params: '. $_POST['params'];
            
        }
    } 
}