<?php

namespace Tests\Action\Mock;

class ActionMock
{
    private string $name;
    private string $type;
    
    public function __construct(string $name = 'TestAction', string $type = 'action')
    {
        $this->name = $name;
        $this->type = $type;
    }
    
    public function getName(): string
    {
        return $this->name;
    }
    
    public function getType(): string
    {
        return $this->type;
    }
}