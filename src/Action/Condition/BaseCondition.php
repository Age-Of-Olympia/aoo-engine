<?php
namespace App\Action\Condition;

abstract class BaseCondition implements ConditionInterface
{
    public function toRemove(): bool {
        return false;
    }
}