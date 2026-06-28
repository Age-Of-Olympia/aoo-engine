<?php

namespace App\Service\Action;

use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

/**
 * find()-or-throw for the action editor services. Every add/remove/save first
 * loads its target row by id and rejects a missing one with the same
 * "<Label> introuvable : <id>." message — this centralises that guard.
 */
final class EntityFinder
{
    /**
     * @template T of object
     * @param class-string<T> $entityClass
     * @return T
     */
    public static function orFail(EntityManagerInterface $entityManager, string $entityClass, int $id, string $label): object
    {
        $entity = $entityManager->find($entityClass, $id);
        if ($entity === null) {
            throw new InvalidArgumentException("{$label} introuvable : {$id}.");
        }

        return $entity;
    }
}
