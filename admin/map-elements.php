<?php
/**
 * Éléments posés (admin dashboard → Cartes → Éléments) : LE lien
 * case ↔ effet — marcher sur une case applique l'effet du même nom que
 * l'élément posé dessus (Player::go → add_effect). Jusqu'ici seul le
 * moteur écrivait map_elements (saignements, traces, import Tiled) ;
 * cette page pose et retire à la case, avec durée ou permanent
 * (endTime = 0, jamais purgé par le cron horaire delete_elements).
 * Ce qui définit l'effet lui-même reste admin → Effets.
 */
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/helpers.php';

use App\Service\CsrfProtectionService;
use App\Service\MapElementService;
use App\Service\NpcAdminService;

$csrf = new CsrfProtectionService();
$service = new MapElementService();

$plans = (new NpcAdminService())->listPlans();
$plan = stringWithDefault('plan', (string) ($_GET['plan'] ?? ''));
if (!in_array($plan, $plans, true)) {
    $plan = in_array('gaia', $plans, true) ? 'gaia' : ($plans[0] ?? '');
}
$withFootprints = !empty($_REQUEST['traces']);
$backTo = 'map-elements.php?plan=' . urlencode($plan) . ($withFootprints ? '&traces=1' : '');

$isStateChangingPost = $_SERVER['REQUEST_METHOD'] === 'POST'
    && (isset($_POST['element_place']) || isset($_POST['element_remove'])
        || isset($_POST['element_remove_bulk']) || isset($_POST['element_purge']));
if ($isStateChangingPost) {
    try {
        $csrf->validateTokenOrFail($_POST['csrf_token'] ?? null);
    } catch (RuntimeException $e) {
        setFlash('danger', $e->getMessage());
        redirectTo($backTo);
    }

    try {
        if (isset($_POST['element_place'])) {
            $name = trim((string) ($_POST['name'] ?? ''));
            $hours = trim((string) ($_POST['hours'] ?? ''));
            if ($hours !== '' && (!is_numeric($hours) || (float) $hours <= 0)) {
                throw new RuntimeException('Durée invalide : un nombre d\'heures positif, ou vide pour permanent.');
            }
            $service->place(
                $name,
                (int) ($_POST['x'] ?? 0),
                (int) ($_POST['y'] ?? 0),
                (int) ($_POST['z'] ?? 0),
                $plan,
                $hours === '' ? null : (int) round((float) $hours * 3600)
            );
            setFlash('success', "Élément « {$name} » posé en ("
                . (int) ($_POST['x'] ?? 0) . ',' . (int) ($_POST['y'] ?? 0) . ') — '
                . ($hours === '' ? 'permanent' : "pour {$hours} h")
                . '. L\'effet du même nom s\'appliquera à qui marche dessus.');
        } elseif (isset($_POST['element_remove'])) {
            // Le bouton de ligne porte l'id dans sa value : la table
            // entière vit dans UN formulaire (cases de sélection).
            $service->remove((int) $_POST['element_remove']);
            setFlash('success', 'Élément retiré.');
        } elseif (isset($_POST['element_remove_bulk'])) {
            $ids = array_map('intval', (array) ($_POST['ids'] ?? []));
            if ($ids === []) {
                throw new RuntimeException('Aucun élément sélectionné.');
            }
            $removed = $service->removeMany($ids);
            setFlash('success', "{$removed} élément(s) retiré(s).");
        } elseif (isset($_POST['element_purge'])) {
            $purged = $service->purgeExpired();
            setFlash('success', "Purge des expirés : {$purged} élément(s) supprimé(s)"
                . ' (tous plans confondus — le travail du cron horaire).');
        }
    } catch (Throwable $e) {
        setFlash('danger', $e->getMessage());
    }
    redirectTo($backTo); // PRG
}

$entries = $plan !== '' ? $service->listByPlan($plan, $withFootprints) : [];
$placeable = $service->placeableNames();

ob_start();
?>

