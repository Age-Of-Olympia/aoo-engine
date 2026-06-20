<?php

namespace App\Action\OutcomeInstruction;

use App\Interface\OutcomeInstructionInterface;
use App\Service\OutcomeInstructionService;
use Exception;

function loadOutcomeInstructionClasses($directory)
{
    $classes = [];
    foreach (glob("$directory/*OutcomeInstruction.php") as $file) {
        $className = basename($file, '.php');
        $classes[$className] = $className;
    }
    return $classes;
}

class OutcomeInstructionFactory
{
    private static $OutcomeInstructionClasses = [];

    public static function initialize($directory): array
    {
        self::$OutcomeInstructionClasses = loadOutcomeInstructionClasses($directory);
        return self::$OutcomeInstructionClasses;
    }

    /**
     * Maps each STI discriminator key to its fully-qualified class, matching
     * the derivation in OutcomeInstructionMetadataListener.
     *
     * @return array<string, class-string>
     */
    public static function typeMap(): array
    {
        $map = [];
        foreach (glob(__DIR__ . '/*OutcomeInstruction.php') as $file) {
            $className = basename($file, '.php');
            $map[strtolower(substr($className, 0, -18))] = "App\\Action\\OutcomeInstruction\\$className";
        }

        return $map;
    }

}
