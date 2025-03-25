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

}
