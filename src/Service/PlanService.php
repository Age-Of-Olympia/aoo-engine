<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * Single read gateway to plan configuration, now stored in the DB (plans,
 * plan_z_levels) instead of datas/private/plans/<plan>.json.
 *
 * read() returns the shape the JSON files used to have — same keys, same
 * absent-key semantics, `false` when the plan has no row (the contract
 * Json::decode had for a missing file: several callers hide characters on a
 * falsy plan config). Call sites keep their `$planJson->…` reads unchanged.
 *
 * Plan lookups happen on hot paths (every board render), so read models are
 * kept in a per-request cache; writers must call forget().
 */
class PlanService
{
    /** @var array<string, object|false> Per-request cache, keyed by slug. */
    private static array $cache = [];

    private Connection $conn;

    public function __construct(?Connection $conn = null)
    {
        $this->conn = $conn ?? EntityManagerFactory::getEntityManager()->getConnection();
    }

    /** JSON-file-shaped configuration of a plan, or false when unknown. */
    public function read(string $slug): object|false
    {
        if (!array_key_exists($slug, self::$cache)) {
            $row = $this->conn->fetchAssociative('SELECT * FROM plans WHERE slug = ?', [$slug]);

            self::$cache[$slug] = $row === false
                ? false
                : $this->buildModel($row, $this->levelsFor([(int) $row['id']]));
        }

        return self::$cache[$slug];
    }

    public function exists(string $slug): bool
    {
        return $this->read($slug) !== false;
    }

    /**
     * Every configured plan, keyed by slug (formerly Json::get_all('plans')).
     *
     * @return array<string, object>
     */
    public function all(): array
    {
        $rows = $this->conn->fetchAllAssociative('SELECT * FROM plans ORDER BY slug');
        $levels = $this->levelsFor(array_map(static fn(array $r): int => (int) $r['id'], $rows));

        $models = [];
        foreach ($rows as $row) {
            $slug = (string) $row['slug'];
            $models[$slug] = self::$cache[$slug] = $this->buildModel($row, $levels);
        }

        return $models;
    }

    /**
     * The plans of one season, keyed by slug — the current game season when
     * none is given. A plan without a season (NULL) belongs to all of them.
     *
     * @return array<string, object>
     */
    public function forSeason(?int $season = null): array
    {
        $season ??= (new SeasonService())->current();

        return array_filter(
            $this->all(),
            static fn(object $plan): bool => !isset($plan->season) || (int) $plan->season === $season
        );
    }

    /** Invalidate the per-request cache after a write (one slug, or all). */
    public static function forget(?string $slug = null): void
    {
        if ($slug === null) {
            self::$cache = [];
        } else {
            unset(self::$cache[$slug]);
        }
    }

    /**
     * @param list<int> $planIds
     * @return array<int, list<array<string, mixed>>> level rows grouped by plan id
     */
    private function levelsFor(array $planIds): array
    {
        if ($planIds === []) {
            return [];
        }

        $grouped = [];
        foreach ($this->conn->fetchAllAssociative(
            'SELECT * FROM plan_z_levels WHERE plan_id IN (?) ORDER BY z',
            [$planIds],
            [ArrayParameterType::INTEGER]
        ) as $row) {
            $grouped[(int) $row['plan_id']][] = $row;
        }

        return $grouped;
    }

    /**
     * One plan row (+ its level rows) → the legacy JSON object shape.
     * Absent optional values omit their key, as the files did — callers
     * test with isset()/empty().
     *
     * @param array<string, mixed>                    $row
     * @param array<int, list<array<string, mixed>>> $levelsByPlan
     */
    private function buildModel(array $row, array $levelsByPlan): object
    {
        $model = [
            'name'              => (string) $row['name'],
            'player_visibility' => (bool) $row['player_visibility'],
            'visibleByDefault'  => (bool) $row['visible_by_default'],
            'verticalScrolling' => (bool) $row['vertical_scrolling'],
        ];

        foreach ([
            'season'        => ['season', 'int'],
            'shortName'     => ['short_name', 'string'],
            'x'             => ['x', 'int'],
            'y'             => ['y', 'int'],
            'pnj'           => ['pnj', 'int'],
            'size'          => ['size', 'int'],
            'bg'            => ['bg', 'string'],
            'mask'          => ['mask', 'string'],
            'scrollingMask' => ['scrolling_mask', 'float'],
            'shade_step'    => ['shade_step', 'float'],
            'shade_max'     => ['shade_max', 'int'],
            'shade_color'   => ['shade_color', 'string'],
        ] as $key => [$column, $type]) {
            if ($row[$column] !== null) {
                settype($row[$column], $type);
                $model[$key] = $row[$column];
            }
        }

        if ($row['visible_bounds_min_x'] !== null && $row['visible_bounds_max_x'] !== null
            && $row['visible_bounds_min_y'] !== null && $row['visible_bounds_max_y'] !== null
        ) {
            $model['visibleBoundsMinX'] = (int) $row['visible_bounds_min_x'];
            $model['visibleBoundsMaxX'] = (int) $row['visible_bounds_max_x'];
            $model['visibleBoundsMinY'] = (int) $row['visible_bounds_min_y'];
            $model['visibleBoundsMaxY'] = (int) $row['visible_bounds_max_y'];
        }

        if ($row['biomes'] !== null) {
            $decoded = json_decode((string) $row['biomes']);
            if (is_array($decoded)) {
                $model['biomes'] = $decoded;
            }
        }

        $zLevels = [];
        foreach ($levelsByPlan[(int) $row['id']] ?? [] as $level) {
            $entry = [
                'z'      => (int) $level['z'],
                'z-name' => (string) $level['name'],
            ];
            if ($level['map_unavailable']) {
                $entry['MapUnavailable'] = true;
            } elseif ($level['visible_bounds_min_x'] !== null && $level['visible_bounds_max_x'] !== null
                && $level['visible_bounds_min_y'] !== null && $level['visible_bounds_max_y'] !== null
            ) {
                $entry['visibleBoundsMinX'] = (int) $level['visible_bounds_min_x'];
                $entry['visibleBoundsMaxX'] = (int) $level['visible_bounds_max_x'];
                $entry['visibleBoundsMinY'] = (int) $level['visible_bounds_min_y'];
                $entry['visibleBoundsMaxY'] = (int) $level['visible_bounds_max_y'];
            }
            $zLevels[] = (object) $entry;
        }
        if ($zLevels !== []) {
            $model['z_levels'] = $zLevels;
        }

        return (object) $model;
    }
}
