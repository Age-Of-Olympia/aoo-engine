<?php
// admin/terrain-transitions.php
/**
 * Transitions de terrain (autotiling Tiled) — admin dashboard → Cartes.
 *
 * Pour un plan sélectionné, trois étapes sur la couche sol :
 *  1. Classer les tuiles posées : terrain (biome fondable) ou hors terrain
 *     (décor, escaliers, runes…). C'est ce classement — persisté dans
 *     tools/tiled/aoo/terrains.json — qui pilote l'analyse ; sur un serveur
 *     fraîchement déployé le fichier peut être vide : tout classer ici.
 *  2. Auditer : chaque point de coin où 2 à 4 terrains se rencontrent exige
 *     ses tuiles de fondu, sinon le pinceau Terrain de Tiled pose la tuile
 *     la plus proche (morceaux d'autres biomes).
 *  3. Générer les fondus manquants (PNG dans img/tiles/ + wangId) et les
 *     vérifier dans la galerie.
 *
 * La sélection de plan accepte GET (lien depuis les cartes locales) et POST
 * (sélecteur). Filtre de saison partagé de la section Cartes (helpers.php).
 */
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/helpers.php';

use Classes\Db;
use App\Service\CsrfProtectionService;
use App\Service\TerrainTransitionService;
use App\Service\ViewService;

$database = new Db();
$csrf = new CsrfProtectionService();
$viewService = new ViewService($database, 0, 0, 0, 0, 'olympia');

$allPlans = $viewService->getAllPlans();
usort($allPlans, function ($a, $b) {
    $nameA = str_replace('_s2', '', $a->id);
    $nameB = str_replace('_s2', '', $b->id);
    return $nameA === $nameB ? ($b->isS2 <=> $a->isS2) : strcasecmp($nameA, $nameB);
});

$seasonFilter = current_season_filter();
$filteredPlans = array_values(array_filter(
    $allPlans,
    fn(object $p) => plan_matches_season_filter($p, $seasonFilter)
));

// GET (lien depuis les cartes locales) ou POST (sélecteur / actions)
$selectedPlan = optionalString('selected_plan')
    ?? (isset($_GET['selected_plan']) && is_string($_GET['selected_plan']) && $_GET['selected_plan'] !== ''
        ? trim($_GET['selected_plan']) : null);

$isStateChangingPost = $_SERVER['REQUEST_METHOD'] === 'POST'
    && (isset($_POST['classify_tiles']) || isset($_POST['generate_transitions'])
        || isset($_POST['regenerate_transitions']));
if ($isStateChangingPost) {
    try {
        $csrf->validateTokenOrFail($_POST['csrf_token'] ?? null);
    } catch (RuntimeException $e) {
        setFlash('danger', $e->getMessage());
        redirectTo('terrain-transitions.php');
    }
}

// Classement terrain / hors terrain : les cases cochées du formulaire sont
// les terrains ; toute tuile listée non cochée est déclassée.
if ($isStateChangingPost && isset($_POST['classify_tiles']) && $selectedPlan) {
    try {
        $listed = array_filter((array) ($_POST['listed_tiles'] ?? []), 'is_string');
        $checked = array_filter((array) ($_POST['terrain_tiles'] ?? []), 'is_string');
        $result = (new TerrainTransitionService($database))->classifyTiles(
            TerrainTransitionService::GROUND_LAYER,
            array_values($checked),
            array_values(array_diff($listed, $checked))
        );
        $summary = [];
        if ($result['declared'] !== []) {
            $summary[] = count($result['declared']) . ' déclarée(s) terrain (' . implode(', ', $result['declared']) . ')';
        }
        if ($result['undeclared'] !== []) {
            $summary[] = count($result['undeclared']) . ' déclassée(s) (' . implode(', ', $result['undeclared']) . ')';
        }
        setFlash('success', $summary === []
            ? 'Classification inchangée.'
            : 'Classification enregistrée : ' . implode(' ; ', $summary) . '.');
    } catch (Throwable $e) {
        setFlash('danger', 'Échec du classement : ' . $e->getMessage());
    }
}

