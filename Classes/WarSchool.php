<?php
namespace Classes;

class WarSchool
{
    private $trainer;          // l'entraîneur


    public function __construct($trainer)
    {
        $this->trainer = $trainer;
    }


    public function hasTrainer(): bool
    {
        return $this->trainer !== null;
    }


    public function getTrainer()
    {
        return $this->trainer;
    }

}
