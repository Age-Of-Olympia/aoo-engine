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

// Editable characters are real players and PNJs only. PNJs carry negative ids,
// so we can't gate on "> 0"; instead resolve then restrict by player_type, so
// other negative-id system rows (not 'npc') stay off-limits as before.
$summary = $id !== 0 ? (new PlayerSkillsService())->getPlayerSummary($id) : null;
if ($summary === null || !in_array($summary['player_type'], ['real', 'npc'], true)) {
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

// Some owned actions are legitimate but not in the action catalog: names
// granted directly in players_actions with no `actions` row behind them.
// This catalog-driven view would otherwise hide them, making a player look
// like they lack skills they actually have. Surface them as owned rows in
// their own group so the sheet stays truthful.
// (L'attaque de base était le cas emblématique, sous le nom fantôme
// « attaquer » ; elle est désormais catalogée en melee + distance —
// cf. Version20260725110000.)
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
