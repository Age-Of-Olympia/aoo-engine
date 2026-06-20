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
    $service = new ActionSimulationService();
    $traits = $service->relevantTraits($action);
    ?>
    <h1>Simuler : <?= $esc($action->getDisplayName()) ?></h1>
    <p><a href="/admin/action-editor.php?id=<?= (int) $action->getId() ?>" class="btn btn-sm btn-outline-secondary">&larr; Éditer</a></p>

    <?php if ($traits['actor'] === [] && $traits['target'] === []): ?>
        <div class="alert alert-info">Cette action n'a rien à simuler (ni jet d'opposition ni dégâts typés).</div>
    <?php else: ?>
        <form method="get" class="card" style="max-width:520px">
            <input type="hidden" name="id" value="<?= (int) $action->getId() ?>">
            <input type="hidden" name="sim" value="1">
            <div class="card-header"><h3 class="card-title">Statistiques hypothétiques</h3></div>
            <div class="card-body">
                <?php foreach ($traits['actor'] as $trait): ?>
                    <div class="form-group"><label>Acteur — <?= $esc($trait) ?></label><input class="form-control" type="number" name="actor[<?= $esc($trait) ?>]" value="<?= $esc($actorGet[$trait] ?? 10) ?>"></div>
                <?php endforeach; ?>
                <?php foreach ($traits['target'] as $trait): ?>
                    <div class="form-group"><label>Cible — <?= $esc($trait) ?></label><input class="form-control" type="number" name="target[<?= $esc($trait) ?>]" value="<?= $esc($targetGet[$trait] ?? 10) ?>"></div>
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
            $roll = $service->simulateRoll($action, $actorStats, $targetStats, $forcedActor, $forcedTarget);
            $damage = $service->simulateDamage($action, $actorStats, $targetStats);
            ?>
            <?php if ($roll !== null): ?>
                <div class="card mt-3" style="max-width:520px">
                    <div class="card-header"><h3 class="card-title">Jet : <?= $roll->hit ? '<span class="badge badge-success">TOUCHE</span>' : '<span class="badge badge-danger">RATÉ</span>' ?></h3></div>
                    <div class="card-body">
                        <p>Acteur (<?= $esc($roll->actorTrait) ?> = <?= $roll->actorTraitValue ?>) : jet <?= $roll->actorRoll ?> + <?= $roll->actorBonus ?> = <strong><?= $roll->actorTotal ?></strong></p>
                        <p>Cible (<?= $esc($roll->targetTrait) ?> = <?= $roll->targetTraitValue ?>) : jet <?= $roll->targetRoll ?> + <?= $roll->targetBonus ?> = <strong><?= $roll->targetTotal ?></strong></p>
                        <p class="text-muted">Touche si total acteur &ge; total cible.</p>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($damage !== null): ?>
                <div class="card mt-3" style="max-width:520px">
                    <div class="card-header"><h3 class="card-title">Dégâts (sur touche) : <strong><?= $damage->total ?></strong></h3></div>
                    <div class="card-body">
                        <p>Base <?= $damage->actorDamages ?> - <?= $damage->targetDefense ?> + bonus <?= $damage->additionalDamages ?> (minimum 1).</p>
                        <p class="text-muted">Distance, critiques, encaisse, passifs et effets ne sont pas encore simulés.</p>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
<?php
}
$content = ob_get_clean();
echo admin_layout('Simuler', $content);
