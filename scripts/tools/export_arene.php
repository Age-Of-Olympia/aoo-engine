<?php
/**
 * Exports the arena captures into a self-contained directory plus a timeline
 * manifest. The SVG transformation lives in App\Service\ScreenshotExportService;
 * this script only walks the files. No database access.
 *
 * Modes:
 *
 *   bundle (default)  Copies the frames and the distinct assets keeping the
 *                     tree, embeds the useful CSS in each SVG. Assets are shared
 *                     by every frame.
 *
 *   standalone        One SVG per frame, images inlined as deduplicated base64.
 *                     For frames shared on their own.
 *
 * Usage:
 *   php scripts/tools/export_arene.php [--mode=bundle|standalone]
 *                                      [--source=img/arene] [--out=export/arene]
 *                                      [--docroot=.]
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Service\ScreenshotExportService;

$options = getopt('', ['mode::', 'source::', 'out::', 'docroot::']);

$mode    = $options['mode']    ?? 'bundle';
$docroot = rtrim($options['docroot'] ?? getcwd(), '/');
$source  = rtrim($options['source']  ?? $docroot . '/img/arene', '/');
$out     = rtrim($options['out']     ?? $docroot . '/export/arene', '/');

if (!in_array($mode, ['bundle', 'standalone'], true)) {
    fwrite(STDERR, "Mode inconnu : {$mode}. Attendu bundle ou standalone.\n");
    exit(1);
}

if (!is_dir($source)) {
    fwrite(STDERR, "Dossier source introuvable : {$source}\n");
    exit(1);
}

$frames = glob($source . '/*.svg') ?: [];
sort($frames); // names carry the timestamp: name order is time order

if ($frames === []) {
    fwrite(STDERR, "Aucune capture dans {$source}\n");
    exit(1);
}

@mkdir($out, 0755, true);

$export    = new ScreenshotExportService($docroot);
$assetsVus = [];
$timeline  = [];

foreach ($frames as $frame) {
    $svg  = (string) file_get_contents($frame);
    $base = basename($frame);

    foreach ($export->referencesExternes($svg) as $ref) {
        $assetsVus[$ref] = true;
    }

    $svg = $mode === 'standalone'
        ? $export->autonomiser($svg)
        : $export->preparerPourBundle($svg);

    file_put_contents($out . '/' . $base, $svg);

    $events = preg_replace('/\.svg$/', '.json', $frame);
    if (is_readable($events)) {
        $payload = json_decode((string) file_get_contents($events), true);
        if (is_array($payload)) {
            $timeline[] = $payload;
            copy($events, $out . '/' . basename($events));
        }
    }
}

// Bundle mode: assets live alongside, shared by every frame.
$assetsCopies = 0;
if ($mode === 'bundle') {
    foreach (array_keys($assetsVus) as $ref) {
        $src = $docroot . '/' . ltrim($ref, '/');
        if (!is_readable($src)) {
            continue;
        }
        $dst = $out . '/' . ltrim($ref, '/');
        @mkdir(dirname($dst), 0755, true);
        if (copy($src, $dst)) {
            $assetsCopies++;
        }
    }
}

usort($timeline, static fn(array $a, array $b): int => ($a['at_ms'] ?? 0) <=> ($b['at_ms'] ?? 0));
file_put_contents(
    $out . '/timeline.json',
    json_encode($timeline, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
);

$nbEvents = array_sum(array_map(static fn(array $f): int => count($f['events'] ?? []), $timeline));

// A missing asset renders as a black or empty cell: report it.
$manquants = $mode === 'standalone'
    ? $export->assetsManquants()
    : array_values(array_filter(array_keys($assetsVus), static fn(string $r): bool => !is_readable($docroot . '/' . ltrim($r, '/'))));

echo "mode          : {$mode}\n";
echo "frames        : " . count($frames) . "\n";
echo "assets        : " . count($assetsVus) . " distincts"
    . ($mode === 'bundle' ? ", {$assetsCopies} copiés" : ", inclus en base64") . "\n";
echo "events        : {$nbEvents} sur " . count($timeline) . " frames\n";
echo "destination   : {$out}\n";

if ($manquants !== []) {
    echo "\nATTENTION : " . count($manquants) . " asset(s) introuvable(s), ils rendront en case vide :\n";
    foreach ($manquants as $ref) {
        echo "  - {$ref}\n";
    }
}
