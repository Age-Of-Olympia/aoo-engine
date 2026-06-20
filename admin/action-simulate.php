<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');

use App\Service\Action\ActionCatalogService;
use App\Service\Action\ActionSimulationService;

$id = (int) ($_GET['id'] ?? 0);
$action = (new ActionCatalogService())->getActionById($id);

$esc = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$actorGet = is_array($_GET['actor'] ?? null) ? $_GET['actor'] : [];
$targetGet = is_array($_GET['target'] ?? null) ? $_GET['target'] : [];

ob_start();
if ($action === null) {
    echo '<div class="alert alert-danger">Action introuvable.</div>';
} else {
    $computeParams = null;
    foreach ($action->getConditions() as $condition) {
        if (str_contains($condition->getConditionType(), 'Compute')) {
            $computeParams = $condition->getParameters() ?? [];
            break;
        }
    }
    ?>
    <h1>Simuler : <?= $esc($action->getDisplayName()) ?></h1>
    <p><a href="/admin/action-editor.php?id=<?= (int) $action->getId() ?>" class="btn btn-sm btn-outline-secondary">&larr; Éditer</a></p>

    <?php if ($computeParams === null): ?>
        <div class="alert alert-info">Cette action n'a pas de jet d'opposition à simuler.</div>
    <?php else:
        $actorTrait = (string) ($computeParams['actorRollType'] ?? '');
        $targetParts = explode('/', (string) ($computeParams['targetRollType'] ?? ''));
        ?>
        <form method="get" class="card" style="max-width:520px">
            <input type="hidden" name="id" value="<?= (int) $action->getId() ?>">
            <input type="hidden" name="sim" value="1">
            <div class="card-header"><h3 class="card-title">Statistiques hypothétiques</h3></div>
            <div class="card-body">
                <div class="form-group"><label>Acteur — <?= $esc($actorTrait) ?></label><input class="form-control" type="number" name="actor[<?= $esc($actorTrait) ?>]" value="<?= $esc($actorGet[$actorTrait] ?? 10) ?>"></div>
                <?php foreach ($targetParts as $part): ?>
                    <div class="form-group"><label>Cible — <?= $esc($part) ?></label><input class="form-control" type="number" name="target[<?= $esc($part) ?>]" value="<?= $esc($targetGet[$part] ?? 10) ?>"></div>
                <?php endforeach; ?>
                <div class="form-group"><label>Jet acteur forcé (optionnel)</label><input class="form-control" type="number" name="forceActor" value="<?= $esc($_GET['forceActor'] ?? '') ?>"></div>
                <div class="form-group"><label>Jet cible forcé (optionnel)</label><input class="form-control" type="number" name="forceTarget" value="<?= $esc($_GET['forceTarget'] ?? '') ?>"></div>
                <button class="btn btn-primary" type="submit">Simuler</button>
            </div>
        </form>

        <?php if (isset($_GET['sim'])):
            $actorStats = array_map('intval', $actorGet);
            $targetStats = array_map('intval', $targetGet);
            $forcedActor = ($_GET['forceActor'] ?? '') !== '' ? (int) $_GET['forceActor'] : null;
            $forcedTarget = ($_GET['forceTarget'] ?? '') !== '' ? (int) $_GET['forceTarget'] : null;
            $sim = (new ActionSimulationService())->simulateRoll($action, $actorStats, $targetStats, $forcedActor, $forcedTarget);
            if ($sim !== null): ?>
                <div class="card mt-3" style="max-width:520px">
                    <div class="card-header"><h3 class="card-title">Résultat : <?= $sim->hit ? '<span class="badge badge-success">TOUCHE</span>' : '<span class="badge badge-danger">RATÉ</span>' ?></h3></div>
                    <div class="card-body">
                        <p>Acteur (<?= $esc($sim->actorTrait) ?> = <?= $sim->actorTraitValue ?>) : jet <?= $sim->actorRoll ?> + <?= $sim->actorBonus ?> = <strong><?= $sim->actorTotal ?></strong></p>
                        <p>Cible (<?= $esc($sim->targetTrait) ?> = <?= $sim->targetTraitValue ?>) : jet <?= $sim->targetRoll ?> + <?= $sim->targetBonus ?> = <strong><?= $sim->targetTotal ?></strong></p>
                        <p class="text-muted">Touche si total acteur &ge; total cible. Effets, passifs, dégâts et critiques ne sont pas encore simulés.</p>
                    </div>
                </div>
            <?php endif;
        endif;
    endif;
}
$content = ob_get_clean();
echo admin_layout('Simuler', $content);
