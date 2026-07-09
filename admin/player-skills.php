<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\Action\ActionCatalogService;
use App\Service\Action\ActionPassiveCatalogService;
use App\Service\CsrfProtectionService;
use App\Service\PlayerActionsService;
use App\Service\PlayerSkillsService;
use App\Service\PlayerPassiveService;
use App\View\Player\PlayerSkillsView;

$id = (int) ($_GET['id'] ?? 0);

// PNJs carry negative ids, so gate on "not zero" rather than "> 0" — the editor
// must reach PNJ characters too. getPlayerSummary has no type filter.
$summary = $id !== 0 ? (new PlayerSkillsService())->getPlayerSummary($id) : null;
if ($summary === null) {
    setFlash('warning', 'Joueur introuvable.');
    redirectTo('/admin/players.php');
}

$ownedActionNames = (new PlayerActionsService())->getActions($id);
$ownedPassiveIds = array_map(
    static fn($passive) => $passive->getId(),
    (new PlayerPassiveService())->getPassivesByPlayerId($id)
);

$actions = [];
$catalogActionNames = [];
foreach ((new ActionCatalogService())->listActions() as $action) {
    $catalogActionNames[$action->getName()] = true;
    $actions[] = [
        'key'      => $action->getName(),
        'label'    => $action->getDisplayName(),
        'sub'      => 'niv.' . $action->getLevel() . ' · ' . action_type_label($action),
        'category' => $action->getCategory() ?? '—',
        'owned'    => in_array($action->getName(), $ownedActionNames, true),
        'editable' => true,
        'field'    => 'actions',
        'value'    => $action->getName(),
    ];
}

// Some owned actions are legitimate but not in the action catalog — most
// notably 'attaquer', the base attack (melee AND distance), which the catalog
// does not model. This catalog-driven view would otherwise hide them, making a
// player look like they lack their basic attack. Surface them as owned rows in
// their own group so the skills stays truthful.
$orphanActions = [];
foreach ($ownedActionNames as $name) {
    if (!isset($catalogActionNames[$name])) {
        $orphanActions[] = [
            'key'      => $name,
            'label'    => $name,
            'sub'      => 'hors catalogue',
            'category' => 'Hors catalogue',
            'owned'    => true,
            'editable' => false,
        ];
    }
}
$actions = array_merge($orphanActions, $actions);

$passives = [];
foreach ((new ActionPassiveCatalogService())->listPassives() as $passive) {
    $passives[] = [
        'key'      => $passive->getName(),
        'label'    => $passive->getDisplayName(),
        'sub'      => $passive->getType() . ' · niv.' . $passive->getLevel(),
        'category' => $passive->getCategoryRender(),
        'owned'    => in_array($passive->getId(), $ownedPassiveIds, true),
        'editable' => true,
        'field'    => 'passives',
        'value'    => (string) $passive->getId(),
    ];
}

$body = (new PlayerSkillsView())->render(
    $summary,
    $actions,
    $passives,
    (new CsrfProtectionService())->renderTokenField()
);

echo admin_layout($summary['name'] . ' — compétences', renderFlashMessage() . $body, [
    'styles' => ['/admin/css/player-skills.css'],
]);
