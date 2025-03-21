<?php

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

function ExecuteCommand($command, $commandLineSplit)
{
    if (isset($commandLineSplit[0]) && ($commandLineSplit[0] === 'help' || $commandLineSplit[0] === '--help')) {
        $result = '<a href="https://age-of-olympia.net/wiki/doku.php?id=v4:console#' . $command->getName() . '">' . $command->getName() . '</a> ' . $command->printArguments() . "<br/>"
            . $command->getDescription();

        $command->result->Log('Help for command ' . $command->getName() . ': ');
        $command->result->Log($result);
    } else {
        if (count($commandLineSplit) >= $command->getRequiredArgumentsCount()) {
            try {
                $command->result->Log('command found ' . $command->getName() . '. Executing...');
                $resultstr = $command->executeIfAuthorized($commandLineSplit);
                if (!empty($resultstr)) {
                    if (startsWithIgnoreCase($resultstr, "error")) {
                        $command->result->Error($resultstr);
                    } else {
                        $command->result->Log($resultstr);
                    }
                }
            } catch (Throwable $e) {
                $command->result->Error("Unexpected technical error, check command syntax : " . $command->getName() . " " . $command->printArguments());
                if($e instanceof ErrorException || $e instanceof Error)
                    throw $e;
                $command->result->Error( $e->getMessage());
            }
        } else {
            $command->result->Error('missing mandatory arguments ' . $command->printArguments());
        }
    }
}

//execution
if (isset($_POST['cmdLine']) && !isset($_POST['completion'])) {
    $inputString = $_POST['cmdLine'];

    $factory = initCommmandFactory();

    $GLOBALS['consoleENV'] = ['self' => $_SESSION['playerId']];
    $commandsList = Command::getCommandsFromInputString($inputString);
    $commandsResults = new CommandResult();
    if (count($commandsList) == 0) {
        $commandsResults->Error("Failed to parse command line");
    }
    $dbconn = db();
    $dbconn->beginTransaction();
    try {
        for ($i = 0; $i < count($commandsList); $i++) {
            $subCommands = Command::ReplaceEnvVariable($commandsList[$i]);

            foreach ($subCommands as $commandLine) {

                $commandLineSplit = Command::getCommandLineSplit($commandLine);
                $commandeName = $commandLineSplit[0];
                $command = $factory->getCommand($commandeName);
                array_shift($commandLineSplit); //remove first part

                if ($command) {
                    $command->result = $commandsResults;//@todo create a new result for each command child base system that is compatible with exeptions 
                    $command->db = $dbconn;
                    ExecuteCommand($command, $commandLineSplit);
                } else {
                    $commandsResults->Error('Unknown command ' . $commandeName);
                }
                if ($commandsResults->hasError()) {

                    if (Command::getEnvVariable("revertMode", "all") == 'all') {
                        throw new Exception('');
                    }
                    $commandsResults->Error('Command ' . $commandeName . ' failed, stopping execution, ' . strval(count($commandsList) - 1 - $i) . ' ommited');
                    break;
                }
            }
        }
        $dbconn->commit();
    } catch (Throwable $e) {
        if(Command::getEnvVariable("debug", "off") == 'on')
        {
            $commandsResults->Error($e->__toString());
        }
        if ($e->getMessage() != '') {
            $commandsResults->Error($e->getMessage());

            $commandsResults->Error('L:'.$e->getLine().' File: '.$e->getFile());

        }

        $commandsResults->Error('faillure revert all changes');
        $dbconn->rollBack();
    }
    // echo results
    echo json_encode($commandsResults->getResults());

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