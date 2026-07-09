<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\Action\ActionCatalogService;
use App\Service\Action\ActionPassiveCatalogService;
use App\Service\SkillOwnershipService;
use App\Service\SkillStatsService;
use App\View\Player\SkillStatsView;

$ownership = new SkillOwnershipService();
$stats = new SkillStatsService();

// "Inclure les PNJ" toggle: folds player_type='npc' into the per-player counts
// and averages. Adoption figures below stay real-only (catalogue coverage).
$includeNpcs = !empty($_GET['npc']);

// Activity filter (Tous / Actifs / Inactifs): narrows the per-player counts and
// averages to the chosen population. Same INACTIVE_TIME cutoff as the roster.
$allowedStatuses = [
    SkillStatsService::STATUS_ALL,
    SkillStatsService::STATUS_ACTIVE,
    SkillStatsService::STATUS_INACTIVE,
];
$status = in_array($_GET['status'] ?? '', $allowedStatuses, true)
    ? $_GET['status']
    : SkillStatsService::STATUS_ALL;

$playerCount = $stats->realPlayerCount($includeNpcs, $status);
$actionByPlayer = $stats->playerActionCounts($includeNpcs, $status);
$passiveByPlayer = $stats->playerPassiveCounts($includeNpcs, $status);

// Per-player: merge action + passive counts by id (action list is the spine —
// it already holds every real player and is sorted most-equipped first).
$passiveMap = [];
foreach ($passiveByPlayer as $row) {
    $passiveMap[$row['id']] = $row['count'];
}
$players = [];
foreach ($actionByPlayer as $row) {
    $players[] = [
        'id'       => $row['id'],
        'name'     => $row['name'],
        'actions'  => $row['count'],
        'passives' => $passiveMap[$row['id']] ?? 0,
    ];
}

$totalActions = array_sum(array_column($actionByPlayer, 'count'));
$totalPassives = array_sum(array_column($passiveByPlayer, 'count'));
$summary = [
    'players'     => $playerCount,
    'avgActions'  => $playerCount > 0 ? $totalActions / $playerCount : 0.0,
    'avgPassives' => $playerCount > 0 ? $totalPassives / $playerCount : 0.0,
];

// Adoption: every catalogued action/passive with its real-owner count, desc.
$actionCounts = $ownership->actionOwnerCounts();
$actionAdoption = [];
foreach ((new ActionCatalogService())->listActions() as $action) {
    $actionAdoption[] = [
        'key'   => $action->getName(),
        'label' => $action->getDisplayName(),
        'count' => $actionCounts[$action->getName()] ?? 0,
    ];
}

$passiveCounts = $ownership->passiveOwnerCounts();
$passiveAdoption = [];
foreach ((new ActionPassiveCatalogService())->listPassives() as $passive) {
    $passiveAdoption[] = [
        'key'   => $passive->getName(),
        'label' => $passive->getDisplayName(),
        'count' => $passiveCounts[$passive->getId()] ?? 0,
    ];
}

$byCountDesc = static fn(array $a, array $b): int => $b['count'] <=> $a['count'];
usort($actionAdoption, $byCountDesc);
usort($passiveAdoption, $byCountDesc);

$body = (new SkillStatsView())->render($summary, $actionAdoption, $passiveAdoption, $players);

// Filter bar: plain GET links so state is bookmarkable and needs no JS. Each
// link preserves the OTHER filter's current value.
//
// Build a URL from the two filter dimensions (npc + status), omitting defaults.
$buildUrl = static function (bool $npc, string $st): string {
    $params = [];
    if ($npc) {
        $params['npc'] = '1';
    }
    if ($st !== SkillStatsService::STATUS_ALL) {
        $params['status'] = $st;
    }
    return '/admin/skill-stats.php' . ($params ? '?' . http_build_query($params) : '');
};

// PNJ inclusion toggle (keeps the current status).
$npcToggle = $includeNpcs
    ? '<a class="btn btn-sm btn-secondary" href="' . e($buildUrl(false, $status)) . '">Joueurs uniquement</a>'
    : '<a class="btn btn-sm btn-outline-secondary" href="' . e($buildUrl(true, $status)) . '">Inclure les PNJ</a>';

// Activity segmented control (keeps the current npc choice).
$statusLabels = [
    SkillStatsService::STATUS_ALL      => 'Tous',
    SkillStatsService::STATUS_ACTIVE   => 'Actifs',
    SkillStatsService::STATUS_INACTIVE => 'Inactifs',
];
$statusButtons = '';
foreach ($statusLabels as $value => $label) {
    $active = $value === $status;
    $cls = $active ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-outline-primary';
    $statusButtons .= '<a class="' . $cls . '" href="' . e($buildUrl($includeNpcs, $value)) . '">' . e($label) . '</a> ';
}

$notes = ($includeNpcs ? 'PNJ inclus' : 'Joueurs réels uniquement')
    . ' · ' . ($status === SkillStatsService::STATUS_ALL
        ? 'tous statuts'
        : ($status === SkillStatsService::STATUS_ACTIVE ? 'actifs seulement' : 'inactifs seulement'));

$toggleBar = '<div class="mb-3 d-flex flex-wrap align-items-center" style="gap:.5rem">'
    . '<span class="btn-group">' . $statusButtons . '</span>'
    . $npcToggle
    . '<span class="text-muted">' . e($notes) . '</span>'
    . '</div>';

echo admin_layout('Compétences — statistiques', $toggleBar . $body, [
    'styles' => ['/admin/css/skill-stats.css'],
]);
