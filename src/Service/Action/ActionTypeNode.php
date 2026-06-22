<?php

namespace App\Service\Action;

/**
 * One node of the action-type inheritance tree (see {@see ActionTypeRegistry::tree()}):
 * a type key, its display label, whether the underlying class is abstract (a pure
 * grouping type like "attack"/"technique"), and its child types.
 */
final class ActionTypeNode
{
    /**
     * @param array<int, ActionTypeNode> $children
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly bool $abstract,
        public readonly array $children,
    ) {
    }
}
