<?php
namespace App\Action\Condition;

class SpellPureComputeCondition extends TechniquePureComputeCondition
{
    protected string $throwName = "Le sort";

    public function __construct()
    {
        parent::__construct();
        array_push($this->preConditions, new AntiSpellCondition());
    }
    
}