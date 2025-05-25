<?php

namespace App\Action;

use App\Interface\ActionInterface;
use App\Service\ActionService;

function loadActionClasses($directory)
{
    $classes = [];
    foreach (glob("$directory/*Action.php") as $file) {
        $className = basename($file, '.php');
        $classes[$className] = $className;
    }
    return $classes;
}

class ActionFactory
{
    private static $actionClasses = [];

    public static function initialize($directory)
    {
        self::$actionClasses = loadActionClasses($directory);
    }

    public static function getAction(string $name): ?ActionInterface
    {
        // keep by type for melee and shoot
        // add by name fot the others
        $actionService = new ActionService();
        $action = $actionService->getActionByName($name);
        return $action;
    }
}
