<?php

namespace App\Action\EffectInstruction;

use App\Interface\EffectInstructionInterface;
use App\Service\EffectInstructionService;
use Exception;

function loadEffectInstructionClasses($directory)
{
    $classes = [];
    foreach (glob("$directory/*EffectInstruction.php") as $file) {
        $className = basename($file, '.php');
        $classes[$className] = $className;
    }
    return $classes;
}

class EffectInstructionFactory
{
    private static $EffectInstructionClasses = [];

    public static function initialize($directory): array
    {
        self::$EffectInstructionClasses = loadEffectInstructionClasses($directory);
        return self::$EffectInstructionClasses;
    }

}
