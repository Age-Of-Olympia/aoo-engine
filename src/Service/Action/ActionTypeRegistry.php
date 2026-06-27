<?php

namespace App\Service\Action;

use App\Entity\Action;
use ReflectionClass;

/**
 * Maps action classes to "type keys" used by the type-level instruction system.
 * A key is the lowercased class short-name without the Action suffix
 * (MeleeAction -> "melee", AttackAction -> "attack"). Inheritance is resolved
 * from the real PHP class hierarchy, so a new subclass groups automatically.
 */
final class ActionTypeRegistry
{
    /** @var array<string, class-string>|null */
    private ?array $cachedTypeMap = null;

    /**
     * Assignable type keys => label: every action class (concrete and abstract
     * grouping parents like "attack") under src/Action, excluding the
     * App\Entity\Action root.
     *
     * @return array<string, string>
     */
    public function assignableTypes(): array
    {
        $types = [];
        foreach (array_keys($this->typeMap()) as $key) {
            $types[$key] = ucfirst($key);
        }
        ksort($types);

        return $types;
    }

    /**
     * Type keys in an action's class ancestry (closest first), filtered to the
     * assignable set — e.g. a MeleeAction resolves to ['melee', 'attack'].
     *
     * @return array<int, string>
     */
    public function typeKeysForAction(Action $action): array
    {
        $assignable = $this->typeMap();
        $keys = [];
        $class = get_class($action);
        while ($class !== false && $class !== Action::class) {
            $key = $this->keyForClass($class);
            if (isset($assignable[$key]) && !in_array($key, $keys, true)) {
                $keys[] = $key;
            }
            $class = get_parent_class($class);
        }

        return $keys;
    }

    /**
     * The type-key ancestry of a type key (closest first, including the key
     * itself) — e.g. "spell" resolves to ['spell', 'technique', 'attack']. Same
     * chain typeKeysForAction returns, but resolved from a type key (so the
     * type-defaults editors can show what a type inherits).
     *
     * @return array<int, string>
     */
    public function ancestryForTypeKey(string $typeKey): array
    {
        $map = $this->typeMap();
        if (!isset($map[$typeKey])) {
            return [];
        }

        $keys = [];
        $class = $map[$typeKey];
        while ($class !== false && $class !== Action::class) {
            $key = $this->keyForClass($class);
            if (isset($map[$key]) && !in_array($key, $keys, true)) {
                $keys[] = $key;
            }
            $class = get_parent_class($class);
        }

        return $keys;
    }

    /**
     * Each type key mapped to its position in the {@see tree()} depth-first order
     * — i.e. the order the "Types d'action" rail displays them. Lets a flat action
     * list be sorted to match the tree (attack family first, then heal, …).
     *
     * @return array<string, int>
     */
    public function typeOrderIndex(): array
    {
        $order = [];
        $this->flattenOrder($this->tree(), $order);

        return $order;
    }

    /**
     * @param array<int, ActionTypeNode> $nodes
     * @param array<string, int>         $order
     */
    private function flattenOrder(array $nodes, array &$order): void
    {
        foreach ($nodes as $node) {
            $order[$node->key] = count($order);
            $this->flattenOrder($node->children, $order);
        }
    }

    public function classForTypeKey(string $key): ?string
    {
        return $this->typeMap()[$key] ?? null;
    }

    /**
     * The type hierarchy as a tree of {@see ActionTypeNode}, mirroring the PHP
     * class inheritance (attack → melee/distance/technique → …). Roots are the
     * types whose parent is the abstract {@see Action}. Children are sorted by key
     * so the rendering is deterministic.
     *
     * @return array<int, ActionTypeNode>
     */
    public function tree(): array
    {
        $map = $this->typeMap();

        /** @var array<string, array<int, string>> $childrenByParent keyed by parent type key ('' = root) */
        $childrenByParent = [];
        foreach (array_keys($map) as $key) {
            $childrenByParent[$this->parentTypeKey($map, $key)][] = $key;
        }

        return $this->buildNodes($map, $childrenByParent, '');
    }

    /**
     * The closest ancestor type key of $key, or '' when its parent is the Action
     * root (i.e. $key is a tree root).
     *
     * @param array<string, class-string> $map
     */
    private function parentTypeKey(array $map, string $key): string
    {
        $parentClass = get_parent_class($map[$key]);
        if ($parentClass === false || $parentClass === Action::class) {
            return '';
        }

        $parentKey = $this->keyForClass($parentClass);

        return isset($map[$parentKey]) ? $parentKey : '';
    }

    /**
     * @param array<string, class-string>       $map
     * @param array<string, array<int, string>> $childrenByParent
     * @return array<int, ActionTypeNode>
     */
    private function buildNodes(array $map, array $childrenByParent, string $parentKey): array
    {
        $keys = $childrenByParent[$parentKey] ?? [];
        sort($keys);

        $nodes = [];
        foreach ($keys as $key) {
            $nodes[] = new ActionTypeNode(
                $key,
                ucfirst($key),
                (new ReflectionClass($map[$key]))->isAbstract(),
                $this->buildNodes($map, $childrenByParent, $key),
            );
        }

        return $nodes;
    }

    /**
     * @return array<string, class-string>
     */
    private function typeMap(): array
    {
        if ($this->cachedTypeMap !== null) {
            return $this->cachedTypeMap;
        }

        $map = [];
        foreach (glob(__DIR__ . '/../../Action/*Action.php') as $file) {
            $className = basename($file, '.php');
            $fqcn = "App\\Action\\$className";
            if (!class_exists($fqcn)) {
                require_once $file;
            }
            if (is_subclass_of($fqcn, Action::class)) {
                $map[$this->keyForClass($fqcn)] = $fqcn;
            }
        }
        $this->cachedTypeMap = $map;

        return $map;
    }

    private function keyForClass(string $fqcn): string
    {
        $short = (new ReflectionClass($fqcn))->getShortName();

        return strtolower((string) preg_replace('/Action$/', '', $short));
    }
}
