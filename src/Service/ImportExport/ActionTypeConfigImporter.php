<?php

namespace App\Service\ImportExport;

use App\Entity\ActionTypeLog;
use App\Entity\ActionTypeXp;
use App\Entity\EntityManagerFactory;
use App\Service\Action\ActionTypeRegistry;
use App\Service\Action\Xp\XpCalculatorRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Throwable;

/**
 * Imports per-type action config (XP rule + log templates) produced by
 * {@see ActionTypeConfigExporter}. Identity is the typeKey (create-or-update);
 * a payload may carry `xp`, `logs`, or both. import() is all-or-nothing: any
 * rejection rolls the whole batch back.
 *
 * Only the selected XP mode's known params are kept (coerced to int), so a
 * bundle can't smuggle arbitrary keys.
 */
final class ActionTypeConfigImporter implements ObjectImporter
{
    private EntityManagerInterface $entityManager;
    private ActionTypeRegistry $registry;
    private XpCalculatorRegistry $calculators;

    public function __construct(
        ?EntityManagerInterface $entityManager = null,
        ?ActionTypeRegistry $registry = null,
        ?XpCalculatorRegistry $calculators = null,
    ) {
        $this->entityManager = $entityManager ?? EntityManagerFactory::getEntityManager();
        $this->registry = $registry ?? new ActionTypeRegistry();
        $this->calculators = $calculators ?? new XpCalculatorRegistry();
    }

    public function objectType(): string
    {
        return 'action-type';
    }

    public function preview(array $objects): ImportReport
    {
        $report = new ImportReport();
        $seen = [];
        foreach ($objects as $index => $object) {
            $this->classify($report, $seen, $object, (int) $index);
        }

        return $report;
    }

    public function import(array $objects): ImportReport
    {
        $report = new ImportReport();
        $seen = [];
        $accepted = [];
        foreach ($objects as $index => $object) {
            $typeKey = $this->classify($report, $seen, $object, (int) $index);
            if ($typeKey !== null) {
                $accepted[$typeKey] = $object;
            }
        }

        if ($report->hasRejections()) {
            return $report;
        }

        $this->entityManager->beginTransaction();
        try {
            foreach ($accepted as $typeKey => $object) {
                $this->apply($typeKey, $object);
            }
            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (Throwable $exception) {
            $this->entityManager->rollback();
            $this->entityManager->clear();
            throw $exception;
        }

        return $report;
    }

    /**
     * Validate one object, record its create/update/reject status, and return the
     * accepted typeKey (or null when rejected / a duplicate).
     *
     * @param array<string, true> $seen
     */
    private function classify(ImportReport $report, array &$seen, mixed $object, int $index): ?string
    {
        if (!is_array($object)) {
            $report->reject('#' . $index, 'Objet invalide (pas un objet JSON).');
            return null;
        }

        $typeKey = is_string($object['typeKey'] ?? null) ? trim($object['typeKey']) : '';
        if ($typeKey === '') {
            $report->reject('#' . $index, 'typeKey manquant.');
            return null;
        }
        if (!isset($this->registry->assignableTypes()[$typeKey])) {
            $report->reject($typeKey, "Type d'action inconnu : « {$typeKey} ».");
            return null;
        }
        if (isset($seen[$typeKey])) {
            $report->reject($typeKey, "Doublon : « {$typeKey} » apparaît plusieurs fois dans le lot.");
            return null;
        }

        $xp = $object['xp'] ?? null;
        if ($xp !== null) {
            $mode = is_array($xp) && is_string($xp['mode'] ?? null) ? $xp['mode'] : '';
            if (!$this->calculators->has($mode)) {
                $report->reject($typeKey, "Mode XP inconnu : « {$mode} ».");
                return null;
            }
        }

        $seen[$typeKey] = true;
        $exists = $this->findXp($typeKey) !== null || $this->findLog($typeKey) !== null;
        $exists ? $report->addUpdated($typeKey) : $report->addCreated($typeKey);

        return $typeKey;
    }

    /**
     * @param array<string, mixed> $object
     */
    private function apply(string $typeKey, array $object): void
    {
        $xp = $object['xp'] ?? null;
        if (is_array($xp)) {
            $mode = (string) $xp['mode'];
            $rawParams = is_array($xp['params'] ?? null) ? $xp['params'] : [];
            $params = [];
            foreach ($this->calculators->defaultsFor($mode) as $key => $default) {
                $params[$key] = (int) ($rawParams[$key] ?? $default);
            }

            $row = $this->findXp($typeKey) ?? $this->persistNewXp($typeKey);
            $row->setMode($mode)->setParams($params);
        }

        $logs = $object['logs'] ?? null;
        if (is_array($logs)) {
            $row = $this->findLog($typeKey) ?? $this->persistNewLog($typeKey);
            $row->setActorTemplate($this->nullableString($logs['actorTemplate'] ?? null));
            $row->setTargetTemplate($this->nullableString($logs['targetTemplate'] ?? null));
        }
    }

    private function persistNewXp(string $typeKey): ActionTypeXp
    {
        $row = (new ActionTypeXp())->setTypeKey($typeKey);
        $this->entityManager->persist($row);

        return $row;
    }

    private function persistNewLog(string $typeKey): ActionTypeLog
    {
        $row = (new ActionTypeLog())->setTypeKey($typeKey);
        $this->entityManager->persist($row);

        return $row;
    }

    private function findXp(string $typeKey): ?ActionTypeXp
    {
        return $this->entityManager->getRepository(ActionTypeXp::class)->findOneBy(['typeKey' => $typeKey]);
    }

    private function findLog(string $typeKey): ?ActionTypeLog
    {
        return $this->entityManager->getRepository(ActionTypeLog::class)->findOneBy(['typeKey' => $typeKey]);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
