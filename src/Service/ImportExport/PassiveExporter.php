<?php

namespace App\Service\ImportExport;

use App\Interface\ObjectExporterInterface;
use App\Entity\ActionPassive;
use App\Service\Action\ActionPassiveCatalogService;
use InvalidArgumentException;
use ReflectionProperty;

/**
 * Exports {@see ActionPassive} entities to natural-key payloads. A passive is a
 * flat entity (no STI, no child collections), so the payload is just its scalars
 * plus the two JSON columns (traits, conditions). Identity is the passive `name`.
 *
 * Proves the {@see ObjectExporterInterface} seam generalises beyond actions.
 */
final class PassiveExporter implements ObjectExporterInterface
{
    private ?ActionPassiveCatalogService $catalog;

    public function __construct(?ActionPassiveCatalogService $catalog = null)
    {
        // Lazy: the default catalog opens a DB connection, so toArray() stays pure.
        $this->catalog = $catalog;
    }

    public function objectType(): string
    {
        return 'passive';
    }

    public function exportAll(): array
    {
        $catalog = $this->catalog ??= new ActionPassiveCatalogService();

        return array_map(
            fn (ActionPassive $passive): array => $this->toArray($passive),
            $catalog->listPassives()
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $entity): array
    {
        if (!$entity instanceof ActionPassive) {
            throw new InvalidArgumentException('PassiveExporter can only export ActionPassive entities.');
        }

        $value = $this->raw($entity, 'value');
        $traits = $this->raw($entity, 'traits');
        $conditions = $this->raw($entity, 'conditions');

        return [
            'name' => $this->str($entity, 'name'),
            'displayName' => $this->str($entity, 'displayName'),
            'text' => $this->str($entity, 'text'),
            'type' => $this->str($entity, 'type'),
            'carac' => $this->str($entity, 'carac'),
            'category' => $this->str($entity, 'category'),
            'prerequisites' => $this->str($entity, 'prerequisites'),
            'race' => $this->str($entity, 'race'),
            'level' => (int) ($this->raw($entity, 'level') ?? 0),
            'value' => $value !== null ? (float) $value : 0.0,
            'traits' => is_array($traits) ? $traits : [],
            'conditions' => is_array($conditions) ? $conditions : null,
        ];
    }

    /**
     * Read a property, or null when uninitialized (legacy NULL in a NOT NULL
     * column would otherwise make the typed getter throw — same guard as
     * {@see ActionExporter}).
     */
    private function raw(ActionPassive $passive, string $property): mixed
    {
        $reflection = new ReflectionProperty(ActionPassive::class, $property);

        return $reflection->isInitialized($passive) ? $reflection->getValue($passive) : null;
    }

    private function str(ActionPassive $passive, string $property): ?string
    {
        $value = $this->raw($passive, $property);

        return $value === null ? null : (string) $value;
    }
}
