<?php

define('NO_LOGIN', true);

require_once('config.php');
require_once('config/config-console.php');


// check session
if(!isset($_SESSION['playerId'])){

    echo 'login required';
    exit();
}

include $_SERVER['DOCUMENT_ROOT'].'/checks/admin-check.php';

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

function ExecuteCommand($command, $commandLineSplit)
{
    if(isset($commandLineSplit[0]) &&($commandLineSplit[0] === 'help' || $commandLineSplit[0] === '--help')){
        $result = '<a href="https://age-of-olympia.net/wiki/doku.php?id=v4:console#'. $command->getName(). '">'. $command->getName(). '</a> ' .$command->printArguments()."<br/>"
        .$command->getDescription();
        return ['message' => 'Help for command ' . $command->getName() . ': ',
                'result' => $result];
    }
    else {
        if (count($commandLineSplit) >= $command->getRequiredArgumentsCount()) {
            try{
                $result = $command->executeIfAuthorized($commandLineSplit);
               return ['message' => 'command found ' . $command->getName() . '. Executing.',
                       'result' => $result];
            }catch(Throwable $e){
                $result = "Unexpected technical error, check command syntax : ".$command->getName()." ".$command->printArguments() 
                . "  - Error details : ". $e->getMessage();
                
                return ['error' => $result];
            }
        } else {
            $result = 'missing mandatory arguments ' . $command->printArguments();
           
            return ['error' => $result];
        }
    }
}

//execution
if (isset($_POST['cmdLine']) && !isset($_POST['completion'])) {
    $inputString = $_POST['cmdLine'];

    $factory = initCommmandFactory();
    $GLOBALS['consoleENV'] = ['self' => $_SESSION['playerId']];
    $commandsList = Command::getCommandsFromInputString($inputString);
    $commandsResults = array();
    if(count($commandsList) == 0){
        $commandsResults[] = ['error' => "Failed to parse command line"];
    }

    for ($i = 0; $i < count($commandsList); $i++){
        $commandLine = Command::ReplaceEnvVarriable($commandsList[$i]);
        $commandLineSplit = Command::getCommandLineSplit($commandLine);
        $commandeName = $commandLineSplit[0];
        $command = $factory->getCommand($commandeName);
        array_shift($commandLineSplit); //remove first part
       
        if($command){
            $commandsResults[] = ExecuteCommand($command, $commandLineSplit);
        }else{
            $error = 'Unknown command ' . $commandeName;
            $commandsResults[] = ['error' => $error];
        }
        if(isset($commandsResults[count($commandsResults)-1]['error'])){
            if($i < count($commandsList) - 1){
                $commandsResults[] = ['error' => 'Command ' . $commandeName . ' failed, stopping execution, '.strval(count($commandsList) -1 - $i).' ommited'];
            }
            break;
        }
    }

    // echo results
    echo json_encode($commandsResults);

    // history command
    if(!isset($_SESSION['cmdHistory'])){

        $_SESSION['cmdHistory'] = array();
    }

    if(count($commandsList) == 1)
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

    foreach($commandsResults as $result){
        $resultTxt = isset($result['result']) ? $result['result'] : (isset($result['error']) ? $result['error'] : 'No result');
        $log = array(
            'mainPlayerId'=>$_SESSION['mainPlayerId'],
            'playerId'=>$_SESSION['playerId'],
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
