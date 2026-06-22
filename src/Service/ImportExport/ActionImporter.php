<?php

namespace App\Service\ImportExport;

use App\Action\Condition\ConditionRegistry;
use App\Action\OutcomeInstruction\OutcomeInstructionFactory;
use App\Entity\EntityManagerFactory;
use App\Entity\Race;
use App\Service\Action\ActionCatalogService;
use App\Service\Action\ActionTypeRegistry;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Imports action payloads produced by {@see ActionExporter}. This slice provides
 * the dry-run {@see preview()} only: it classifies each object as create/update,
 * rejects whole objects that can't import (unknown action/condition/instruction
 * type, missing name, forbidden STI type change) and warns about links it would
 * skip (unknown race). It performs NO writes — the transactional commit lands in
 * a later slice.
 */
final class ActionImporter implements ObjectImporter
{
    private EntityManagerInterface $entityManager;
    private ActionCatalogService $catalog;
    private ActionTypeRegistry $typeRegistry;
    private ConditionRegistry $conditionRegistry;

    public function __construct(
        ?EntityManagerInterface $entityManager = null,
        ?ActionCatalogService $catalog = null,
        ?ActionTypeRegistry $typeRegistry = null,
        ?ConditionRegistry $conditionRegistry = null,
    ) {
        $this->entityManager = $entityManager ?? EntityManagerFactory::getEntityManager();
        $this->catalog = $catalog ?? new ActionCatalogService($this->entityManager);
        $this->typeRegistry = $typeRegistry ?? new ActionTypeRegistry();
        $this->conditionRegistry = $conditionRegistry ?? new ConditionRegistry();
    }

    public function objectType(): string
    {
        return 'action';
    }

    public function preview(array $objects): ImportReport
    {
        $report = new ImportReport();
        foreach ($objects as $index => $object) {
            $this->previewOne($report, $object, (int) $index);
        }

        return $report;
    }

    private function previewOne(ImportReport $report, mixed $object, int $index): void
    {
        if (!is_array($object)) {
            $report->reject('#' . $index, 'Objet invalide (pas un objet JSON).');
            return;
        }

        $name = is_string($object['name'] ?? null) ? trim($object['name']) : '';
        if ($name === '') {
            $report->reject('#' . $index, 'Nom manquant.');
            return;
        }

        $type = is_string($object['type'] ?? null) ? $object['type'] : '';
        if ($this->typeRegistry->classForTypeKey($type) === null) {
            $report->reject($name, "Type d'action inconnu : « {$type} ».");
            return;
        }

        $unknownChild = $this->firstUnknownChildType($object);
        if ($unknownChild !== null) {
            $report->reject($name, $unknownChild);
            return;
        }

        // create-or-update by natural key; a stored type change is fail-closed.
        $existing = $this->catalog->findByName($name);
        if ($existing !== null) {
            $currentType = $this->typeRegistry->typeKeysForAction($existing)[0] ?? null;
            if ($currentType !== null && $currentType !== $type) {
                $report->reject($name, "Changement de type interdit ({$currentType} → {$type}).");
                return;
            }
            $report->addUpdated($name);
        } else {
            $report->addCreated($name);
        }

        // Unknown races are warn-and-skip: the action still imports.
        foreach ($this->raceNames($object) as $raceName) {
            if ($this->findRace($raceName) === null) {
                $report->warn($name, "Race inconnue ignorée : « {$raceName} ».");
            }
        }
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
