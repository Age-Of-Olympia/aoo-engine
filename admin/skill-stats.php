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

$playerCount = $stats->realPlayerCount($includeNpcs);
$actionByPlayer = $stats->playerActionCounts($includeNpcs);
$passiveByPlayer = $stats->playerPassiveCounts($includeNpcs);

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

// PNJ inclusion toggle: a plain GET link that flips the ?npc flag, so the state
// is bookmarkable and needs no JS.
$toggle = $includeNpcs
    ? '<a class="btn btn-sm btn-secondary" href="/admin/skill-stats.php">Joueurs uniquement</a>'
    : '<a class="btn btn-sm btn-outline-secondary" href="/admin/skill-stats.php?npc=1">Inclure les PNJ</a>';
$toggleBar = '<div class="mb-3">' . $toggle
    . ' <span class="text-muted">' . ($includeNpcs ? 'PNJ inclus dans les compteurs par personnage.' : 'Joueurs réels uniquement.')
    . '</span></div>';

echo admin_layout('Compétences — statistiques', $toggleBar . $body, [
    'styles' => ['/admin/css/skill-stats.css'],
]);
