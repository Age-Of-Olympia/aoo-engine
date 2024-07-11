<?php

class CommandFactory
{
    private $commands = [];

    public function register(Command $command) {
        $this->commands[$command->getName()] = $command;
    }

    public function getCommand($commandName) {
        if (array_key_exists($commandName, $this->commands)) {
            return $this->commands[$commandName];
        }
        return null;
    }

    public function getCommandsStartingWith($prefix) : array{
        $matchingCommands = [];

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
}


