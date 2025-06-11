<?php
use Classes\Console;

define('NO_LOGIN', true);

require_once('config.php');
require_once('config/config-console.php');


// check session
if(!isset($_SESSION['playerId'])){

    echo 'login required';
    exit();
}

include $_SERVER['DOCUMENT_ROOT'] . '/checks/admin-check.php';
set_error_handler("warning_handler", E_WARNING);
function warning_handler($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
}
// get cmd history
if(!empty($_POST['cmdHistory'])){

    if(!empty($_SESSION['cmdHistory'])){

        echo implode("|", $_SESSION['cmdHistory']);
    }

    exit();
}


//auto-complete
if (isset($_POST['cmdLine']) && isset($_POST['completion'])){
    $inputString = $_POST['cmdLine'];

    $factory = initCommmandFactory();


    if(sizeof($factory->getCommandsStartingWith($inputString))>0){

        echo json_encode(['suggestions' => $factory->getCommandsStartingWith($inputString) ]);

    }else{
        echo json_encode(['suggestions' => '']);
    }
}



//execution
if (isset($_POST['cmdLine']) && !isset($_POST['completion'])) {
    $inputString = $_POST['cmdLine'];

    $console = new Console();
    $console->InitAndExec($inputString);

    // echo results
    echo json_encode($console->commandsResults->getResults());

    // history command
    if(!isset($_SESSION['cmdHistory'])){

        $_SESSION['cmdHistory'] = array();
    }

    if(count($console->commandsList) == 1)
        $_SESSION['cmdHistory'][] = $_POST['cmdLine'];


    // track
    $path = 'datas/private/console/';

    if(!file_exists($path)){

        mkdir($path, 0775, true); // recursive = true
    }

    // Chemin vers le fichier csv
    $logFile = $path .'track.csv';

    if(!file_exists($logFile)){

        // Ouvrir le fichier en mode ajout
        $fileHandle = fopen($logFile, 'a');

        if ($fileHandle === false) {
            echo json_encode(['error' => 'unable to write track.csv']);
        }

        // Convertir $result en ligne CSV et l'écrire dans le fichier
        fputcsv($fileHandle, array('mainPlayerId','playerId','time','Y/m/d','H:i:s','cmdLine','result'));

        // Fermer le fichier
        fclose($fileHandle);
    }

    // Ouvrir le fichier en mode ajout
    $fileHandle = fopen($logFile, 'a');

    if ($fileHandle === false) {
        echo json_encode(['error' => 'unable to write track.csv']);
    }

    foreach($console->commandsResults as $result){
        $resultTxt = isset($result['message']) ? $result['message'] : 'No result';
        $log = array(
            'mainPlayerId'=>$_SESSION['mainPlayerId']??0,
            'playerId'=>$_SESSION['playerId']??0,
            'time'=>time(),
            'Y/m/d'=>date('Y/m/d', time()),
            'H:i:s'=>date('H:i:s', time()),
            'cmdLine'=>$_POST['cmdLine'],
            'result'=>$resultTxt
        );
        // Convertir $result en ligne CSV et l'écrire dans le fichier
        fputcsv($fileHandle, $log);
    }
  

    // Fermer le fichier
    fclose($fileHandle);
}
function startsWithIgnoreCase($string, $startString) {
    return stripos($string, $startString) === 0;
}