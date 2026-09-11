<?php

namespace App\Service\Action;

use App\Entity\Action;
use App\Entity\OutcomeInstruction;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\Proxy;
use InvalidArgumentException;

/**
 * The types of the two STI hierarchies (actions, outcome instructions) are
 * the files of their folder: drop a class in, it exists. This is the one
 * place that reads the folder and derives the discriminator key (the
 * lowercased short name without the suffix); Doctrine's map is filled from
 * it at metadata load, and every other consumer asks it instead of
 * scanning again.
 */
final class ActionTypeDiscovery
{
    /** @var array<class-string, array{dir: string, suffix: string, namespace: string}> */
    private const ROOTS = [
        Action::class => ['dir' => 'Action', 'suffix' => 'Action', 'namespace' => 'App\\Action\\'],
        OutcomeInstruction::class => ['dir' => 'Action/OutcomeInstruction', 'suffix' => 'OutcomeInstruction', 'namespace' => 'App\\Action\\OutcomeInstruction\\'],
    ];

    /** @var array<class-string, array<string, class-string>> */
    private static array $maps = [];

    /**
     * key => class for every type under $root, abstract grouping classes
     * included (they carry type-level config).
     *
     * @param class-string $root
     * @return array<string, class-string>
     */
    public static function typeMap(string $root): array
    {
        if (isset(self::$maps[$root])) {
            return self::$maps[$root];
        }
        $spec = self::ROOTS[$root] ?? throw new InvalidArgumentException($root . ' is not an STI root known here');

        $map = [];
        foreach (glob(dirname(__DIR__, 2) . '/' . $spec['dir'] . '/*' . $spec['suffix'] . '.php') ?: [] as $file) {
            $short = basename($file, '.php');
            $map[self::keyFor($short, $spec['suffix'])] = $spec['namespace'] . $short;
        }
        ksort($map);

        return self::$maps[$root] = $map;
    }

    /** The discriminator key of a class (short or qualified) of the given suffix. */
    public static function keyFor(string $class, string $suffix): string
    {
        $short = str_contains($class, '\\') ? substr(strrchr($class, '\\'), 1) : $class;

        return strtolower(substr($short, 0, -strlen($suffix)));
    }

    /** The discriminator key of an entity (proxies unwrapped) or of a class name. */
    public static function typeOf(object|string $entity): string
    {
        $class = is_object($entity)
            ? ($entity instanceof Proxy ? get_parent_class($entity) : $entity::class)
            : $entity;
        foreach (self::ROOTS as $root => $spec) {
            if (is_a($class, $root, true)) {
                return self::keyFor($class, $spec['suffix']);
            }
        }

        throw new InvalidArgumentException($class . ' belongs to no STI hierarchy known here');
    }

    /**
     * The folder scan becomes the root's discriminator map — called by the
     * root's metadata listener (ActionMetadataListener,
     * OutcomeInstructionMetadataListener).
     *
     * @param class-string $root
     */
    public static function fillMetadata(ClassMetadata $metadata, string $root): void
    {
        foreach (self::typeMap($root) as $key => $class) {
            $metadata->discriminatorMap[$key] = $class;
            // Root queries filter on `type IN (...)` built from subClasses:
            // only the concrete classes, an abstract grouping type (attack)
            // is not a mapped entity and has no rows.
            if (!(new \ReflectionClass($class))->isAbstract() && !in_array($class, $metadata->subClasses, true)) {
                $metadata->subClasses[] = $class;
            }
        }
    }
}