// Réécriture des PNG des fondus SÉLECTIONNÉS (par ensemble de biomes)
// depuis les images de base actuelles — wangId inchangés. La sélection
// arrive en libellés d'ensembles ; les noms sont recalculés côté serveur.
if ($isStateChangingPost && isset($_POST['regenerate_transitions']) && $selectedPlan) {
    try {
        set_time_limit(600);
        $service = new TerrainTransitionService($database);
        $bySet = $service->planTransitionsBySet($selectedPlan);

        $chosenSets = array_filter((array) ($_POST['regenerate_sets'] ?? []), 'is_string');
        if (!empty($_POST['regenerate_all_plan'])) { // champ caché rempli par le bouton « Tout le plan »
            $chosenSets = array_keys($bySet);
        }

        $names = [];
        foreach ($chosenSets as $setLabel) {
            $names = array_merge($names, $bySet[$setLabel] ?? []);
        }

        if ($names === []) {
            setFlash('warning', 'Aucun ensemble sélectionné — cochez les fondus à réécrire.');
        } else {
            $result = $service->regenerateTransitionImages(TerrainTransitionService::GROUND_LAYER, $names);
            $notice = $result['unparsed'] === [] ? ''
                : ' ⚠ Noms indéchiffrables ignorés : ' . implode(', ', array_slice($result['unparsed'], 0, 10))
                    . (count($result['unparsed']) > 10 ? '…' : '') . '.';
            setFlash('success', $result['regenerated'] . ' fondu(s) réécrit(s) ('
                . count($chosenSets) . ' ensemble(s)) depuis les images de base actuelles.' . $notice);
        }
    } catch (Throwable $e) {
        setFlash('danger', 'Échec de la régénération : ' . $e->getMessage());
    }
}

// Génération des fondus manquants du plan (rapport rendu plus bas)
$transitionReport = null;
if ($isStateChangingPost && isset($_POST['generate_transitions']) && $selectedPlan) {
    try {
        set_time_limit(600); // gros plans : des centaines de fondus PNG à écrire
        $transitionReport = (new TerrainTransitionService($database))->generateForPlan($selectedPlan);
        setFlash('success', $transitionReport['generatedCount'] . ' tuile(s) de transition générée(s) pour '
            . $selectedPlan . ' — re-puller le plan dans Tiled pour recharger les tilesets.');
    } catch (Throwable $e) {
        setFlash('danger', 'Erreur lors de la génération des transitions : ' . $e->getMessage());
    }
}

ob_start();
?>

