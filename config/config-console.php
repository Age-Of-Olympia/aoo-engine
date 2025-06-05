<?php
use Classes\CommandFactory;

function initCommmandFactory() : CommandFactory
{
    $factory = new CommandFactory();

    // Register all commands in classes/console-commands
    foreach (glob(dirname(__DIR__). '/classes/console-commands/*cmd.php' ) as $filename) {
        require_once $filename;

        $className = basename($filename, '.php');

        if (class_exists($className)) {
            $reflectionClass = new ReflectionClass($className);
            if ($reflectionClass->isInstantiable()) {
                $commandInstance = $reflectionClass->newInstance();
                $commandInstance->setFactory($factory);
                $factory->register($commandInstance);
            }
        }
    }

    return $factory;
}