<div class="container">
    <h3>Éléments posés</h3>

    <?= renderFlashMessage() ?>

    <div class="alert alert-info" style="font-size: 13px; line-height: 1.5;">
        Un élément posé sur une case applique <strong>l'effet du même nom</strong> à qui marche
        dessus (boue, ronce…) — le comportement de l'effet se règle dans
        <a href="/admin/effects.php">Effets</a>. Durée vide = permanent (jamais purgé) ;
        reposer un élément prolonge sa durée. La case doit exister (entrée coords du plan).
    </div>

    <div class="card mt-3">
        <div class="card-body py-2 d-flex align-items-center gap-3 flex-wrap">
            <form method="get" class="d-flex align-items-center gap-2 flex-wrap mb-0">
                <span class="text-muted" style="font-size:13px;"><i class="fas fa-map"></i> Plan :</span>
                <select name="plan" class="form-control form-control-sm d-inline-block" style="width:auto"
                        onchange="this.form.submit()">
                    <?php foreach ($plans as $candidate): ?>
                        <option value="<?= e($candidate) ?>" <?= $candidate === $plan ? 'selected' : '' ?>>
                            <?= e($candidate) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label class="mb-0 ml-3" style="font-size:13px;">
                    <input type="checkbox" name="traces" value="1" <?= $withFootprints ? 'checked' : '' ?>
                           onchange="this.form.submit()">
                    afficher les traces de pas
                </label>
            </form>
        </div>
    </div>

    <?php if ($plan !== ''): ?>
    <div class="card mt-3">
        <div class="card-body">
            <h5 class="card-title">Poser un élément — plan <?= e($plan) ?></h5>
            <form method="post" class="d-flex align-items-end gap-3 flex-wrap">
                <?= $csrf->renderTokenField() ?>
                <input type="hidden" name="plan" value="<?= e($plan) ?>">
                <?php if ($withFootprints): ?><input type="hidden" name="traces" value="1"><?php endif; ?>
                <div class="form-group mb-0">
                    <label style="font-size:13px;">Élément</label>
                    <select name="name" class="form-control form-control-sm" required>
                        <?php foreach ($placeable as $candidate): ?>
                            <option value="<?= e($candidate) ?>"><?= e($candidate) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group mb-0" style="width:5.5rem;">
                    <label style="font-size:13px;">x</label>
                    <input type="number" class="form-control form-control-sm" name="x" required>
                </div>
                <div class="form-group mb-0" style="width:5.5rem;">
                    <label style="font-size:13px;">y</label>
                    <input type="number" class="form-control form-control-sm" name="y" required>
                </div>
                <div class="form-group mb-0" style="width:5.5rem;">
                    <label style="font-size:13px;" title="Étage : 0 = sol, négatif = souterrain">z</label>
                    <input type="number" class="form-control form-control-sm" name="z" value="0" required>
                </div>
                <div class="form-group mb-0" style="width:8rem;">
                    <label style="font-size:13px;">Durée (heures)</label>
                    <input type="number" class="form-control form-control-sm" name="hours" min="1" step="1"
                           placeholder="permanent">
                </div>
                <button type="submit" name="element_place" value="1" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> Poser
                </button>
            </form>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-body">
            <?php $expired = count(array_filter($entries,
                fn (array $entry) => $entry['endTime'] !== 0 && $entry['endTime'] <= time())); ?>
            <form method="post" class="mb-0">
                <?= $csrf->renderTokenField() ?>
                <input type="hidden" name="plan" value="<?= e($plan) ?>">
                <?php if ($withFootprints): ?><input type="hidden" name="traces" value="1"><?php endif; ?>

                <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                    <h5 class="card-title mb-0">Éléments — <?= e($plan) ?></h5>
                    <span class="badge bg-secondary"><?= count($entries) ?> posés</span>
                    <button type="submit" name="element_purge" value="1" class="btn btn-sm btn-outline-secondary ml-auto"
                            onclick="return confirm('Purger tous les éléments expirés (tous plans confondus) — le travail du cron horaire ?');"
                            title="Supprime les éléments à durée écoulée, sur TOUS les plans — les permanents survivent.">
                        <i class="fas fa-broom"></i> Purger les expirés<?= $expired > 0 ? ' (' . $expired . ' ici)' : '' ?>
                    </button>
                </div>

                <?php if ($entries === []): ?>
                    <div class="alert alert-info mb-0">Aucun élément sur ce plan<?=
                        $withFootprints ? '' : ' (traces de pas masquées)' ?>.</div>
                <?php else: ?>
                    <table class="table table-sm table-striped" style="font-size:13px;" data-admin-list data-page-size="40">
                        <thead><tr>
                            <th style="width:28px;"><input type="checkbox"
                                onclick="this.closest('table').querySelectorAll('input[name=\'ids[]\']').forEach(c => c.checked = this.checked)"
                                title="Tout sélectionner"></th>
                            <th></th><th>Élément</th><th>Case (x,y,z)</th><th>Expiration</th><th></th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($entries as $entry): ?>
                            <?php $image = $service->imagePath($entry['name']); ?>
                            <tr>
                                <td><input type="checkbox" name="ids[]" value="<?= $entry['id'] ?>"></td>
                                <td style="width:48px;">
                                    <?php if ($image !== ''): ?>
                                        <img src="/<?= e($image) ?>" height="32" loading="lazy"
                                             style="object-fit:contain;" alt="">
                                    <?php endif; ?>
                                </td>
                                <td><code><?= e($entry['name']) ?></code></td>
                                <td><?= $entry['x'] ?>,<?= $entry['y'] ?>,<?= $entry['z'] ?></td>
                                <td>
                                    <?php if ($entry['endTime'] === 0): ?>
                                        <span class="badge badge-info">permanent</span>
                                    <?php elseif ($entry['endTime'] <= time()): ?>
                                        <span class="badge badge-secondary">expiré (purge au prochain cron)</span>
                                    <?php else: ?>
                                        <?= e(date('d/m/Y H:i', $entry['endTime'])) ?>
                                    <?php endif; ?>
                                </td>
                                <td style="white-space:nowrap;">
                                    <button type="submit" name="element_remove" value="<?= $entry['id'] ?>"
                                            class="btn btn-sm btn-outline-danger"
                                            onclick="return confirm('Retirer « <?= e($entry['name']) ?> » de la case (<?= $entry['x'] ?>,<?= $entry['y'] ?>) ?');">
                                        Retirer
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <button type="submit" name="element_remove_bulk" value="1" class="btn btn-sm btn-outline-danger"
                            onclick="return confirm('Retirer tous les éléments sélectionnés ?');">
                        <i class="fas fa-trash"></i> Retirer la sélection
                    </button>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
echo admin_layout('Éléments posés', $content);
