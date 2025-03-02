<?php

namespace App\Listener;

use App\Entity\Action;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;

class ActionMetadataListener {
    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs) {
        $metadata = $eventArgs->getClassMetadata();

        if ($metadata->getName() === Action::class) {
            $this->updateDiscriminatorMap($metadata);
        }
    }

    private function updateDiscriminatorMap(ClassMetadata $metadata) {
        $directory = 'src/Action'; // Chemin vers le répertoire des actions
        foreach (glob("$directory/*Action.php") as $file) {
            $className = basename($file, '.php');
            $fullClassName = "App\\Action\\$className";
            if (!class_exists($fullClassName)) {
                require_once $file;
            }
            $metadata->discriminatorMap[strtolower(substr($className, 0, -6))] = $fullClassName;
        }
    }
}
