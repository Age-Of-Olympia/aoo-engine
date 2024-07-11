<?php
abstract class Command
{
    private string $name;
    private array $arguments;
    private CommandFactory $factory;

    public function __construct(string $name, array $arguments = [])
    {
        $this->name = $name;
        $this->arguments = $arguments;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getArguments(): array
    {
        return $this->arguments;
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
            $argumentsStr.= " -" . $arg->getName(). $optional ;
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

    abstract public function execute(  array $argumentValues ): string;
}
