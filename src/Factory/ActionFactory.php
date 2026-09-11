<?php

namespace App\Factory;

use App\Interface\ActionInterface;
use App\Service\ActionService;

class ActionFactory
{
    public static function getAction(string $name): ?ActionInterface
    {
        // keep by type for melee and shoot
        // add by name fot the others
        $actionService = new ActionService();
        $action = $actionService->getActionByName($name);
        return $action;
    }
}
