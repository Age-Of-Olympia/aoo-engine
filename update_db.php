<?php


require_once('config.php');


$player = new Player($_SESSION['playerId']);


if(!$player->have_option('isAdmin')){

    exit('error not admin');
}


$updateDir = 'db/updates';

$doneDir = 'db/updates_done';

$doneFiles = File::scan_dir($doneDir);


$sql = '';


foreach(File::scan_dir($updateDir) as $e){


    echo $e;


    if(in_array($e, $doneFiles)){


        echo ' skipped';
    }

    else{


        $data = file_get_contents($updateDir .'/'. $e);

        $sql .= $data;


        // cp this update to updates_done
        $myfile = fopen($doneDir .'/'. $e, "w") or die("Unable to open file!");
        fwrite($myfile, $data);
        fclose($myfile);


        echo ' <font color="blue">done!</font>';
    }


    echo '<br />';
}



if($sql != ''){

    db()->multi_query($sql);

    echo '<font color="red">db updated</font>';
}

else{

    echo 'db not updated';
}
