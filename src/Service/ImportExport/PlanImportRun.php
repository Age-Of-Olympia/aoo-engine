<?php

namespace App\Service\ImportExport;

use App\Factory\EntityManagerFactory;
use App\Service\PlanConfigService;
use App\Service\TiledMapService;
use Doctrine\DBAL\Connection;

/**
 * One plan of a bundle, loaded step by step.
 *
 * stage() cuts the payload into steps once and stores them in
 * plan_import_steps; a run then loads one step per next(), so a request
 * never holds more than one step, whatever the size of the map. A step
 * handles CHUNK rows, or about BAND for entities and buildings, which cost
 * a query or more each (a band never splits a row of the map).
 */
final class PlanImportRun
{
    private const CHUNK = 2000;
    private const BAND = 100;

    /** Steps of an import abandoned for this long are dropped. */
    private const STALE_DAYS = 7;

    private Connection $conn;
    private PlanImportProgress $progress;
    private PlanImportWriter $writer;

    private int $total;
    private int $index;

    public function __construct(
        private string $fingerprint,
        private string $plan,
        private ImportReport $report,
        ?Connection $conn = null,
        ?PlanImportProgress $progress = null
    ) {
        $this->conn = $conn ?? EntityManagerFactory::getEntityManager()->getConnection();
        $this->progress = $progress ?? new PlanImportProgress($this->conn);
        $this->writer = new PlanImportWriter($this->conn, $this->report);
        $this->total = (int) $this->conn->fetchOne(
            'SELECT COUNT(*) FROM plan_import_steps WHERE fingerprint = ? AND plan = ?',
            [$fingerprint, $plan]
        );
        $this->index = min($this->progress->stepOf($fingerprint, $plan), $this->total);
    }

    /**
     * Stores the steps of a validated payload, unless an earlier call already did.
     *
     * @param array<string, mixed> $payload {@see PlanImporter::payloadFor()}
     */
    public static function stage(array $payload, ImportReport $report, ?Connection $conn = null): self
    {
        $conn ??= EntityManagerFactory::getEntityManager()->getConnection();
        $plan = (string) $payload['plan'];
        $fingerprint = PlanImportProgress::fingerprint($payload);

        $conn->executeStatement(
            'DELETE FROM plan_import_steps WHERE created_at < NOW() - INTERVAL ' . self::STALE_DAYS . ' DAY'
        );

        $staged = $conn->fetchOne(
            'SELECT 1 FROM plan_import_steps WHERE fingerprint = ? AND plan = ? LIMIT 1',
            [$fingerprint, $plan]
        );

        if ($staged === false) {
            $steps = self::buildSteps($payload);

            $conn->transactional(static function () use ($conn, $fingerprint, $plan, $steps): void {
                foreach ($steps as $index => $step) {
                    $conn->executeStatement(
                        'INSERT INTO plan_import_steps (fingerprint, plan, step, label, payload, created_at)
                         VALUES (?, ?, ?, ?, ?, NOW())',
                        [$fingerprint, $plan, $index, $step['label'], json_encode($step, JSON_UNESCAPED_UNICODE)]
                    );
                }
            });
        }

        return new self($fingerprint, $plan, $report, $conn);
    }

    public function plan(): string
    {
        return $this->plan;
    }

    public function fingerprint(): string
    {
        return $this->fingerprint;
    }

    public function step(): int
    {
        return $this->index;
    }

    public function total(): int
    {
        return $this->total;
    }

    public function label(): string
    {
        if ($this->isDone()) {
            return 'terminé';
        }

        return (string) $this->conn->fetchOne(
            'SELECT label FROM plan_import_steps WHERE fingerprint = ? AND plan = ? AND step = ?',
            [$this->fingerprint, $this->plan, $this->index]
        );
    }

    public function isDone(): bool
    {
        return $this->index >= $this->total;
    }

    public function next(): void
    {
        if ($this->isDone()) {
            return;
        }

        $step = $this->load($this->index);
        $index = $this->index;

        $this->conn->transactional(function () use ($step, $index): void {
            $this->run($step);
            $this->progress->record($this->fingerprint, $this->plan, $index + 1, $this->total);
        });

        $this->index++;
    }

    public function finish(): void
    {
        // The config rides on the first step, written once every step has run
        $config = $this->load(0)['config'] ?? null;
        if (is_array($config)) {
            (new PlanConfigService())->replace($this->plan, $config);
        }

        $this->progress->clear($this->fingerprint, $this->plan);
        $this->conn->executeStatement(
            'DELETE FROM plan_import_steps WHERE fingerprint = ? AND plan = ?',
            [$this->fingerprint, $this->plan]
        );
    }

    /** @return array<string, mixed> */
    private function load(int $index): array
    {
        $json = $this->conn->fetchOne(
            'SELECT payload FROM plan_import_steps WHERE fingerprint = ? AND plan = ? AND step = ?',
            [$this->fingerprint, $this->plan, $index]
        );

        return $json === false ? [] : (array) json_decode((string) $json, true);
    }

