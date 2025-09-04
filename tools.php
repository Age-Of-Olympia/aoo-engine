<?php
use Classes\Player;
use Classes\File;
use App\Service\AdminAuthorizationService;
require_once('config.php');

$player = new Player($_SESSION['playerId']);

AdminAuthorizationService::DoSuperAdminCheck();

$filesTbl = File::scan_dir('scripts/tools/', without:'.php');

foreach($filesTbl as $e){


    if(isset($_GET[$e])){


        include('scripts/tools/'. $e .'.php');

        exit();
    }
}

exit('script not found');
