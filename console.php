<?php

define('NO_LOGIN', true);

require_once('config.php');
require_once('config/config-console.php');


// check session
if(!isset($_SESSION['playerId'])){

    echo 'login required';
    exit();
}


// check admin (only once per session)
if(!isset($_SESSION['isAdmin'])){

    // check admin
    $player = new Player($_SESSION['playerId']);
    if(!$player->have_option('isAdmin')){

        echo 'admin account required';
        exit();
    }
    else{

        $_SESSION['isAdmin'] = true;
    }
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

    $factory = initCommmandFactory();


    $commandLineSplit = explode(' ', $inputString);
    $command = $factory->getCommand($commandLineSplit[0]);
    array_shift($commandLineSplit); //remove first line
    if($command){

        if(isset($commandLineSplit[0]) &&($commandLineSplit[0] === 'help' || $commandLineSplit[0] === '--help')){
            $result = '<a href="https://age-of-olympia.net/wiki/doku.php?id=dev:console#'. $command->getName(). '">'. $command->getName(). '</a> ' .$command->printArguments()."<br/>"
            .$command->getDescription();
            echo json_encode(['message' => 'Help for command ' . $command->getName() . ': ',
                'result' => $result]);
        }
        else {
            if (count($commandLineSplit) >= $command->getRequiredArgumentsCount()) {
                $result = $command->execute($commandLineSplit);
                echo json_encode(['message' => 'command found ' . $command->getName() . '. Executing.',
                    'result' => $result]);
            } else {
                $result = 'missing mandatory arguments ' . $command->printArguments();
                $error = $result;
                echo json_encode(['error' => $result]);
            }
        }


    }else{
        $result = ['error' => 'Unknown command'];
        $error = $result['error'];
        echo json_encode($result);
    }


    // history command
    if(!isset($_SESSION['cmdHistory'])){

        $_SESSION['cmdHistory'] = array();
    }

    $_SESSION['cmdHistory'][] = $_POST['cmdLine'];


    // track
    $path = 'datas/private/console/';

    if(!file_exists($path)){

        mkdir($path, 0775, true); // recursive = true
    }

    $error = (!empty($error)) ? $error : $result;

    $log = array(
        'mainPlayerId'=>$_SESSION['mainPlayerId'],
        'playerId'=>$_SESSION['playerId'],
        'time'=>time(),
        'Y/m/d'=>date('Y/m/d', time()),
        'H:i:s'=>date('H:i:s', time()),
        'cmdLine'=>$_POST['cmdLine'],
        'result'=>$error
    );

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

    // Convertir $result en ligne CSV et l'écrire dans le fichier
    fputcsv($fileHandle, $log);

    // Fermer le fichier
    fclose($fileHandle);
}
