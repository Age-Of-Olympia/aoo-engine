<?php

namespace App\Service\Action;

use App\Interface\ConditionInterface;

/**
 * A precondition ready to run: its handler, and what its failure costs —
 * a refusal of the action, or a plain paid failure.
 *
 * The pair exists because the handler is shared. One ObstacleCondition
 * serves all six compute types, so it is the configuration ROW that says
 * what its failure entails: the handler cannot carry it, and the parent
 * condition must not.
 */
final class ResolvedPrecondition
{
    public function __construct(
        private ConditionInterface $handler,
        private bool $blocking,
    ) {
    }

    public function handler(): ConditionInterface
    {
        return $this->handler;
    }

    /** A failure refuses the action instead of failing it at the actor's expense. */
    public function isBlocking(): bool
    {
        return $this->blocking;
    }
}
