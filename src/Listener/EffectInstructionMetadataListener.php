<?php

namespace App\Listener;

use App\Entity\EffectInstruction;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;

class EffectInstructionMetadataListener {
    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs) {
        $metadata = $eventArgs->getClassMetadata();

        if ($metadata->getName() === EffectInstruction::class) {
            $this->updateDiscriminatorMap($metadata);
        }
    }

    private function updateDiscriminatorMap(ClassMetadata $metadata) {
        $directory = 'src/Action/EffectInstruction'; // Chemin vers le répertoire des EffectInstructions
        foreach (glob("$directory/*EffectInstruction.php") as $file) {
            $className = basename($file, '.php');
            $fullClassName = "App\\Action\\EffectInstruction\\$className";
            if (!class_exists($fullClassName)) {
                require_once $file;
            }
            $metadata->discriminatorMap[strtolower(substr($className, 0, -17))] = $fullClassName;
        }
    }
}
