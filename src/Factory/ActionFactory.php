<?php

namespace App\Factory;

use App\Entity\Action;
use App\Service\ActionService;

class ActionFactory
{
    public static function getAction(string $name): ?Action
    {
        // keep by type for melee and shoot
        // add by name fot the others
        $actionService = new ActionService();
        $action = $actionService->getActionByName($name);
        return $action;
    }
}
