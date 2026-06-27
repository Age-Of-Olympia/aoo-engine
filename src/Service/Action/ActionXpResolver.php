<?php

namespace App\Service\Action;

use App\Entity\Action;
use App\Entity\ActionTypeXp;
use App\Entity\EntityManagerFactory;
use App\Interface\ActorInterface;
use App\Service\Action\Xp\XpCalculatorRegistry;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Computes an action's XP from its type-level rule ({@see ActionTypeXp}) — the
 * data-driven replacement for Action::calculateXp(). It picks the closest type
 * in the action's ancestry that has a row and runs the matching calculator with
 * its params. When no type row exists (or the mode is unknown) it falls back to
 * the legacy calculateXp(), so behaviour is preserved until every type is seeded.
 */
final class ActionXpResolver
{
    private EntityManagerInterface $entityManager;
    private ActionTypeRegistry $registry;
    private XpCalculatorRegistry $calculators;

    public function __construct(
        ?EntityManagerInterface $entityManager = null,
        ?ActionTypeRegistry $registry = null,
        ?XpCalculatorRegistry $calculators = null,
    ) {
        $this->entityManager = $entityManager ?? EntityManagerFactory::getEntityManager();
        $this->registry = $registry ?? new ActionTypeRegistry();
        $this->calculators = $calculators ?? new XpCalculatorRegistry();
    }

    /**
     * @return array{actor: int, target: int}
     */
    public function calculate(Action $action, bool $success, ActorInterface $actor, ActorInterface $target): array
    {
        $config = $this->configFor($action);
        $calculator = $config === null ? null : $this->calculators->get($config->getMode());

        if ($config === null || $calculator === null) {
            return $this->legacy($action, $success, $actor, $target);
        }

        return $calculator->calculate($config->getParams(), $success, $actor, $target);
    }

    /**
     * @return array{actor: int, target: int}
     */
    private function legacy(Action $action, bool $success, ActorInterface $actor, ActorInterface $target): array
    {
        $xp = $action->calculateXp($success, $actor, $target);

        return ['actor' => (int) ($xp['actor'] ?? 0), 'target' => (int) ($xp['target'] ?? 0)];
    }

    private function configFor(Action $action): ?ActionTypeXp
    {
        $keys = $this->registry->typeKeysForAction($action);
        if ($keys === []) {
            return null;
        }

        /** @var array<int, ActionTypeXp> $rows */
        $rows = $this->entityManager->getRepository(ActionTypeXp::class)->findBy(['typeKey' => $keys]);
        if ($rows === []) {
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
