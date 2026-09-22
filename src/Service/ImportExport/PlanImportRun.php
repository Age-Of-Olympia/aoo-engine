<?php

namespace App\Service\ImportExport;

use App\Factory\EntityManagerFactory;
use App\Service\PlanConfigService;
use App\Service\TiledMapService;
use Doctrine\DBAL\Connection;

/**
 * One plan bundle being imported, step by step.
 *
 * A whole plan used to be written in a single transaction and a single
 * request: a big map ran out of time or memory, and what had been written
 * was rolled back. A run cuts the same work into steps — purge, cells, each
 * layer by chunks, the entity families, the buildings of each level — and
 * commits each one with the cursor that names the next
 * ({@see PlanImportProgress}). An interrupted import resumes where it
 * stopped instead of starting over.
 *
 * The caller decides how many steps one request runs: the console command
 * loops on a time budget, the admin page calls back until `isDone()`.
 */
final class PlanImportRun
{
    /** Rows written per step. */
    private const CHUNK = 2000;

    private Connection $conn;
    private PlanImportProgress $progress;
    private PlanImportWriter $writer;

    /** @var array<int, array{key: string, label: string, run: callable}> */
    private array $steps;

    private string $fingerprint;
    private string $plan;
    private int $index;

    /** @param array<string, mixed> $payload one validated plan payload */
    public function __construct(
        private array $payload,
        private ImportReport $report,
        ?Connection $conn = null,
        ?PlanImportProgress $progress = null
    ) {
        $this->conn = $conn ?? EntityManagerFactory::getEntityManager()->getConnection();
        $this->progress = $progress ?? new PlanImportProgress($this->conn);
        $this->writer = new PlanImportWriter($this->conn, $this->report);
        $this->plan = (string) $payload['plan'];
        $this->fingerprint = PlanImportProgress::fingerprint($payload);
        $this->steps = $this->buildSteps();
        $this->index = min($this->progress->stepOf($this->fingerprint, $this->plan), count($this->steps));
    }

    public function plan(): string
    {
        return $this->plan;
    }

    public function step(): int
    {
        return $this->index;
    }

    public function total(): int
    {
        return count($this->steps);
    }

    public function label(): string
    {
        return $this->steps[$this->index]['label'] ?? 'terminé';
    }

    public function isDone(): bool
    {
        return $this->index >= count($this->steps);
    }

    /** Was this run already under way when we picked it up? */
    public function resumed(): bool
    {
        return $this->index > 0;
    }

    /**
     * Runs the next step and moves the cursor, both in one transaction: a
     * crash between the two would replay a step that already landed.
     */
    public function next(): void
    {
        if ($this->isDone()) {
            return;
        }

        $step = $this->steps[$this->index];
        $index = $this->index;

        $this->conn->transactional(function () use ($step, $index): void {
            ($step['run'])();
            $this->progress->record($this->fingerprint, $this->plan, $index + 1, count($this->steps));
        });

        $this->index++;

        if ($this->isDone()) {
            $this->finish();
        }
    }

    /** Runs every remaining step — the caller that has no budget to keep. */
    public function runToEnd(): void
    {
        while (!$this->isDone()) {
            $this->next();
        }
    }

    /**
     * The plan's JSON and the cursor: both belong after the last step,
     * so a run interrupted before the end keeps its resume point.
     */
    private function finish(): void
    {
        if (is_array($this->payload['config'])) {
            (new PlanConfigService())->replace($this->plan, $this->payload['config']);
        }

        $this->progress->clear($this->fingerprint, $this->plan);
    }

    /** @return array<int, array{key: string, label: string, run: callable}> */
    private function buildSteps(): array
    {
        $steps = [];
        $plan = $this->plan;
        $layers = $this->payload['layers'];
        $buildings = $this->payload['buildings'] ?? [];

        /* Structures win the cell: a road drawn under a wall would be created
         * first and the wall refused. Decided once, before the steps split. */
        $roads = TiledMapService::roadsClearOfStructures(
            $layers[TiledMapService::GROUND_ENTITY_LAYER] ?? [],
            $buildings
        );
        $layers[TiledMapService::GROUND_ENTITY_LAYER] = $roads['kept'];
        if ($roads['dropped'] > 0) {
            $this->report->warn($plan, $roads['dropped'] . ' route(s) sous une structure, non posée(s).');
        }

        $steps[] = [
            'key' => 'purge',
            'label' => 'contenu remplacé',
            'run' => fn() => $this->writer->purgeAuthoredRows($plan),
        ];

        foreach (array_chunk($this->writer->neededCoords($plan, $this->payload, $layers), self::CHUNK) as $i => $chunk) {
            $steps[] = [
                'key' => 'coords:' . $i,
                'label' => 'cases (' . ($i + 1) . ')',
                'run' => fn() => $this->writer->insertCoords($plan, $chunk),
            ];
        }

        foreach ($layers as $layer => $rows) {
            if (isset(TiledMapService::ENTITY_LAYERS[$layer]) || $layer === TiledMapService::BUILDINGS_LAYER) {
                continue;
            }
            foreach (array_chunk($rows, self::CHUNK) as $i => $chunk) {
                $steps[] = [
                    'key' => 'rows:' . $layer . ':' . $i,
                    'label' => $layer . ' (' . ($i + 1) . ')',
                    'run' => fn() => $this->writer->insertLayerRows($plan, $layer, $chunk),
                ];
            }
        }

        foreach (array_keys(TiledMapService::ENTITY_LAYERS) as $layer) {
            $rows = $layers[$layer] ?? [];
            $steps[] = [
                'key' => 'entities:' . $layer,
                'label' => $layer . ' (entités)',
                'run' => fn() => TiledMapService::reconcilerFor($layer)->reconcile($plan, $rows),
            ];
        }

        if ($this->payload['buildings'] !== null) {
            foreach ($this->writer->buildingsByLevel($this->payload['coords'], $buildings) as $z => $zRows) {
                $steps[] = [
                    'key' => 'buildings:' . $z,
                    'label' => 'bâtiments (niveau ' . $z . ')',
                    'run' => fn() => $this->writer->placeBuildings($plan, (int) $z, $zRows),
                ];
            }
        }

        return $steps;
    }
}
