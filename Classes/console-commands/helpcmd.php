<?php
use Classes\Command;
use Classes\Argument;


class HelpCmd extends Command
{
    public function __construct() {
        parent::__construct("help",[new Argument('command',true)]);
        parent::setDescription(<<<EOT
Affiche les commandes possibles et leurs arguments
Exemple:
> help tp
EOT
        );
    }

    public function execute(  array $argumentValues ) : string
    {
        $output = '';
        foreach (parent::getFactory()->getCommands() as $command){
            if (!isset($argumentValues[0]) || (strpos($command->getName(), $argumentValues[0]) === 0)) { //if start with provided second argument, filter
                $output.= '<a href="https://age-of-olympia.net/wiki/doku.php?id=v4:console#'. $command->getName(). '">'. $command->getName(). '</a> ' .$command->printArguments().'<br/>';
            }
        }
        return $output;
    }
}
