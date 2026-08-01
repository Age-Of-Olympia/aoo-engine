<?php

namespace App\Service\Action;

use App\Entity\Action;
use App\Entity\ActionCondition;
use App\Entity\ActionTypePrecondition;
use App\Factory\EntityManagerFactory;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Resolves the preconditions an action inherits, as ready-to-run (transient)
 * {@see ActionCondition} objects. It collects the configured
 * {@see ActionTypePrecondition} rows for the global key ('') plus every type key
 * in the action's class ancestry (a MeleeAction inherits "melee" and "attack"),
 * and rebuilds an ActionCondition for each.
 *
 * Order: global first, then broadest ancestor (e.g. "attack" before "melee"),
 * then by orderIndex within a key — so the most general gates (the enfers block)
 * run before more specific ones, mirroring the old hardcoded injection order.
 */
final class ActionTypePreconditionResolver
{
    /** Sentinel type key for preconditions that apply to every action. */
    public const GLOBAL_KEY = '';

    private EntityManagerInterface $entityManager;
    private ActionTypeRegistry $registry;

    public function __construct(?EntityManagerInterface $entityManager = null, ?ActionTypeRegistry $registry = null)
    {
        $this->entityManager = $entityManager ?? EntityManagerFactory::getEntityManager();
        $this->registry = $registry ?? new ActionTypeRegistry();
    }

    /**
     * @return array<int, ActionCondition>
     */
    public function resolve(Action $action): array
    {
        // typeKeysForAction is concrete-first ([melee, attack]); reverse so the
        // broadest ancestor sorts first, and prepend the global key.
        $ancestry = array_reverse(array_values($this->registry->typeKeysForAction($action)));
        $orderedKeys = array_merge([self::GLOBAL_KEY], $ancestry);
        $priority = array_flip($orderedKeys);

        /** @var array<int, ActionTypePrecondition> $configs */
        $configs = $this->entityManager->getRepository(ActionTypePrecondition::class)
            ->findBy(['typeKey' => $orderedKeys]);

        usort($configs, static function (ActionTypePrecondition $a, ActionTypePrecondition $b) use ($priority): int {
            return [$priority[$a->getTypeKey()] ?? PHP_INT_MAX, $a->getOrderIndex()]
                <=> [$priority[$b->getTypeKey()] ?? PHP_INT_MAX, $b->getOrderIndex()];
        });

        return array_map(fn (ActionTypePrecondition $config): ActionCondition => $this->build($config, $action), $configs);
    }

    private function build(ActionTypePrecondition $config, Action $action): ActionCondition
    {
        $condition = new ActionCondition();
        $condition->setConditionType($config->getConditionType());
        $condition->setParameters($config->getParameters() ?? []);
        $condition->setExecutionOrder($config->getOrderIndex());
        $condition->setBlocking($config->isBlocking());
        // The handler reads the action (e.g. PlanCondition checks the action name
        // against its enfers whitelist), so wire the owning action through.
        $condition->setAction($action);

        return $condition;
    }
}
