<?php

namespace App\Service\ImportExport;

use App\Action\Condition\ConditionRegistry;
use App\Action\OutcomeInstruction\OutcomeInstructionFactory;
use App\Entity\Action;
use App\Entity\ActionCondition;
use App\Entity\ActionOutcome;
use App\Entity\EntityManagerFactory;
use App\Entity\OutcomeInstruction;
use App\Entity\Race;
use App\Service\Action\ActionCatalogService;
use App\Service\Action\ActionParameterValidator;
use App\Service\Action\ActionTypeRegistry;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionProperty;
use Throwable;

/**
 * Imports action payloads produced by {@see ActionExporter}.
 *
 * preview() is a dry run (no writes). import() re-validates every object and
 * applies them transactionally, all-or-nothing: any rejection rolls the whole
 * batch back. Identity is the action name (create-or-update); an existing
 * action's children are replaced wholesale (owned cascade + orphanRemoval).
 *
 * Parameters are passed through {@see ActionParameterValidator::coerceRaw()},
 * NOT the strict schema coerce(): real stored params legitimately omit "required"
 * fields and carry catalog values the form no longer offers, so strict coercion
 * would reject valid round-tripped data. coerceRaw still enforces the security-
 * critical part — the parameter-key allow-list — since keys can be echoed into
 * outcome HTML.
 */
final class ActionImporter implements ObjectImporter
{
    private EntityManagerInterface $entityManager;
    private ActionCatalogService $catalog;
    private ActionTypeRegistry $typeRegistry;
    private ConditionRegistry $conditionRegistry;
    private ActionParameterValidator $validator;

    public function __construct(
        ?EntityManagerInterface $entityManager = null,
        ?ActionCatalogService $catalog = null,
        ?ActionTypeRegistry $typeRegistry = null,
        ?ConditionRegistry $conditionRegistry = null,
        ?ActionParameterValidator $validator = null,
    ) {
        $this->entityManager = $entityManager ?? EntityManagerFactory::getEntityManager();
        $this->catalog = $catalog ?? new ActionCatalogService($this->entityManager);
        $this->typeRegistry = $typeRegistry ?? new ActionTypeRegistry();
        $this->conditionRegistry = $conditionRegistry ?? new ConditionRegistry();
        $this->validator = $validator ?? new ActionParameterValidator();
    }

    public function objectType(): string
    {
        return 'action';
    }

    public function preview(array $objects): ImportReport
    {
        $report = new ImportReport();
        $seen = [];
        foreach ($objects as $index => $object) {
            $accepted = $this->classify($report, $object, (int) $index);
            if ($accepted === null) {
                continue;
            }
            if ($this->isDuplicate($report, $seen, $accepted['name'])) {
                continue;
            }
            $this->recordClassification($report, $accepted);
        }

        return $report;
    }

