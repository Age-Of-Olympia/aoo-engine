<?php

namespace App\Service\ImportExport;

use App\Action\OutcomeInstruction\OutcomeInstructionFactory;
use App\Entity\Action;
use App\Entity\ActionCondition;
use App\Entity\ActionOutcome;
use App\Entity\OutcomeInstruction;
use App\Entity\Race;
use App\Service\Action\ActionCatalogService;
use Doctrine\Persistence\Proxy;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionProperty;

/**
 * Exports {@see Action} entities (with their conditions, outcomes and outcome
 * instructions) to natural-key payloads. Identity is the action `name`; races
 * are referenced by {@see Race::getName()}; the STI discriminator is emitted as
 * `type`. DB ids and the transient `automaticOutcomeInstructions` are never
 * exported, and type-level instructions belong to the type, not the action.
 */
final class ActionExporter implements ObjectExporter
{
    private const ACTION_SUFFIX = 'Action';

    private ?ActionCatalogService $catalog;

    public function __construct(?ActionCatalogService $catalog = null)
    {
        // Kept nullable so toArray() stays pure: the default catalog (which opens
        // a DB connection) is only built when exportAll() actually needs it.
        $this->catalog = $catalog;
    }

    public function objectType(): string
    {
        return 'action';
    }

    public function exportAll(): array
    {
        $catalog = $this->catalog ??= new ActionCatalogService();

        return array_map(
            fn (Action $action): array => $this->toArray($action),
            $catalog->listActions()
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $entity): array
    {
        if (!$entity instanceof Action) {
            throw new InvalidArgumentException('ActionExporter can only export Action entities.');
        }

        // Read scalars off the property, not the getter: legacy rows have NULL in
        // columns the schema marks NOT NULL (e.g. actions.text), which leaves the
        // typed property uninitialized — the getter would then throw. A NULL in the
        // payload faithfully records "no value" instead of crashing the export.
        return [
            'name' => $this->scalar($entity, 'name'),
            'type' => self::discriminatorType($entity),
            'icon' => $this->scalar($entity, 'icon'),
            'iconColor' => $this->scalar($entity, 'iconColor'),
            'displayName' => $this->scalar($entity, 'displayName'),
            'text' => $this->scalar($entity, 'text'),
            'level' => $this->scalar($entity, 'level'),
            'race' => $this->scalar($entity, 'race'),
            'category' => $this->scalar($entity, 'category'),
            'cost' => $this->scalar($entity, 'cost'),
            'prerequisites' => $this->scalar($entity, 'prerequisites'),
            'races' => $this->raceNames($entity),
            'conditions' => $this->conditions($entity),
            'outcomes' => $this->outcomes($entity),
        ];
    }

    /**
     * Reads a base-{@see Action} scalar property, returning null when the typed
     * property is uninitialized (NULL in a NOT NULL column on a legacy row).
     */
    private function scalar(Action $action, string $property): string|int|null
    {
        $reflection = new ReflectionProperty(Action::class, $property);

        /** @var string|int|null $value */
        $value = $reflection->isInitialized($action) ? $reflection->getValue($action) : null;

        return $value;
    }

    /**
     * @return array<int, string>
     */
    private function raceNames(Action $action): array
    {
        $names = array_map(
            static fn (Race $race): string => $race->getName(),
            $action->getRaces()->toArray()
        );
        sort($names);

        return array_values($names);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function conditions(Action $action): array
    {
        $rows = [];
        foreach ($action->getConditions() as $condition) {
            /** @var ActionCondition $condition */
            $rows[] = [
                'type' => $condition->getConditionType(),
                'executionOrder' => $condition->getExecutionOrder(),
                'blocking' => $condition->isBlocking(),
                'parameters' => $condition->getParameters(),
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function outcomes(Action $action): array
    {
        $rows = [];
        foreach ($action->getOutcomes() as $outcome) {
            /** @var ActionOutcome $outcome */
            $rows[] = [
                'name' => $outcome->getName(),
                'onSuccess' => $outcome->isOnSuccess(),
                'applyToSelf' => $outcome->getApplyToSelf(),
                'instructions' => $this->instructions($outcome),
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function instructions(ActionOutcome $outcome): array
    {
        $rows = [];
        foreach ($outcome->getInstructions() as $instruction) {
            /** @var OutcomeInstruction $instruction */
            $rows[] = [
                'type' => OutcomeInstructionFactory::typeOf($instruction),
                'orderIndex' => $instruction->getOrderIndex(),
                'parameters' => $instruction->getParameters(),
            ];
        }

        return $rows;
    }

    /**
     * Derives the STI discriminator key the same way {@see \App\Listener\ActionMetadataListener}
     * builds the map: the lowercased short class name without its "Action" suffix.
     * Doctrine proxies are unwrapped to their real entity class first.
     */
    private static function discriminatorType(Action $action): string
    {
        $class = $action instanceof Proxy ? get_parent_class($action) : $action::class;
        $shortName = (new ReflectionClass($class))->getShortName();

        return strtolower(substr($shortName, 0, -strlen(self::ACTION_SUFFIX)));
    }
}
