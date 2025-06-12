<?php
namespace Classes;
use ReflectionClass;

class CommandFactory
{
    private $commands = [];

    public function register(Command $command) {
        $this->commands[strtolower($command->getName())] = $command;
    }

    public function getCommand($commandName):?Command {
        $commandName = strtolower($commandName);
        if (array_key_exists($commandName, $this->commands)) {
            return $this->commands[$commandName];
        }
        return null;
    }

    public function getCommandsStartingWith($prefix) : array{
        $matchingCommands = [];
        $prefix = strtolower($prefix);
        
        foreach ($this->commands as $commandName => $command) {
            if (strpos($commandName, $prefix) === 0) {
                $matchingCommands[] = $command->getName();
            }
        }

        return $matchingCommands;
    }
    public function getCommands() : array{
        return $this->commands;
    }

    public static function initCommmandFactory(): CommandFactory
    {
        $factory = new CommandFactory();

        // Register all commands in Classes/console-commands
        foreach (glob(dirname(__DIR__) . '/Classes/console-commands/*cmd.php') as $filename) {
            require_once $filename;

            $className = basename($filename, '.php');

            if (class_exists($className)) {
                $reflectionClass = new ReflectionClass($className);
                if ($reflectionClass->isInstantiable()) {
                    $commandInstance = $reflectionClass->newInstance();
                    $commandInstance->setFactory($factory);
                    $factory->register($commandInstance);
                }
            }
        }

        return $factory;
    }
}


