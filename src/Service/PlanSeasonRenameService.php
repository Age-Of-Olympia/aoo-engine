<?php

namespace App\Service;

/**
 * Normalizes plan slugs against the game's current season: the current
 * season's plans take the clean base name, a displaced archive keeps its
 * own season as suffix.
 *
 * The _sX suffix used to BE the season; it is now only part of the join
 * key. This ceremony, replayable at every season opening, turns
 * `gaia` (S1) + `gaia_s2` (S2, current) into `gaia_s1` + `gaia` — two
 * ordered passes: first move the archives out of the way, then strip the
 * current season's suffixes. Every rename goes through
 * PlanAdminService::renamePlan, which follows the name everywhere
 * (coords, logs, harvest, settings, condition params, minimap PNGs).
 *
 * A plan whose board background relies on the slug-named fallback
 * (img/tiles/<slug>.webp) would silently change art when renamed: its
 * current fallback is pinned as explicit config first.
 */
class PlanSeasonRenameService
{
    private PlanAdminService $admin;
    private PlanConfigService $config;
    private SeasonService $seasons;

    public function __construct(
        ?PlanAdminService $admin = null,
        ?PlanConfigService $config = null,
        ?SeasonService $seasons = null
    ) {
        $this->admin = $admin ?? new PlanAdminService();
        $this->config = $config ?? new PlanConfigService();
        $this->seasons = $seasons ?? new SeasonService();
    }

    /**
     * The renames a run would perform, in execution order, without writing.
     *
     * @return array{
     *     operations: list<array{from: string, to: string, kind: string, pinBg: ?string}>,
     *     skipped: array<string, string>
     * }
     */
    public function preview(): array
    {
        $current = $this->seasons->current();
        $bySlug = [];
        foreach (plans()->all() as $slug => $plan) {
            $bySlug[$slug] = isset($plan->season) ? (int) $plan->season : null;
        }

        $operations = [];
        $skipped = [];
        $planned = $bySlug; // slug => season, updated as operations stack

        // The current season's suffixed plans, each wanting its base name.
        $wanted = [];
        foreach ($bySlug as $slug => $season) {
            if (!preg_match('/^(.+)_s(\d+)$/', $slug, $m)) {
                continue;
            }
            if ($season !== $current) {
                continue; // another season keeps its suffix, it says what it is
            }
            if ((int) $m[2] !== $current) {
                $skipped[$slug] = "suffixe _s{$m[2]} mais saison {$current} en colonne — à réconcilier d'abord";

                continue;
            }
            $wanted[$slug] = $m[1];
        }

        // Pass 1: displace the archives holding a wanted base name.
        foreach ($wanted as $slug => $base) {
            if (!array_key_exists($base, $planned)) {
                continue; // base is free
            }

            $baseSeason = $planned[$base];
            if ($baseSeason === null || $baseSeason === $current) {
                $skipped[$slug] = "« {$base} » est occupé par un plan "
                    . ($baseSeason === null ? 'de toutes saisons' : 'de la saison courante')
                    . ' — conflit à trancher à la main';
                unset($wanted[$slug]);

                continue;
            }

            $archiveSlug = $base . '_s' . $baseSeason;
            if (array_key_exists($archiveSlug, $planned)) {
                $skipped[$slug] = "« {$archiveSlug} » existe déjà, impossible de déplacer l'archive « {$base} »";
                unset($wanted[$slug]);

                continue;
            }

            $operations[] = [
                'from' => $base, 'to' => $archiveSlug, 'kind' => 'archive',
                'pinBg' => $this->bgToPin($base),
            ];
            unset($planned[$base]);
            $planned[$archiveSlug] = $baseSeason;
        }

        // Pass 2: strip the current season's suffixes.
        foreach ($wanted as $slug => $base) {
            $operations[] = [
                'from' => $slug, 'to' => $base, 'kind' => 'strip',
                'pinBg' => $this->bgToPin($slug),
            ];
            unset($planned[$slug]);
            $planned[$base] = $current;
        }

        return ['operations' => $operations, 'skipped' => $skipped];
    }

    /**
     * Executes the previewed renames, one plan at a time — a failure stops
     * the run and is reported, what already ran stays done (each rename is
     * its own transaction).
     *
     * @return array{
     *     renamed: list<array{from: string, to: string, kind: string, pinnedBg: ?string}>,
     *     skipped: array<string, string>,
     *     failed: ?array{from: string, to: string, error: string}
     * }
     */
    public function apply(): array
    {
        $plan = $this->preview();
        $report = ['renamed' => [], 'skipped' => $plan['skipped'], 'failed' => null];

        foreach ($plan['operations'] as $op) {
            try {
                if ($op['pinBg'] !== null) {
                    // Raw write on purpose: parse() would re-check the file,
                    // which bgToPin() just did.
                    $this->config->write($op['from'], ['bg' => $op['pinBg']]);
                }
                $this->admin->renamePlan($op['from'], $op['to']);
                $report['renamed'][] = [
                    'from' => $op['from'], 'to' => $op['to'], 'kind' => $op['kind'],
                    'pinnedBg' => $op['pinBg'],
                ];
            } catch (\Throwable $e) {
                $report['failed'] = ['from' => $op['from'], 'to' => $op['to'], 'error' => $e->getMessage()];
                break;
            }
        }

        PlanService::forget();

        return $report;
    }

    /**
     * The slug-named background file a plan currently falls back to, when
     * it has no explicit bg — null when it has one, or no file exists.
     */
    private function bgToPin(string $slug): ?string
    {
        $model = plans()->read($slug);
        if ($model === false || !empty($model->bg)) {
            return null;
        }

        $root = dirname(__DIR__, 2) . '/';
        foreach (['webp', 'png'] as $ext) {
            $path = 'img/tiles/' . $slug . '.' . $ext;
            if (is_file($root . $path)) {
                return $path;
            }
        }

        return null;
    }
}
