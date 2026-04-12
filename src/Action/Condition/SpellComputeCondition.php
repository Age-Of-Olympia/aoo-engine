<?php
namespace App\Action\Condition;

class SpellComputeCondition extends TechniqueComputeCondition
{

    public function __construct()
    {
        parent::__construct();
        array_push($this->preConditions, new AntiSpellCondition());
    }
    
}