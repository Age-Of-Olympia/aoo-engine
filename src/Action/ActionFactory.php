<?php

namespace App\Action;

use App\Interface\ActionInterface;
use App\Service\ActionService;
use Exception;

function loadActionClasses($directory)
{
    $classes = [];
    foreach (glob("$directory/*Action.php") as $file) {
        $className = basename($file, '.php');
        //require_once $file;
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

    public static function getAction(string $type, ?string $name = null): ActionInterface
    {
        $actionService = new ActionService();
        $className = ucfirst(strtolower($type)) . 'Action';
        if (isset(self::$actionClasses[$className])) {
            return $actionService->getActionByTypeByName($type, $name);;
        }
        throw new Exception("Action type not found: $type");
    }
}
