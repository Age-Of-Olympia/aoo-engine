<?php
namespace App\Action\Condition;

use App\Interface\ConditionInterface;

abstract class BaseCondition implements ConditionInterface
{
    public function toRemove(): bool {
        return false;
    }
}