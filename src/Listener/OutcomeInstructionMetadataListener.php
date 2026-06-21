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
        $directory = __DIR__ . '/../Action/OutcomeInstruction'; // Absolute path to OutcomeInstruction directory
        foreach (glob("$directory/*OutcomeInstruction.php") as $file) {
            $className = basename($file, '.php');
            $fullClassName = "App\\Action\\OutcomeInstruction\\$className";
            if (!class_exists($fullClassName)) {
                require_once $file;
            }
            $metadata->discriminatorMap[\App\Action\OutcomeInstruction\OutcomeInstructionFactory::discriminatorKey($className)] = $fullClassName;

            // Register concrete subclasses so STI root queries (e.g. the one in
            // OutcomeInstructionService) include their discriminators in the
            // generated `type IN (...)` filter. Unlike Action, OutcomeInstruction
            // has no #[DiscriminatorMap] attribute for Doctrine to derive these
            // from — without this the filter is just the base 'outcomeinstruction'
            // and every real instruction row (lifeloss, healing, …) is excluded.
            if ($fullClassName !== OutcomeInstruction::class && !in_array($fullClassName, $metadata->subClasses, true)) {
                $metadata->subClasses[] = $fullClassName;
            }
        }
    }
}
