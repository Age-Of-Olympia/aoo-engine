<?php
namespace Classes;

use Exception;

abstract class Command
{
    private string $name;
    private string $description;
    private array $arguments;
    private CommandFactory $factory;
    public CommandResult $result;
    public Console $console;
    public $db;
    
    public function __construct(string $name, array $arguments = [])
    {
        $this->name = $name;
        $this->arguments = $arguments;
        $this->description = "";
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function setDescription(string $description){
        $this->description = $description;
    }

    public function getDescription(): string{
        return $this->description ;
    }

    public function setFactory($factory){
        $this->factory = $factory;
    }

    public function getFactory() : CommandFactory{
        return $this->factory;
    }

    public function getRequiredArgumentsCount() : int {
        $count = 0;
        foreach ($this->arguments as $arg) {
            if (!$arg->isOptional()) {
                $count++;
            }
        }
        return $count;
    }

    public function printArguments() :string {
        $argumentsStr = '';
        if(sizeof($this->arguments) ===0){
            $argumentsStr = ' - No arguments required';
        }
        foreach ($this->arguments as $arg) {
            $optional = $arg->isOptional() ? ' (optional)' : ' (required)';
            $argumentsStr.= " - " . $arg->getName(). $optional ;
        }
        return $argumentsStr;
    }

    public function getPlayer( $playerIdOrName) {
        if(is_numeric($playerIdOrName)){
            $player = new Player($playerIdOrName);
        }
        else{
            $player = Player::get_player_by_name($playerIdOrName);
        }
        return $player;
    }

    public static function getCommandLineSplit($inputString){

        preg_match_all('/"(?:\\\\.|[^\\\\"])*"|\S+/', $inputString, $matches);

        $commandLineSplit = $matches[0];

        // Nettoyer les guillemets des arguments entourés de guillemets
        $commandLineSplit = array_map(function($arg) {
            return trim($arg, '"');
        }, $commandLineSplit);

        return $commandLineSplit;
    }

    public static function getCommandsFromInputString($inputString){
        preg_match_all('/(?:[^";]|"(?:\\\\.|[^\\\\"])*")++/', $inputString, $matches);
        return $matches[0];
    }

    public static function ReplaceEnvVariable($commandLine){
        $commandLine=trim($commandLine);
        $subCommands= array();
        $didAnArray=false;
        $arrayVarToProcess= array();
        //dirty hack no replace in savescript command
        if (stripos($commandLine, "savescript") !=0) {
            foreach ($GLOBALS[consoleEnvKey] as $key => $value) {
                $needle = '{' . $key . '}';
                if (strpos($commandLine, $needle) !== false) {
                    if (is_array($value)) {
                        $arrayVarToProcess[$key] = $value;
                    } else {
                        $commandLine = str_replace($needle, $value, $commandLine);
                    }
                }
            }
            foreach ($arrayVarToProcess as $key => $value) {
                $needle = '{' . $key . '}';

                if ($didAnArray) {
                    throw new Exception('Only one array is allowed in a command line');
                }
                foreach ($value as $subValue) {
                    $subCommands[] = str_replace($needle, $subValue, $commandLine);
                }
                $didAnArray = true;
            }
        }
        if(empty($subCommands)){
            $subCommands[]= $commandLine;
        }
        return $subCommands;
    }
    public static function GetEnvVariable($name, $defaultValue)
    {
        if(isset($GLOBALS[consoleEnvKey][$name]))
            return $GLOBALS[consoleEnvKey][$name];

            return $defaultValue;
    }

    public static function SetEnvVariable($name, $newValue)
    {
        $GLOBALS[consoleEnvKey][$name]=$newValue;
    }

    abstract public function execute(  array $argumentValues ): string;

    public function executeIfAuthorized( array $argumentValues ): string {
        return $this->execute($argumentValues);
    }
}
