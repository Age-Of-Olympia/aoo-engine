<?php

require_once('config.php');

$player = new Player($_SESSION['playerId']);

if(!$player->have_option('isAdmin')){

    exit('error isAdmin');
}

$filesTbl = File::scan_dir('scripts/tools/', $without='.php');

foreach($filesTbl as $e){


    if(isset($_GET[$e])){


        include('scripts/tools/'. $e .'.php');

        exit();
    }
}

exit('script not found');
