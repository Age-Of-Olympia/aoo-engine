<?php

class HelpCmd extends Command
{
    public function __construct() {
        parent::__construct("help",[new Argument('command',true)]);
    }

    public function execute(  array $argumentValues ) : string
    {
        $output = '';
        foreach (parent::getFactory()->getCommands() as $command){
            if (!isset($argumentValues[0]) || (strpos($command->getName(), $argumentValues[0]) === 0)) { //if start with provided second argument, filter
                $output.=$command->getName(). ' ' .$command->printArguments().'<br/>';
            }
        }
        return $output;
    }
}
