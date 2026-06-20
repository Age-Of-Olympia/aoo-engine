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
     * Maps each STI discriminator key to its fully-qualified class.
     *
     * @return array<string, class-string>
     */
    public static function typeMap(): array
    {
        $map = [];
        foreach (glob(__DIR__ . '/*OutcomeInstruction.php') as $file) {
            $className = basename($file, '.php');
            $map[self::discriminatorKey($className)] = "App\\Action\\OutcomeInstruction\\$className";
        }

        return $map;
    }

    /**
     * The single source of truth for the OutcomeInstruction STI discriminator key:
     * the lowercased class name without the "OutcomeInstruction" suffix.
     */
    public static function discriminatorKey(string $className): string
    {
        $shortName = str_contains($className, '\\') ? substr(strrchr($className, '\\'), 1) : $className;

        return strtolower(substr($shortName, 0, -18));
    }

    public static function typeOf(object $instruction): string
    {
        return self::discriminatorKey((new \ReflectionClass($instruction))->getShortName());
    }

}
