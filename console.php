<?php

define('NO_LOGIN', true);

require_once('config.php');
require_once('config/config-console.php');


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
        if (count($commandLineSplit) >= $command->getRequiredArgumentsCount()) {
            echo json_encode(['message' => 'command found '. $command->getName().'. Executing.',
                'result' => $command->execute($commandLineSplit)]);
        }else{
            echo json_encode(['error' => 'missing mandatory arguments '.$command->printArguments()]);
        }


    }else{
        echo json_encode(['error' => 'Unknown command']);
    }

    //Todo trace command

}
