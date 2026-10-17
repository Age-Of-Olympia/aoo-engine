<?php
/**
 * Monitoring dashboard: a verdict, a strip of every check coloured by its
 * status, then one panel per section, worst checks first. The checks live in
 * App\Service\Monitoring; this page only renders their results.
 */
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Enum\MonitoringStatus;
use App\Service\Monitoring\MonitoringResult;
use App\Service\Monitoring\MonitoringService;

const STATUS_ORDER = [MonitoringStatus::Error, MonitoringStatus::Warning, MonitoringStatus::Running, MonitoringStatus::Ok];

/** "1 erreur" / "3 erreurs" */
function mon_count(int $n, string $singular, string $plural): string
{
    return $n . ' ' . ($n > 1 ? $plural : $singular);
}

/**
 * Inline SVG sparkline of a series, oldest point on the left.
 *
 * @param list<int> $series
 */
function mon_sparkline(array $series): string
{
    if (count($series) < 2) {
        return '';
    }
    $min = min($series);
    $span = max(max($series) - $min, 1);
    $step = 120 / (count($series) - 1);
    $points = [];
    foreach ($series as $i => $value) {
        $points[] = round($i * $step, 1) . ',' . round(28 - ($value - $min) / $span * 24, 1);
    }
    [$lastX, $lastY] = explode(',', end($points));

    return '<svg class="mon-spark" viewBox="-3 0 126 32" preserveAspectRatio="xMinYMid meet" aria-hidden="true">'
        . '<polyline points="' . implode(' ', $points) . '" />'
        . '<circle cx="' . $lastX . '" cy="' . $lastY . '" r="2.5" /></svg>';
}

$rank = static fn (MonitoringResult $r): int => (int) array_search($r->status, STATUS_ORDER, true);

$sections = [];
$tally = array_fill_keys(array_map(static fn ($s) => $s->value, STATUS_ORDER), 0);
foreach ((new MonitoringService())->results() as $result) {
    $sections[$result->section][] = $result;
    $tally[$result->status->value]++;
}
// A crashed check comes first: everything after it may be missing a card.
if (isset($sections['Monitoring'])) {
    $sections = ['Monitoring' => $sections['Monitoring']] + $sections;
}
foreach ($sections as &$results) {
    usort($results, static fn ($a, $b): int => $rank($a) <=> $rank($b));
}
unset($results);

// ----- Verdict -----
$errors = $tally[MonitoringStatus::Error->value];
$warnings = $tally[MonitoringStatus::Warning->value];
$verdict = match (true) {
    $errors > 0   => mon_count($errors, 'erreur à corriger', 'erreurs à corriger'),
    $warnings > 0 => mon_count($warnings, 'point à surveiller', 'points à surveiller'),
    default       => 'Rien à signaler',
};
$verdictStatus = $errors > 0 ? 'error' : ($warnings > 0 ? 'warning' : 'ok');
$sub = ($errors > 0 && $warnings > 0 ? 'Et ' . mon_count($warnings, 'point à surveiller', 'points à surveiller') . '. ' : '')
    . array_sum($tally) . ' contrôles, relevés à ' . date('H\hi') . '.';

// ----- Strip + panels -----
$strip = '';
$panels = '';
$n = 0;
foreach ($sections as $section => $results) {
    $tiles = '';
    $rows = '';
    $counts = [];
    foreach ($results as $result) {
        $id = 'chk-' . ++$n;
        $status = $result->status->value;
        $counts[$status] = ($counts[$status] ?? 0) + 1;

        $tiles .= '<a class="mon-tile is-' . $status . '" href="#' . $id . '" title="' . e($result->title . ' : ' . $result->summary) . '">'
            . '<span class="mon-sr">' . e($result->title . ', ' . $result->status->label()) . '</span></a>';

        $head = '<span class="mon-check-title">' . e($result->title) . '</span>'
            . ($result->status === MonitoringStatus::Ok ? '' : '<span class="mon-badge">' . e($result->status->label()) . '</span>')
            . ($result->figure === null ? '' : '<span class="mon-figure">' . e($result->figure) . '</span>')
            . '<span class="mon-check-summary">' . e($result->summary) . '</span>'
            . mon_sparkline($result->series);

        if ($result->lines === []) {
            $rows .= '<li class="mon-check is-' . $status . '" id="' . $id . '"><div class="mon-check-head">' . $head . '</div></li>';
            continue;
        }

        $lines = '';
        foreach ($result->lines as $line) {
            $lines .= '<tr' . ($line['status'] ? ' class="is-' . $line['status']->value . '"' : '') . '>'
                . ($line['label'] === '' ? '<td colspan="2">' : '<th scope="row">' . e($line['label']) . '</th><td>')
                . e($line['text']) . '</td></tr>';
        }
        $rows .= '<li class="mon-check is-' . $status . '" id="' . $id . '"><details' . ($status === 'error' ? ' open' : '') . '>'
            . '<summary class="mon-check-head">' . $head . '</summary>'
            . '<div class="mon-lines"><table>' . $lines . '</table></div></details></li>';
    }

    $tallyHtml = '';
    foreach (STATUS_ORDER as $s) {
        if (!empty($counts[$s->value])) {
            $tallyHtml .= '<span class="is-' . $s->value . '">' . $counts[$s->value] . ' ' . e(mb_strtolower($s->label())) . '</span>';
        }
    }

    $strip .= '<div class="mon-strip-group"><div class="mon-tiles">' . $tiles . '</div>'
        . '<span class="mon-strip-name">' . e($section) . '</span></div>';
    $panels .= '<section class="mon-panel"><header class="mon-panel-head"><h2>' . e($section) . '</h2>'
        . '<p class="mon-tally">' . $tallyHtml . '</p></header><ul class="mon-checks">' . $rows . '</ul></section>';
}

$content = '<div class="mon">'
    . '<header class="mon-head is-' . $verdictStatus . '"><div>'
    . '<h1 class="mon-verdict">' . e($verdict) . '</h1>'
    . '<p class="mon-sub">' . e($sub) . '</p></div>'
    . '<a class="btn btn-sm btn-outline-primary mon-refresh" href="/admin/monitoring.php">Actualiser</a></header>'
    . '<nav class="mon-strip" aria-label="Tous les contrôles">' . $strip . '</nav>'
    . '<div class="mon-grid">' . $panels . '</div></div>';

echo admin_layout('Monitoring', $content, ['styles' => ['/admin/css/monitoring.css']]);
