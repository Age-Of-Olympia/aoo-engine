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

$playerCount = $stats->realPlayerCount();
$actionByPlayer = $stats->playerActionCounts();
$passiveByPlayer = $stats->playerPassiveCounts();

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

echo admin_layout('Compétences — statistiques', $body, [
    'styles' => ['/admin/css/skill-stats.css'],
]);