    /** @param array<string, mixed> $step */
    private function run(array $step): void
    {
        $plan = $this->plan;

        if (isset($step['warn'])) {
            $this->report->warn($plan, $step['warn']);
        }

        match ($step['do']) {
            'purge' => $this->writer->purgeAuthoredRows($plan, $step['levels']),
            'coords' => $this->writer->insertCoords($plan, $step['cells']),
            'rows' => $this->writer->insertLayerRows($plan, $step['layer'], $step['rows']),
            'entities' => TiledMapService::reconcilerFor($step['layer'])
                ->reconcile($plan, $step['rows'], $step['z'], $step['from'], $step['to']),
            'buildings' => $this->writer->placeBuildings($plan, $step['z'], $step['rows'], $step['from'], $step['to']),
            default => throw new \LogicException('Unknown plan import step: ' . $step['do']),
        };
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, array<string, mixed>>
     */
    private static function buildSteps(array $payload): array
    {
        $plan = (string) $payload['plan'];
        $layers = $payload['layers'];
        $buildings = $payload['buildings'] ?? [];

        $roads = TiledMapService::roadsClearOfStructures(
            $layers[TiledMapService::GROUND_ENTITY_LAYER] ?? [],
            $buildings
        );
        $layers[TiledMapService::GROUND_ENTITY_LAYER] = $roads['kept'];

        $cells = PlanImportWriter::neededCoords($payload, $layers);
        $levels = array_values(array_unique(array_column($cells, 2)));
        sort($levels);

        $steps = [['label' => 'contenu remplacé', 'do' => 'purge', 'levels' => $levels, 'config' => $payload['config']]];

        foreach (array_chunk($cells, self::CHUNK) as $i => $chunk) {
            $steps[] = ['label' => 'cases (' . ($i + 1) . ')', 'do' => 'coords', 'cells' => $chunk];
        }

        foreach ($layers as $layer => $rows) {
            if (isset(TiledMapService::ENTITY_LAYERS[$layer]) || $layer === TiledMapService::BUILDINGS_LAYER) {
                continue;
            }
            foreach (array_chunk($rows, self::CHUNK) as $i => $chunk) {
                $steps[] = ['label' => $layer . ' (' . ($i + 1) . ')', 'do' => 'rows', 'layer' => $layer, 'rows' => $chunk];
            }
        }

        foreach (array_keys(TiledMapService::ENTITY_LAYERS) as $layer) {
            foreach (self::bands($layers[$layer] ?? [], $levels) as $i => $band) {
                $step = ['label' => $layer . ' (entités ' . ($i + 1) . ')', 'do' => 'entities', 'layer' => $layer] + $band;
                if ($i === 0 && $layer === TiledMapService::GROUND_ENTITY_LAYER && $roads['dropped'] > 0) {
                    $step['warn'] = $roads['dropped'] . ' route(s) sous une structure, non posée(s).';
                }
                $steps[] = $step;
            }
        }

        if ($payload['buildings'] !== null) {
            foreach (self::bands($buildings, $levels) as $i => $band) {
                $steps[] = ['label' => 'bâtiments (niveau ' . $band['z'] . ', ' . ($i + 1) . ')', 'do' => 'buildings'] + $band;
            }
        }

        return $steps;
    }

    /**
     * Rows cut into bands of about BAND rows per level, never splitting a
     * row of the map. The bands of a level cover every y (the first and last
     * are open), so a band also removes what stands in it and is no longer drawn.
     *
     * @param list<array<string, mixed>> $rows
     * @param list<int> $levels every level of the bundle, even one without rows
     * @return list<array{z: int, from: ?int, to: ?int, rows: list<array<string, mixed>>}>
     */
    private static function bands(array $rows, array $levels): array
    {
        $byLevel = array_fill_keys($levels, []);
        foreach ($rows as $row) {
            $byLevel[(int) $row['z']][(int) $row['y']][] = $row;
        }
        ksort($byLevel);

        $bands = [];
        foreach ($byLevel as $z => $byY) {
            ksort($byY);
            $from = null;
            $band = [];
            foreach ($byY as $y => $yRows) {
                $band = array_merge($band, $yRows);
                if (count($band) >= self::BAND) {
                    $bands[] = ['z' => $z, 'from' => $from, 'to' => $y, 'rows' => $band];
                    [$from, $band] = [$y + 1, []];
                }
            }
            if ($band !== [] || $from === null) {
                $bands[] = ['z' => $z, 'from' => $from, 'to' => null, 'rows' => $band];
            } else {
                // Nothing after the last full band: open it upwards instead of adding an empty one
                $bands[array_key_last($bands)]['to'] = null;
            }
        }

        return $bands;
    }
}
