<?php
/**
 * Rendements par plan (admin dashboard → Cartes → Rendements) : les plans où
 * fouiller ne rapporte rien, et les exceptions de `race_harvest` au rendement
 * des types — ajout et édition.
 *
 * Accès : hérite du niveau du menu des cartes (AdminMenuAccessService).
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\CsrfProtectionService;
use App\Service\Map\HarvestCatalogService;

$csrf = new CsrfProtectionService();
$service = new HarvestCatalogService();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_harvest'])) {
    try {
        $csrf->validateTokenOrFail($_POST['csrf_token'] ?? null);
        $saved = $service->save(is_array($_POST['rows'] ?? null) ? $_POST['rows'] : []);
        setFlash('success', sprintf('%d rendement(s) enregistré(s).', $saved));
    } catch (\Throwable $e) {
        setFlash('danger', 'Échec de l\'enregistrement : ' . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_harvest'])) {
    try {
        $csrf->validateTokenOrFail($_POST['csrf_token'] ?? null);
        $added = $service->add((string) ($_POST['plan'] ?? ''), (int) ($_POST['race_id'] ?? 0));
        setFlash($added ? 'success' : 'warning', $added
            ? 'Exception ajoutée : à régler ci-dessous.'
            : 'Rien ajouté : ce type a déjà une exception sur ce plan, ou le plan ou le type est inconnu.');
    } catch (\Throwable $e) {
        setFlash('danger', 'Échec de l\'ajout : ' . $e->getMessage());
    }
}

$configured = $service->configured();

ob_start();
?>
<h1>Rendements par plan</h1>

<?= renderFlashMessage() ?>

<?php $missing = $service->plansMissingYields(); ?>
<?php if ($missing !== []): ?>
    <div class="alert alert-danger">
        <strong>Sur <?= count($missing) ?> plan(s), fouiller ne rapporte RIEN.</strong>
        Ces plans contiennent des ressources récoltables dont le type n'a aucun rendement défini.
        <table class="table table-sm mb-0 mt-2">
            <thead><tr><th>Plan</th><th>Ressources posées</th><th>Types sans rendement</th></tr></thead>
            <tbody>
            <?php foreach (array_slice($missing, 0, 25) as $row): ?>
                <tr>
                    <td><?= e($row['plan']) ?></td>
                    <td><?= (int) $row['resources'] ?></td>
                    <td><?= e(implode(', ', array_slice($row['types'], 0, 6)))
                            . (count($row['types']) > 6 ? ', …' : '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (count($missing) > 25): ?>
            <p class="mb-0 mt-1"><?= count($missing) - 25 ?> autre(s) plan(s) dans le même cas.</p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="card mt-3">
        <div class="card-body">
            <h5 class="card-title">Exceptions par plan</h5>
            <p class="text-muted" style="font-size: 13px;">
                Le rendement d'un récoltable est défini sur son type (Types récoltables) et vaut sur tous
                les plans ; un plan n'a rien à déclarer pour que fouiller rapporte.
                Une ligne ici est une <strong>exception</strong> : le même arbre donne moins dans le désert
                que dans la forêt. Une case vide reprend la valeur du type ; <strong>0</strong> signifie
                « jamais sur ce plan ».
            </p>

            <form method="post" class="form-inline mb-3">
                <input type="hidden" name="csrf_token" value="<?= e($csrf->generateToken()) ?>" />
                <select name="plan" class="form-select mr-2" style="width: auto;" required>
                    <?php foreach (plans()->all() as $slug => $plan): ?>
                        <option value="<?= e($slug) ?>"><?= e($slug) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="race_id" class="form-select mr-2" style="width: auto;" required>
                    <?php foreach ($service->resourceTypes() as $raceId => $type): ?>
                        <option value="<?= (int) $raceId ?>"><?= e($type) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="add_harvest" value="1" class="btn btn-outline-primary">Ajouter une exception</button>
            </form>

            <?php if ($configured !== []): ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e($csrf->generateToken()) ?>" />

                <table class="table table-striped table-sm">
                    <thead><tr>
                        <th>Plan</th><th>Type</th>
                        <th title="Vide : l'objet défini sur le type. Rempli : cet objet, sur ce plan.">Donne</th>
                        <th title="Chance sur cent d'épuisement à la récolte. Vide : valeur du type. 0 : jamais épuisé sur ce plan.">Épuisement</th>
                        <th title="Chance sur mille de repousse à chaque passage du cron. Vide : valeur du type. 0 : ne repousse jamais sur ce plan.">Repousse</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($configured as $row): ?>
                        <?php $key = e($row['plan'] . '|' . $row['race_id']); ?>
                        <tr>
                            <td><?= e($row['plan']) ?></td>
                            <td><?= e($row['type']) ?></td>
                            <td><input type="text" class="form-select" style="width: 10rem;"
                                       name="rows[<?= $key ?>][item]" value="<?= e($row['item']) ?>" /></td>
                            <td><input type="number" min="0" max="100" class="form-select" style="width: 6rem;"
                                       name="rows[<?= $key ?>][exhaust]" value="<?= $row['exhaust'] === null ? '' : (int) $row['exhaust'] ?>" /></td>
                            <td><input type="number" min="0" max="1000" class="form-select" style="width: 6rem;"
                                       name="rows[<?= $key ?>][regrow]" value="<?= $row['regrow'] === null ? '' : (int) $row['regrow'] ?>" /></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <button type="submit" name="save_harvest" value="1" class="btn btn-primary">Enregistrer</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
<?php

echo admin_layout('Rendements', ob_get_clean());
