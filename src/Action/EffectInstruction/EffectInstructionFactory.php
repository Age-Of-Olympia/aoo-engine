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

    // public static function getAllEffectInstructions(): array
    // {  
    //     $result = array();
    //     foreach (self::$EffectInstructionClasses as $type) {
    //         array_push($result, $type);
    //     } 
    //     return $result;
    // }

    // public static function getEffectInstruction($type): EffectInstructionInterface
    // {
    //     $EffectInstructionService = new EffectInstructionService();
    //     if (isset(self::$EffectInstructionClasses[$type])) {
    //         return $EffectInstructionService->getEffectInstructionByTypeByEffect($type);;
    //     }
    //     throw new Exception("EffectInstruction type not found: $type");
    // }


}