<div class="container">
    <h3>Transitions de terrain (pinceau Terrain de Tiled)</h3>

    <?= renderFlashMessage() ?>

    <div class="alert alert-info" style="font-size: 13px; line-height: 1.5;">
        <strong>Trois étapes :</strong>
        <ol class="mb-0 mt-1">
            <li><strong>Classer</strong> les tuiles du plan : terrain (biome fondable) ou hors terrain (décor, escaliers, runes…). Le classement vit dans <code style="display:inline">tools/tiled/aoo/terrains.json</code> — sur un serveur neuf ce fichier est vide, tout se classe ici.</li>
            <li><strong>Auditer</strong> : chaque endroit où 2 à 4 terrains se touchent exige ses tuiles de fondu, sinon le pinceau Terrain pose la tuile la plus proche (morceaux d'autres biomes).</li>
            <li><strong>Générer</strong> les fondus manquants (<code style="display:inline">img/tiles/</code> + wangId) et les vérifier dans la galerie.</li>
        </ol>
    </div>

    <div class="card mt-3">
        <div class="card-body py-2">
            <?= render_season_filter($seasonFilter) ?>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-body">
            <form method="post" id="planForm">
                <div class="form-group mb-0">
                    <label for="planSelect">Choisir un plan :</label>
                    <select class="form-control" id="planSelect" name="selected_plan" onchange="this.form.submit()">
                        <option value="">-- Sélectionner un plan (<?= e(season_filter_label($seasonFilter)) ?>) --</option>
                        <?php foreach ($filteredPlans as $plan): ?>
                            <option value="<?= e($plan->id) ?>" <?= selected($selectedPlan === $plan->id) ?>>
                                <?= e($plan->name) ?> (<?= e($plan->id) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($filteredPlans)): ?>
                        <small class="text-muted">Aucun plan ne correspond au filtre « <?= e(season_filter_label($seasonFilter)) ?> » — élargir le filtre ci-dessus.</small>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <?php if ($selectedPlan): ?>
        <?php
        try {
            $terrainService = new TerrainTransitionService($database);
            $classification = $terrainService->planTileClassification($selectedPlan);
            // Toujours ré-auditer au rendu : après classement ou génération,
            // l'état affiché est celui d'après écriture
            $terrainAudit = $terrainService->auditPlan($selectedPlan);
            $terrainCount = count(array_filter($classification, fn(array $t) => $t['isTerrain']));
        ?>

        <div class="card mt-3">
            <div class="card-body">
                <h5 class="card-title">1. Classification des tuiles du plan</h5>
                <p class="text-muted mb-2" style="font-size:13px;line-height:1.5;">
                    Cochez les tuiles qui sont des <strong>terrains</strong> (biomes à fondre entre eux) ;
                    laissez décoché le décor posé au sol (escaliers, lits, runes…). Déclasser une tuile
                    ne détruit rien : ses fondus déjà générés restent en place et elle peut être re-déclarée.
                </p>

                <?php if ($classification === []): ?>
                    <div class="alert alert-info mb-0">Aucune tuile de sol en base pour ce plan.</div>
                <?php else: ?>
                    <?php if ($terrainCount === 0): ?>
                        <div class="alert alert-warning py-1" style="font-size:13px;">
                            <i class="fas fa-exclamation-triangle"></i>
                            Aucune tuile de ce plan n'est classée terrain — probablement un
                            <code style="display:inline">terrains.json</code> vierge sur ce serveur. Commencez par cocher les biomes ci-dessous.
                        </div>
                    <?php endif; ?>

                    <form method="post">
                        <?= $csrf->renderTokenField() ?>
                        <input type="hidden" name="selected_plan" value="<?= e($selectedPlan) ?>">
                        <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:.75rem;">
                            <?php foreach ($classification as $tile): ?>
                                <?php if ($tile['isTransition']): continue; endif; ?>
                                <label style="border:1px solid <?= $tile['isTerrain'] ? '#198754' : '#ddd' ?>;border-radius:.375rem;padding:6px;text-align:center;cursor:pointer;width:86px;font-size:11px;background:<?= $tile['isTerrain'] ? '#f2fbf6' : '#fff' ?>;">
                                    <img src="/img/tiles/<?= e($tile['name']) ?>.png" width="50" height="50" loading="lazy"
                                         style="image-rendering:pixelated;display:block;margin:0 auto 4px;" alt="">
                                    <input type="hidden" name="listed_tiles[]" value="<?= e($tile['name']) ?>">
                                    <input type="checkbox" name="terrain_tiles[]" value="<?= e($tile['name']) ?>" <?= checked($tile['isTerrain']) ?>>
                                    <span style="word-break:break-all;" title="<?= e($tile['name']) ?> — <?= (int) $tile['count'] ?> case(s)"><?= e($tile['name']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <button type="submit" name="classify_tiles" class="btn btn-primary btn-sm">
                            <i class="fas fa-tags"></i> Enregistrer la classification
                        </button>
                        <small class="text-muted ml-2">Écrit tools/tiled/aoo/terrains.json sur ce serveur.</small>
                    </form>

                    <?php $transitionTiles = array_filter($classification, fn(array $t) => $t['isTransition']); ?>
                    <?php if ($transitionTiles !== []): ?>
                        <p class="text-muted mt-2 mb-0" style="font-size:12px;">
                            Fondus déjà posés sur le plan (non classables) :
                            <?= count($transitionTiles) ?> tuile(s) trans_*.
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-body">
                <h5 class="card-title">2. Audit &amp; 3. Génération</h5>

                <?php if (empty($terrainAudit['zLevels'])): ?>
                    <div class="alert alert-info mb-0">Aucune tuile de sol en base pour ce plan.</div>
                <?php else: ?>
                    <table class="table table-sm mb-2" style="font-size:13px;max-width:520px;">
                        <thead><tr><th>Niveau Z</th><th>Cases</th><th>Paires</th><th>Trios</th><th>Quatuors</th></tr></thead>
                        <tbody>
                        <?php foreach ($terrainAudit['zLevels'] as $z => $zStats): ?>
                            <tr>
                                <td>z=<?= e($z) ?></td>
                                <td><?= $zStats['cells'] ?></td>
                                <td><?= $zStats['pairs'] ?></td>
                                <td><?= $zStats['trios'] ?></td>
                                <td><?= $zStats['quads'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php if (!empty($terrainAudit['ignored'])): ?>
                        <p class="text-muted mb-2" style="font-size:12px;">
                            Tuiles hors terrain ignorées (modifiable à l'étape 1) : <?= e(implode(', ', $terrainAudit['ignored'])) ?>
                        </p>
                    <?php endif; ?>

                    <?php
                    $incompleteSets = array_filter($terrainAudit['sets'], fn(array $s) => $s['missing'] > 0);
                    $conflictSets   = array_filter($terrainAudit['sets'], fn(array $s) => $s['conflict']);
                    ?>

                    <?php if (!empty($conflictSets)): ?>
                        <div class="alert alert-warning py-1" style="font-size:13px;">
                            <i class="fas fa-exclamation-triangle"></i> Ensembles ignorés (biomes de même couleur) :
                            <?= e(implode(' ; ', array_map(fn(array $s) => implode(' / ', $s['tiles']), $conflictSets))) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($terrainAudit['missingTiles'] > 0): ?>
                        <details class="alert alert-warning mb-2" style="padding:0;">
                            <summary style="cursor:pointer;padding:.5rem .75rem;font-weight:600;">
                                <i class="fas fa-exclamation-triangle"></i>
                                <?= count($incompleteSets) ?> ensemble(s) incomplet(s)
                                <span class="badge" style="background-color:#f0ad4e;color:#fff;"><?= $terrainAudit['missingTiles'] ?> tuiles à générer</span>
                            </summary>
                            <ul class="mb-0" style="padding:.25rem .75rem .75rem 2.2rem;font-size:13px;line-height:1.6;">
                                <?php foreach ($incompleteSets as $set): ?>
                                    <li><?= e(implode(' / ', $set['tiles'])) ?> — <?= $set['missing'] ?>/<?= $set['total'] ?> manquantes</li>
                                <?php endforeach; ?>
                            </ul>
                        </details>
                        <form method="post" class="d-flex align-items-center gap-3">
                            <?= $csrf->renderTokenField() ?>
                            <input type="hidden" name="selected_plan" value="<?= e($selectedPlan) ?>">
                            <button type="submit" name="generate_transitions" class="btn btn-primary btn-sm">
                                <i class="fas fa-fill-drip"></i> Générer les <?= $terrainAudit['missingTiles'] ?> tuiles manquantes
                            </button>
                            <small class="text-muted">Écrit les PNG dans img/tiles/ et déclare leurs wangId — peut prendre une minute sur un gros plan.</small>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-success py-1 mb-0" style="font-size:13px;">
                            <i class="fas fa-check-circle"></i>
                            Toutes les transitions requises existent (<?= count($terrainAudit['sets']) ?> ensembles de biomes).
                        </div>
                    <?php endif; ?>

                <?php endif; ?>

                <?php $transitionsBySet = $terrainService->planTransitionsBySet($selectedPlan); ?>
                <?php if ($transitionsBySet !== []): ?>
                    <hr>
                    <h6 class="text-muted">Fondus existants du plan — vérifier et régénérer</h6>
                    <p class="text-muted mb-2" style="font-size:12px;">
                        Aperçu des fondus tels que le jeu les rend sur CE serveur. Un ensemble aux couleurs
                        fausses (fondu vers le noir, art d'un biome modifié…) se réécrit depuis les images de
                        base actuelles — les wangId ne changent pas.
                    </p>
                    <form method="post">
                        <?= $csrf->renderTokenField() ?>
                        <input type="hidden" name="selected_plan" value="<?= e($selectedPlan) ?>">
                        <?php foreach ($transitionsBySet as $setLabel => $names): ?>
                            <details class="mb-2" style="border:1px solid #e5e5e5;border-radius:.375rem;">
                                <summary style="cursor:pointer;padding:.4rem .75rem;font-size:13px;font-weight:600;">
                                    <input type="checkbox" name="regenerate_sets[]" value="<?= e($setLabel) ?>"
                                           onclick="event.stopPropagation();">
                                    <?= e($setLabel) ?> <span class="badge bg-secondary"><?= count($names) ?> tuiles</span>
                                </summary>
                                <div style="padding:.5rem .75rem;display:flex;flex-wrap:wrap;gap:4px;">
                                    <?php foreach ($names as $name): ?>
                                        <img src="/img/tiles/<?= e($name) ?>.png" width="50" height="50"
                                             title="<?= e($name) ?>" loading="lazy"
                                             style="image-rendering:pixelated;border:1px solid #ddd;">
                                    <?php endforeach; ?>
                                </div>
                            </details>
                        <?php endforeach; ?>
                        <div class="d-flex align-items-center gap-3 mt-2 flex-wrap">
                            <button type="submit" name="regenerate_transitions" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-sync"></i> Régénérer la sélection
                            </button>
                            <button type="submit" name="regenerate_transitions" class="btn btn-outline-secondary btn-sm"
                                    onclick="this.form.regenerate_all_plan.value=1;"
                                    formnovalidate>
                                <i class="fas fa-sync"></i> Tout le plan (<?= array_sum(array_map('count', $transitionsBySet)) ?> fondus)
                            </button>
                            <input type="hidden" name="regenerate_all_plan" value="">
                            <small class="text-muted">Seuls les fondus des biomes de ce plan sont concernés.</small>
                        </div>
                    </form>
                <?php endif; ?>

                <?php if ($transitionReport !== null && !empty($transitionReport['generated'])): ?>
                    <div class="mt-3">
                        <h6 class="text-muted">Tuiles générées — vérifier les fondus :</h6>
                        <?php foreach ($transitionReport['generated'] as $setLabel => $names): ?>
                            <details class="mb-2" style="border:1px solid #e5e5e5;border-radius:.375rem;">
                                <summary style="cursor:pointer;padding:.4rem .75rem;font-size:13px;font-weight:600;">
                                    <?= e($setLabel) ?> <span class="badge bg-secondary"><?= count($names) ?> tuiles</span>
                                </summary>
                                <div style="padding:.5rem .75rem;display:flex;flex-wrap:wrap;gap:4px;">
                                    <?php foreach ($names as $name): ?>
                                        <img src="/img/tiles/<?= e($name) ?>.png" width="50" height="50"
                                             title="<?= e($name) ?>" loading="lazy"
                                             style="image-rendering:pixelated;border:1px solid #ddd;">
                                    <?php endforeach; ?>
                                </div>
                            </details>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php
        } catch (Throwable $terrainError) {
            echo '<div class="alert alert-danger mt-3">Analyse impossible : ' . e($terrainError->getMessage()) . '</div>';
        }
        ?>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
echo admin_layout('Transitions de terrain', $content);
