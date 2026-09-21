<?php

namespace App\Service\ImportExport;

use App\Interface\ObjectExporterInterface;
use App\Service\PlanConfigService;
use App\Service\TiledMapService;
use Classes\Db;
use InvalidArgumentException;

/**
 * Exports a plan (local map) as a natural-key payload: the identity is the
 * plan name, every cell is carried by (x, y, z) — no database id, the bundle
 * is portable between environments.
 *
 * A payload is the plan's JSON file (verbatim), all its coords (to preserve
 * empty z levels) and its authorable layers. map_items (runtime loot),
 * player-built rows and endTime are excluded — same rules as the Tiled
 * export.
 *
 * Size: a bundle is a single file. A 200x200 plan weighs a few MB of JSON —
 * sized for the admin; gzip on the endpoint before splitting if that ever
 * becomes a problem.
 *
 * Unlike the other families, "export everything" is heavy: the admin mostly
 * offers the single export through exportOne().
 */
final class PlanExporter implements ObjectExporterInterface
{
    private ?Db $db;
    private ?TiledMapService $tiledMap;
    private ?PlanConfigService $planConfig;

    public function __construct(?Db $db = null, ?TiledMapService $tiledMap = null, ?PlanConfigService $planConfig = null)
    {
        // Lazy: instantiation must not open a DB connection
        $this->db = $db;
        $this->tiledMap = $tiledMap;
        $this->planConfig = $planConfig;
    }

    public function objectType(): string
    {
        return 'plan';
    }

    public function exportAll(): array
    {
        $plans = array_keys(($this->tiledMap ??= new TiledMapService())->listPlans());
        sort($plans);

        return array_map(fn(string $plan): array => $this->exportOne($plan), $plans);
    }

    /**
     * @return array<string, mixed>
     */
    public function exportOne(string $plan): array
    {
        $this->tiledMap ??= new TiledMapService();
        $this->planConfig ??= new PlanConfigService();

        return [
            'plan'   => $plan,
            'config' => $this->planConfig->readFull($plan),
            'coords' => $this->allCoords($plan),
            'layers' => $this->tiledMap->exportAllLayers($plan),
        ];
    }

    /**
     * Plans have no Doctrine entity: the single export goes through
     * exportOne() (string natural key), not toArray().
     */
    public function toArray(object $entity): array
    {
        throw new InvalidArgumentException('PlanExporter : utiliser exportOne(string $plan).');
    }

    /**
     * Compact [x, y, z] triplets — ~4x lighter than objects, and carrying the
     * cells without content (empty but existing z levels).
     *
     * @return list<array{0: int, 1: int, 2: int}>
     */
    private function allCoords(string $plan): array
    {
        $res = ($this->db ??= new Db())->exe(
            'SELECT x, y, z FROM coords WHERE plan = ? ORDER BY z, y, x',
            array($plan)
        );

        $coords = [];
        while ($row = $res->fetch_assoc()) {
            $coords[] = [(int) $row['x'], (int) $row['y'], (int) $row['z']];
        }

        return $coords;
    }
}