    public function import(array $objects): ImportReport
    {
        $report = new ImportReport();
        $this->entityManager->beginTransaction();
        try {
            $plans = [];
            $seen = [];
            foreach ($objects as $index => $object) {
                $accepted = $this->classify($report, $object, (int) $index);
                if ($accepted === null) {
                    continue;
                }
                if ($this->isDuplicate($report, $seen, $accepted['name'])) {
                    continue;
                }
                try {
                    // coerceRaw (inside buildPlan) can throw on an illegal param key.
                    $plans[] = $this->buildPlan($accepted);
                    $this->recordClassification($report, $accepted);
                } catch (InvalidArgumentException $exception) {
                    $report->reject($accepted['name'], $exception->getMessage());
                }
            }

            // All-or-nothing: a single rejection aborts the whole batch unwritten.
            if ($report->hasRejections()) {
                $this->entityManager->rollback();
                $this->entityManager->clear();
                return $report;
            }

            foreach ($plans as $plan) {
                $this->applyPlan($plan);
            }

            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (Throwable $exception) {
            $this->entityManager->rollback();
            // Detach the scheduled-but-unflushed persists so a reused EM (long
            // -running process) doesn't re-flush this batch on a later request.
            $this->entityManager->clear();
            throw $exception;
        }

        return $report;
    }

    /**
     * True (and records a rejection) when $name was already accepted earlier in
     * the batch — actions.name is the natural key, so a duplicate in one bundle
     * would create two rows / be ambiguous on re-import.
     *
     * @param array<string, true> $seen
     */
    private function isDuplicate(ImportReport $report, array &$seen, string $name): bool
    {
        if (isset($seen[$name])) {
            $report->reject($name, 'Doublon : « ' . $name . " » apparaît plusieurs fois dans le lot.");
            return true;
        }
        $seen[$name] = true;

        return false;
    }

    /**
     * Type-level validation + create/update resolution shared by preview and
     * import. Records rejections (and unknown-race warnings) on the report;
     * returns an accepted descriptor, or null when the object is rejected.
     *
     * @return array{object: array<string, mixed>, name: string, type: string, existing: ?Action}|null
     */
    private function classify(ImportReport $report, mixed $object, int $index): ?array
    {
        if (!is_array($object)) {
            $report->reject('#' . $index, 'Objet invalide (pas un objet JSON).');
            return null;
        }

        $name = is_string($object['name'] ?? null) ? trim($object['name']) : '';
        if ($name === '') {
            $report->reject('#' . $index, 'Nom manquant.');
            return null;
        }

        $type = is_string($object['type'] ?? null) ? $object['type'] : '';
        $class = $this->typeRegistry->classForTypeKey($type);
        if ($class === null) {
            $report->reject($name, "Type d'action inconnu : « {$type} ».");
            return null;
        }
        // classForTypeKey also resolves abstract grouping types (e.g. "attack");
        // reject those cleanly instead of letting `new $class()` throw a fatal.
        if (!(new ReflectionClass($class))->isInstantiable()) {
            $report->reject($name, "Type d'action non instanciable : « {$type} ».");
            return null;
        }

        $unknownChild = $this->firstUnknownChildType($object);
        if ($unknownChild !== null) {
            $report->reject($name, $unknownChild);
            return null;
        }

        $existing = $this->catalog->findByName($name);
        if ($existing !== null) {
            $currentType = $this->typeRegistry->typeKeysForAction($existing)[0] ?? null;
            if ($currentType !== null && $currentType !== $type) {
                $report->reject($name, "Changement de type interdit ({$currentType} → {$type}).");
                return null;
            }
        }

        foreach ($this->raceNames($object) as $raceName) {
            if ($this->findRace($raceName) === null) {
                $report->warn($name, "Race inconnue ignorée : « {$raceName} ».");
            }
        }

        return ['object' => $object, 'name' => $name, 'type' => $type, 'existing' => $existing];
    }

    /**
     * @param array{object: array<string, mixed>, name: string, type: string, existing: ?Action} $accepted
     */
    private function recordClassification(ImportReport $report, array $accepted): void
    {
        if ($accepted['existing'] !== null) {
            $report->addUpdated($accepted['name']);
        } else {
            $report->addCreated($accepted['name']);
        }
    }

    /**
     * Coerce every child's params (the throwing step) ahead of any writes, so a
     * bad param key becomes a clean rejection rather than a mid-write failure.
     *
     * @param array{object: array<string, mixed>, name: string, type: string, existing: ?Action} $accepted
     * @return array{name: string, type: string, existing: ?Action, object: array<string, mixed>, conditions: array<int, array<string, mixed>>, outcomes: array<int, array<string, mixed>>}
     */
    private function buildPlan(array $accepted): array
    {
        $object = $accepted['object'];

        $conditions = [];
        foreach ($this->rows($object, 'conditions') as $condition) {
            $conditions[] = [
                'type' => (string) ($condition['type'] ?? ''),
                'executionOrder' => (int) ($condition['executionOrder'] ?? 0),
                'blocking' => (bool) ($condition['blocking'] ?? false),
                'parameters' => $this->coerceParams($this->paramsOf($condition)),
            ];
        }

        $outcomes = [];
        foreach ($this->rows($object, 'outcomes') as $outcome) {
            $instructions = [];
            foreach ($this->rows($outcome, 'instructions') as $instruction) {
                $instructions[] = [
                    'type' => (string) ($instruction['type'] ?? ''),
                    'orderIndex' => (int) ($instruction['orderIndex'] ?? 0),
                    'parameters' => $this->coerceParams($this->paramsOf($instruction)),
                ];
            }
            $outcomes[] = [
                'name' => is_string($outcome['name'] ?? null) ? $outcome['name'] : null,
                'onSuccess' => (bool) ($outcome['onSuccess'] ?? false),
                'applyToSelf' => (bool) ($outcome['applyToSelf'] ?? false),
                'instructions' => $instructions,
            ];
        }

        return [
            'name' => $accepted['name'],
            'type' => $accepted['type'],
            'existing' => $accepted['existing'],
            'object' => $object,
            'conditions' => $conditions,
            'outcomes' => $outcomes,
        ];
    }

    /**
     * @param array{name: string, type: string, existing: ?Action, object: array<string, mixed>, conditions: array<int, array<string, mixed>>, outcomes: array<int, array<string, mixed>>} $plan
     */
    private function applyPlan(array $plan): void
    {
        $action = $plan['existing'];
        if ($action === null) {
            /** @var class-string<Action> $class */
            $class = $this->typeRegistry->classForTypeKey($plan['type']);
            $action = new $class();
            $action->setName($plan['name']);
            $this->entityManager->persist($action);
        }

        $this->applyScalars($action, $plan['object']);
        $this->rebuildConditions($action, $plan['conditions']);
        $this->rebuildOutcomes($action, $plan['outcomes']);
        $this->rebuildRaces($action, $plan['object']);
    }

    /**
     * @param array<string, mixed> $object
     */
    private function applyScalars(Action $action, array $object): void
    {
        // NOT NULL columns: coerce a missing/legacy-null value to a safe default.
        $action->setIcon((string) ($object['icon'] ?? ''));
        $action->setDisplayName((string) ($object['displayName'] ?? $action->getName()));
        $action->setText((string) ($object['text'] ?? ''));
        $action->setLevel((int) ($object['level'] ?? 0));

        // Nullable columns: written via reflection so a null overwrites cleanly
        // (the setters only accept strings, and we must be able to clear a value).
        $this->setNullableScalar($action, 'race', $object['race'] ?? null);
        $this->setNullableScalar($action, 'category', $object['category'] ?? null);
        $this->setNullableScalar($action, 'cost', $object['cost'] ?? null);
        $this->setNullableScalar($action, 'prerequisites', $object['prerequisites'] ?? null);
    }

    private function setNullableScalar(Action $action, string $property, mixed $value): void
    {
        $reflection = new ReflectionProperty(Action::class, $property);
        $reflection->setValue($action, $value === null ? null : (string) $value);
    }

    /**
     * @param array<int, array<string, mixed>> $conditions
     */
    private function rebuildConditions(Action $action, array $conditions): void
    {
        foreach ($action->getConditions()->toArray() as $existing) {
            $action->removeCondition($existing); // orphanRemoval deletes on flush
        }

        foreach ($conditions as $row) {
            $condition = new ActionCondition();
            $condition->setConditionType($row['type']);
            $condition->setExecutionOrder($row['executionOrder']);
            $condition->setBlocking($row['blocking']);
            $condition->setParameters($row['parameters']);
            $condition->setAction($action);
            $action->addCondition($condition);
            $this->entityManager->persist($condition);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $outcomes
     */
    private function rebuildOutcomes(Action $action, array $outcomes): void
    {
        foreach ($action->getOutcomes()->toArray() as $existing) {
            $action->removeOutcome($existing);
        }

        $instructionClasses = OutcomeInstructionFactory::typeMap();

        foreach ($outcomes as $row) {
            $outcome = new ActionOutcome();
            $outcome->setName($row['name']);
            $outcome->setOnSuccess($row['onSuccess']);
            $outcome->setApplyToSelf($row['applyToSelf']);
            $outcome->setAction($action);
            $action->addOutcome($outcome);
            $this->entityManager->persist($outcome);

            foreach ($row['instructions'] as $instructionRow) {
                /** @var class-string<OutcomeInstruction> $class */
                $class = $instructionClasses[$instructionRow['type']];
                $instruction = new $class();
                $instruction->setParameters($instructionRow['parameters']);
                $instruction->setOrderIndex($instructionRow['orderIndex']);
                $instruction->setOutcome($outcome);
                $outcome->addInstruction($instruction);
                $this->entityManager->persist($instruction);
            }
        }
    }

    /**
     * @param array<string, mixed> $object
     */
    private function rebuildRaces(Action $action, array $object): void
    {
        foreach ($action->getRaces()->toArray() as $existing) {
            $action->removeRace($existing);
        }

        foreach ($this->raceNames($object) as $raceName) {
            $race = $this->findRace($raceName);
            if ($race !== null) { // unknown races were already warned in classify()
                $action->addRace($race);
            }
        }
    }

    /**
     * Lenient param normalisation: every key/value through coerceRaw, which
     * json-decodes values (preserving types) and enforces the key allow-list.
     *
     * @param array<string, mixed> $stored
     * @return array<string, mixed>
     */
    private function coerceParams(array $stored): array
    {
        $rows = [];
        foreach ($stored as $key => $value) {
            $rows[] = ['k' => (string) $key, 'v' => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
        }

        return $this->validator->coerceRaw($rows);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function paramsOf(array $row): array
    {
        return is_array($row['parameters'] ?? null) ? $row['parameters'] : [];
    }

    /**
     * The first unknown condition/instruction type message, or null if all the
     * object's children reference known types.
     *
     * @param array<string, mixed> $object
     */
    private function firstUnknownChildType(array $object): ?string
    {
        foreach ($this->rows($object, 'conditions') as $condition) {
            $conditionType = is_string($condition['type'] ?? null) ? $condition['type'] : '';
            if ($this->conditionRegistry->getCondition($conditionType) === null) {
                return "Condition inconnue : « {$conditionType} ».";
            }
        }

        $instructionTypes = OutcomeInstructionFactory::typeMap();
        foreach ($this->rows($object, 'outcomes') as $outcome) {
            foreach ($this->rows($outcome, 'instructions') as $instruction) {
                $instructionType = is_string($instruction['type'] ?? null) ? $instruction['type'] : '';
                if (!isset($instructionTypes[$instructionType])) {
                    return "Instruction inconnue : « {$instructionType} ».";
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $source
     * @return array<int, array<string, mixed>>
     */
    private function rows(array $source, string $key): array
    {
        $value = $source[$key] ?? null;
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_array'));
    }

    /**
     * @param array<string, mixed> $object
     * @return array<int, string>
     */
    private function raceNames(array $object): array
    {
        $races = $object['races'] ?? null;
        if (!is_array($races)) {
            return [];
        }

        return array_values(array_filter($races, 'is_string'));
    }

    private function findRace(string $name): ?Race
    {
        return $this->entityManager->getRepository(Race::class)->findOneBy(['name' => $name]);
    }
}
