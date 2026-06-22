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

    public function classForTypeKey(string $key): ?string
    {
        return $this->typeMap()[$key] ?? null;
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
