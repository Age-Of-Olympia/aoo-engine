<?php
use App\Factory\PlayerFactory;
use App\Service\AccountDeletionService;
use App\View\AccountView;
use Classes\Ui;
use Classes\Str;
use Classes\File;
use Classes\Db;
use App\Tutorial\TutorialFeatureFlag;
use App\Tutorial\TutorialSessionManager;

require_once('config.php');


$player = PlayerFactory::legacy($_SESSION['playerId']);

$player->get_data();

$ui = new Ui('Options du Profil', true);


ob_start();


if(isset($_GET['portraits'])){

    include('scripts/account/portraits.php');
    exit();
}

if(isset($_GET['avatars'])){

    include('scripts/account/avatars.php');
    exit();
}

if(isset($_GET['mdj'])){

    include('scripts/account/mdj.php');
    exit();
}

if(isset($_GET['story'])){

    include('scripts/account/story.php');
    exit();
}

if(isset($_GET['changeMail'])){
    include('scripts/account/change_mail.php');
    exit();
}

if(isset($_GET['changePsw'])){
    $player->get_data();
    include('scripts/account/change_psw.php');
    exit();
}

if(isset($_POST['changeName'])){

    include('scripts/account/change_name.php');
    exit();
}

$options = AccountView::buildOptions($player);

define('OPTIONS', $options);



if(!empty($_POST['option'])){


    if(!isset(OPTIONS[$_POST['option']])){

        exit('error option');
    }

    if($_POST['option']=='incognitoMode' || $_POST['option']=='anonymeMode')
    {
       if($player->id>=0)
           exit('error option for pnj');
    }

    $player->refresh_view();

    /* Capture toggle direction BEFORE mutating the option set. */
    $wasEnabled = (bool) $player->have_option($_POST['option']);

    if($wasEnabled){

        $player->end_option($_POST['option']);
    }
    else{

        $player->add_option($_POST['option']);
    }

    /* deleteAccount is more than a preference: stamp the request date and
       alert the admin team so the 7-day deletion window can be honoured. */
    if($_POST['option'] === 'deleteAccount'){

        $deletionService = new AccountDeletionService();

        if($wasEnabled){

            $deletionService->cancelDeletion(
                $player->id,
                $player->data->plain_mail ?? null
            );
        }
        else{

            $deletionService->requestDeletion(
                $player->id,
                $player->data->name,
                $player->data->plain_mail ?? null
            );
        }
    }

    exit();
}


AccountView::render($player, $options);

?>


<?php

$content = ob_get_clean();
echo Str::minify($content);
