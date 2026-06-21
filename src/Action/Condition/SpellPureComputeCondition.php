<?php
namespace App\Action\Condition;

use Classes\Dice;

class SpellPureComputeCondition extends TechniquePureComputeCondition
{
    protected string $throwName = "Le sort";

    public function __construct(?Dice $dice = null)
    {
        parent::__construct($dice);
        array_push($this->preConditions, new AntiSpellCondition());
    }
    
}