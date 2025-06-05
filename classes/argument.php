<?php
namespace Classes;

class Argument
{
    private string $name;
    private bool $optional;

    public function __construct(string $name, bool $optional = false)
    {
        $this->name = $name;
        $this->optional = $optional;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isOptional(): bool
    {
        return $this->optional;
    }

}
