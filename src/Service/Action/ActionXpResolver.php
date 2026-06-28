<?php

namespace App\Service\Action;

use App\Entity\Action;
use App\Entity\ActionTypeXp;
use App\Interface\ActorInterface;
use App\Service\Action\Xp\XpCalculator;
use App\Service\Action\Xp\XpCalculatorRegistry;
use App\Service\Action\Xp\XpSideEffect;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Computes an action's XP from its type-level rule ({@see ActionTypeXp}) — the
 * data-driven replacement for the removed Action::calculateXp(). It picks the
 * closest type in the action's ancestry that has a row and runs the matching
 * calculator with its params. A type with no configured rule grants no XP.
 */
final class ActionXpResolver
{
    private XpCalculatorRegistry $calculators;
    private TypeConfigLocator $locator;

    /** @var array<int, array{0: ?ActionTypeXp, 1: ?XpCalculator}> */
    private array $ruleCache = [];

    public function __construct(
        ?EntityManagerInterface $entityManager = null,
        ?ActionTypeRegistry $registry = null,
        ?XpCalculatorRegistry $calculators = null,
        ?TypeConfigLocator $locator = null,
    ) {
        $this->calculators = $calculators ?? new XpCalculatorRegistry();
        $this->locator = $locator ?? new TypeConfigLocator($entityManager, $registry);
    }

    /**
     * The XP this action grants. Pure: it never mutates the fighters — resource
     * spends are an {@see XpSideEffect}, applied separately via applySideEffects().
     *
     * @return array{actor: int, target: int}
     */
    public function calculate(Action $action, bool $success, ActorInterface $actor, ActorInterface $target): array
    {
        [$config, $calculator] = $this->ruleFor($action);

        if ($config === null || $calculator === null) {
            return ['actor' => 0, 'target' => 0]; // no configured rule -> no XP
        }

        return $calculator->calculate($config->getParams(), $success, $actor, $target);
    }

    /**
     * Apply the rule's mutations (e.g. training's per-side energie spend). Call
     * once, at the real execution point, after calculate() has read pre-spend
     * state. A no-op for rules without side effects.
     */
    public function applySideEffects(Action $action, bool $success, ActorInterface $actor, ActorInterface $target): void
    {
        [$config, $calculator] = $this->ruleFor($action);

        if ($config === null || !$calculator instanceof XpSideEffect) {
            return;
        }

        $calculator->applySideEffects($config->getParams(), $success, $actor, $target);
    }

    /**
     * Resolve (and memoise) the [config, calculator] pair for an action, so
     * calculate() and applySideEffects() share a single lookup per action.
     *
     * @return array{0: ?ActionTypeXp, 1: ?XpCalculator}
     */
    private function ruleFor(Action $action): array
    {
        $oid = spl_object_id($action);
        if (!array_key_exists($oid, $this->ruleCache)) {
            $config = $this->configFor($action);
            $calculator = $config === null ? null : $this->calculators->get($config->getMode());
            $this->ruleCache[$oid] = [$config, $calculator];
        }

        return $this->ruleCache[$oid];
    }

    private function configFor(Action $action): ?ActionTypeXp
    {
        return $this->locator->closest($action, ActionTypeXp::class, 'XP');
    }
}
