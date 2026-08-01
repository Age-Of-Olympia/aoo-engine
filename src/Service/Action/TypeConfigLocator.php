<?php

namespace App\Service\Action;

use App\Entity\Action;
use App\Factory\EntityManagerFactory;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Finds the type-keyed config row (ActionTypeXp, ActionTypeLog, …) that applies
 * to an action: the row whose typeKey is the closest type in the action's class
 * ancestry. The XP and log resolvers shared this lookup verbatim — only the
 * entity class and the warning label differed.
 */
final class TypeConfigLocator
{
    private EntityManagerInterface $entityManager;
    private ActionTypeRegistry $registry;

    public function __construct(?EntityManagerInterface $entityManager = null, ?ActionTypeRegistry $registry = null)
    {
        $this->entityManager = $entityManager ?? EntityManagerFactory::getEntityManager();
        $this->registry = $registry ?? new ActionTypeRegistry();
    }

    /**
     * The closest-in-ancestry row of $entityClass for $action, or null.
     *
     * Each candidate entity must expose getTypeKey(): string. When the action has
     * a type ancestry but no row covers it and $warnContext is given, a one-off
     * {@see TypeConfigWarning} is emitted (the silent fail-soft would otherwise
     * hide a skipped seed migration / unseeded subclass).
     *
     * @template T of object
     * @param class-string<T> $entityClass
     * @return T|null
     */
    public function closest(Action $action, string $entityClass, ?string $warnContext = null): ?object
    {
        $keys = $this->registry->typeKeysForAction($action);
        if ($keys === []) {
            return null;
        }

        $rows = $this->entityManager->getRepository($entityClass)->findBy(['typeKey' => $keys]);
        if ($rows === []) {
            if ($warnContext !== null) {
                TypeConfigWarning::once($warnContext, $keys);
            }
            return null;
        }

        $byKey = [];
        foreach ($rows as $row) {
            $byKey[$row->getTypeKey()] = $row;
        }
        // typeKeysForAction is closest-first, so the first hit is the most specific.
        foreach ($keys as $key) {
            if (isset($byKey[$key])) {
                return $byKey[$key];
            }
        }

        return null;
    }
}
