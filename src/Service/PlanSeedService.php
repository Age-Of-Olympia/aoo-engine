<?php

namespace App\Service;

use App\Entity\Plan;
use App\Entity\PlanZLevel;
use App\Factory\EntityManagerFactory;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Seeds the `plans` / `plan_z_levels` tables from the legacy plan JSONs
 * (datas/{public,private}/plans/*.json) — one run per environment, from the
 * web root where datas/ exists (migrations run from the git checkout, where
 * it does not: the races lesson).
 *
 * Create-only: an existing row is never touched — after the switch the DB is
 * authoritative and admin edits must survive a replay. Dead keys (exits,
 * enters, id, num_z_levels) are dropped knowingly; any OTHER unknown key is
 * reported so a server-side surprise surfaces at seed time instead of
 * disappearing silently.
 */
class PlanSeedService
{
    /** Keys consumed by the seed. */
    private const KNOWN_KEYS = [
        'name', 'shortName', 'x', 'y', 'player_visibility', 'visibleByDefault',
        'pnj', 'size', 'bg', 'mask', 'scrollingMask', 'verticalScrolling',
        'shade_step', 'shade_max', 'shade_color',
        'visibleBoundsMinX', 'visibleBoundsMaxX', 'visibleBoundsMinY', 'visibleBoundsMaxY',
        'biomes', 'z_levels',
    ];

    /** Vestigial keys, dropped on purpose (inter-plan travel died in 066f7b6c). */
    private const DEAD_KEYS = ['exits', 'enters', 'id', 'num_z_levels'];

    private ?EntityManagerInterface $em;

    public function __construct(?EntityManagerInterface $em = null)
    {
        $this->em = $em;
    }

    private function em(): EntityManagerInterface
    {
        return $this->em ??= EntityManagerFactory::getEntityManager();
    }

    /** Plan slugs on disk, from both the public and the private folders. */
    public function planNames(): array
    {
        $root = dirname(__DIR__, 2);
        $names = [];

        foreach (['public', 'private'] as $where) {
            foreach (glob($root . '/datas/' . $where . '/plans/*.json') ?: [] as $file) {
                $names[basename($file, '.json')] = true;
            }
        }

        $names = array_keys($names);
        sort($names);

        return $names;
    }

    /**
     * What a run would do, without writing.
     *
     * @return array{
     *     entries: list<array{slug: string, name: string, inDb: bool, zLevels: int, warnings: list<string>}>,
     *     unreadable: list<string>
     * }
     */
    public function preview(): array
    {
        $existing = $this->existingSlugs();
        $entries = [];
        $unreadable = [];

        foreach ($this->planNames() as $slug) {
            $json = json()->decode('plans', $slug);
            if (!is_object($json)) {
                $unreadable[] = $slug;

                continue;
            }

            $warnings = [];
            $plan = $this->parse($slug, $json, $warnings);

            $entries[] = [
                'slug' => $slug,
                'name' => $plan->getName(),
                'inDb' => isset($existing[$slug]),
                'zLevels' => count($plan->getZLevels()),
                'warnings' => $warnings,
            ];
        }

        return ['entries' => $entries, 'unreadable' => $unreadable];
    }

    /**
     * Create-only seed run.
     *
     * @return array{
     *     created: list<string>,
     *     skipped: list<string>,
     *     unreadable: list<string>,
     *     warnings: array<string, list<string>>
     * }
     */
    public function seed(): array
    {
        $existing = $this->existingSlugs();
        $report = ['created' => [], 'skipped' => [], 'unreadable' => [], 'warnings' => []];

        foreach ($this->planNames() as $slug) {
            if (isset($existing[$slug])) {
                $report['skipped'][] = $slug;

                continue;
            }

            $json = json()->decode('plans', $slug);
            if (!is_object($json)) {
                $report['unreadable'][] = $slug;

                continue;
            }

            $warnings = [];
            $this->em()->persist($this->parse($slug, $json, $warnings));

            $report['created'][] = $slug;
            if ($warnings !== []) {
                $report['warnings'][$slug] = $warnings;
            }
        }

        $this->em()->flush();
        PlanService::forget();

        return $report;
    }

    /** @return array<string, true> */
    private function existingSlugs(): array
    {
        $slugs = [];
        foreach ($this->em()->getConnection()->fetchFirstColumn('SELECT slug FROM plans') as $slug) {
            $slugs[(string) $slug] = true;
        }

        return $slugs;
    }

    /**
     * One decoded JSON file → a Plan aggregate, normalizing as it goes.
     *
     * @param list<string> $warnings
     */
    private function parse(string $slug, object $json, array &$warnings): Plan
    {
        foreach (array_keys(get_object_vars($json)) as $key) {
            if (in_array($key, self::KNOWN_KEYS, true) || in_array($key, self::DEAD_KEYS, true)) {
                continue;
            }
            $warnings[] = "clé inconnue ignorée : {$key}";
        }

        $plan = new Plan($slug, trim((string) ($json->name ?? '')) !== '' ? (string) $json->name : $slug);

        $shortName = trim((string) ($json->shortName ?? ''));
        $plan->setShortName($shortName !== '' ? $shortName : null);

        $plan->setX(isset($json->x) && is_numeric($json->x) ? (int) $json->x : null);
        $plan->setY(isset($json->y) && is_numeric($json->y) ? (int) $json->y : null);

        // Absent key always meant "characters visible"
        $plan->setPlayerVisibility(!isset($json->player_visibility) || $json->player_visibility !== false);
        $plan->setVisibleByDefault(!empty($json->visibleByDefault));

        $plan->setPnj(isset($json->pnj) && is_numeric($json->pnj) ? (int) $json->pnj : null);
        $plan->setSize(isset($json->size) && is_numeric($json->size) ? (int) $json->size : null);

        foreach (['bg', 'mask'] as $image) {
            $value = trim((string) ($json->{$image} ?? ''));
            if ($value === '') {
                continue;
            }
            if (!preg_match('#^img/#', $value)) {
                $warnings[] = "{$image} « {$value} » n'est pas un chemin img/… (le jeu l'ignorait déjà)";
            }
            $plan->{'set' . ucfirst($image)}($value);
        }

        $plan->setScrollingMask(
            isset($json->scrollingMask) && is_numeric($json->scrollingMask) ? (float) $json->scrollingMask : null
        );

        /* The legacy reader only tested PRESENCE of the key — a file saying
         * `"verticalScrolling": false` still scrolled vertically. Normalized
         * here to a real boolean: present = vertical. */
        if (isset($json->verticalScrolling)) {
            $plan->setVerticalScrolling(true);
            if (!$json->verticalScrolling) {
                $warnings[] = 'verticalScrolling à faux traité comme vertical (sémantique de présence du JSON)';
            }
        }

        $plan->setShadeStep(isset($json->shade_step) && is_numeric($json->shade_step) ? (float) $json->shade_step : null);
        $plan->setShadeMax(isset($json->shade_max) && is_numeric($json->shade_max) ? (int) $json->shade_max : null);
        $shadeColor = trim((string) ($json->shade_color ?? ''));
        $plan->setShadeColor($shadeColor !== '' ? $shadeColor : null);

        if (isset($json->visibleBoundsMinX, $json->visibleBoundsMaxX, $json->visibleBoundsMinY, $json->visibleBoundsMaxY)) {
            $plan->setVisibleBounds(
                (int) $json->visibleBoundsMinX,
                (int) $json->visibleBoundsMaxX,
                (int) $json->visibleBoundsMinY,
                (int) $json->visibleBoundsMaxY
            );
        }

        if (isset($json->biomes) && is_array($json->biomes)) {
            $biomes = [];
            foreach ($json->biomes as $biome) {
                $entry = ['wall' => (string) ($biome->wall ?? ''), 'ressource' => (string) ($biome->ressource ?? '')];
                if (isset($biome->exhaust)) {
                    $entry['exhaust'] = (int) $biome->exhaust;
                }
                if (isset($biome->regrow)) {
                    $entry['regrow'] = (int) $biome->regrow;
                }
                $biomes[] = $entry;
            }
            $plan->setBiomes($biomes);
        }

        foreach ($json->z_levels ?? [] as $level) {
            if (!isset($level->z) || !is_numeric($level->z)) {
                $warnings[] = 'niveau z sans coordonnée z, ignoré';

                continue;
            }

            $z = (int) $level->z;
            if ($plan->getZLevel($z) !== null) {
                $warnings[] = "niveau z={$z} en double, premier gardé";

                continue;
            }

            $zLevel = new PlanZLevel($plan, $z);
            $zLevel->setName((string) ($level->{'z-name'} ?? 'Niveau ' . $z));
            $zLevel->setMapUnavailable(!empty($level->MapUnavailable));

            if (!$zLevel->isMapUnavailable()
                && isset($level->visibleBoundsMinX, $level->visibleBoundsMaxX, $level->visibleBoundsMinY, $level->visibleBoundsMaxY)
            ) {
                $zLevel->setVisibleBounds(
                    (int) $level->visibleBoundsMinX,
                    (int) $level->visibleBoundsMaxX,
                    (int) $level->visibleBoundsMinY,
                    (int) $level->visibleBoundsMaxY
                );
            }

            $plan->addZLevel($zLevel);
        }

        return $plan;
    }
}
