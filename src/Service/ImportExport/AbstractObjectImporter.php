<?php

namespace App\Service\ImportExport;

use Doctrine\ORM\EntityManagerInterface;
use Throwable;

/**
 * Shared skeleton for the bundle importers: the preview (dry-run) and the
 * transactional, all-or-nothing import are identical across object families —
 * only how a single object is classified/built and applied differs.
 *
 * Subclasses implement accept() (classify + dedup + record + build one object,
 * or null to reject) and applyPlan() (write one accepted plan). They set the
 * inherited $entityManager in their constructor.
 */
abstract class AbstractObjectImporter implements ObjectImporterInterface
{
    protected EntityManagerInterface $entityManager;

    public function preview(array $objects): ImportReport
    {
        $report = new ImportReport();
        $this->collect($objects, $report);

        return $report;
    }

    public function import(array $objects): ImportReport
    {
        $report = new ImportReport();
        $this->entityManager->beginTransaction();
        try {
            $plans = $this->collect($objects, $report);

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
     * Run every object through accept(), collecting the plans it accepts.
     * preview discards them; import applies them.
     *
     * @param array<int, mixed> $objects
     * @return array<int, mixed>
     */
    private function collect(array $objects, ImportReport $report): array
    {
        $plans = [];
        $seen = [];
        foreach ($objects as $index => $object) {
            $plan = $this->accept($report, $seen, $object, (int) $index);
            if ($plan !== null) {
                $plans[] = $plan;
            }
        }

        return $plans;
    }

    /**
     * Classify one object — recording its create/update/reject/warn status and
     * de-duplicating within the batch — and return the plan applyPlan() will
     * write, or null when the object can't be imported.
     *
     * @param array<string, true> $seen natural keys already accepted in this batch
     */
    abstract protected function accept(ImportReport $report, array &$seen, mixed $object, int $index): mixed;

    /**
     * Persist one accepted plan (the value accept() returned).
     */
    abstract protected function applyPlan(mixed $plan): void;

    /**
     * True (and records a rejection) when $key was already accepted earlier in
     * the batch — the natural key must be unique within a bundle.
     *
     * @param array<string, true> $seen
     */
    protected function isDuplicate(ImportReport $report, array &$seen, string $key): bool
    {
        if (isset($seen[$key])) {
            $report->reject($key, 'Doublon : « ' . $key . " » apparaît plusieurs fois dans le lot.");
            return true;
        }
        $seen[$key] = true;

        return false;
    }
}
