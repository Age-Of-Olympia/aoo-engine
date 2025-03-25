<?php

namespace App\Listener;

use App\Entity\OutcomeInstruction;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;

class OutcomeInstructionMetadataListener {
    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs) {
        $metadata = $eventArgs->getClassMetadata();

        if ($metadata->getName() === OutcomeInstruction::class) {
            $this->updateDiscriminatorMap($metadata);
        }
    }

    private function updateDiscriminatorMap(ClassMetadata $metadata) {
        $directory = 'src/Action/OutcomeInstruction'; // Chemin vers le répertoire des OutcomeInstructions
        foreach (glob("$directory/*OutcomeInstruction.php") as $file) {
            $className = basename($file, '.php');
            $fullClassName = "App\\Action\\OutcomeInstruction\\$className";
            if (!class_exists($fullClassName)) {
                require_once $file;
            }
            $metadata->discriminatorMap[strtolower(substr($className, 0, -18))] = $fullClassName;
        }
    }
}
