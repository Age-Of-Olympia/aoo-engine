<?php
/**
 * Seed des rendements par plan depuis les JSON legacy
 * (admin dashboard → Cartes → Rendements).
 *
 * Raison d'être : la migration RaceHarvestTable ne crée que la table — au
 * déploiement, les migrations s'exécutent depuis le checkout git où datas/
 * (gitignoré) n'existe pas. Cette page verse depuis la racine web, où datas/
 * existe. Même patron que dialog-seed.php.
 *
 * Relançable : une ligne déjà versée est mise à jour, donc un JSON corrigé se
 * reverse. Page à retirer quand les rendements ne viendront plus des JSON.
 *
 * Accès : hérite du niveau du menu des cartes (AdminMenuAccessService).
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\CsrfProtectionService;
use App\Service\Map\HarvestCatalogService;

$csrf = new CsrfProtectionService();
$service = new HarvestCatalogService();
$report = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['seed_harvest'])) {
    try {
        $csrf->validateTokenOrFail($_POST['csrf_token'] ?? null);
        $report = $service->seed();
        setFlash('success', sprintf(
            'Seed appliqué : %d rendement(s) versé(s) — %d plan(s), %d type(s).',
            $report['written'],
            $report['plans'],
            $report['types']
        ));
    } catch (\Throwable $e) {
        setFlash('danger', 'Échec du seed : ' . $e->getMessage());
    }
}

try {
    $preview = $service->preview();
} catch (\Throwable $e) {
    $preview = ['rows' => [], 'unreadable' => [], 'unknown' => []];
    setFlash('danger', 'Analyse impossible : ' . $e->getMessage());
}

$byPlan = [];

foreach ($preview['rows'] as $row) {
    $byPlan[$row['plan']][] = $row;
}

ksort($byPlan);

ob_start();
?>
<h1>Rendements — seed depuis les JSON de plan</h1>

<?= renderFlashMessage() ?>

<div class="alert alert-info" style="font-size: 13px; line-height: 1.5;">
    <strong>Pourquoi cette page ?</strong>
    La migration <code style="display:inline">RaceHarvestTable</code> ne crée que la table
    <code style="display:inline">race_harvest</code> : en production les migrations s'exécutent depuis le checkout git
    où <code style="display:inline">datas/</code> n'existe pas. Ce bouton verse les rendements depuis les
    <code style="display:inline">biomes[]</code> des JSON de plan de cet environnement.
    <ul class="mb-0 mt-1">
        <li>Le rendement dépend du <strong>plan</strong> et pas seulement du type : le même mur donne des taux différents selon l'endroit.</li>
        <li>Un plan <strong>illisible est nommé, jamais devin&eacute;</strong> — aucun taux par défaut n'est inventé.</li>
        <li>Relançable : une ligne déjà versée est mise à jour, donc un JSON corrigé se reverse.</li>
        <li>Page temporaire, à retirer quand les rendements ne viendront plus des JSON.</li>
    </ul>
</div>

<?php if ($preview['unreadable'] !== []): ?>
    <div class="alert alert-danger" style="font-size: 13px;">
        <strong>Plans illisibles, ignorés :</strong> <?= e(implode(', ', $preview['unreadable'])) ?>.
        Leurs rendements ne seront pas versés — corrigez le fichier, puis relancez.
    </div>
<?php endif; ?>

<?php if ($preview['unknown'] !== []): ?>
    <div class="alert alert-warning" style="font-size: 13px;">
        <strong>Types nommés par un plan et absents du catalogue :</strong> <?= e(implode(', ', $preview['unknown'])) ?>.
        Ces entrées ne rapportent rien en jeu — le plus souvent une coquille dans le JSON.
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <h5 class="card-title">Ce que les plans déclarent</h5>

        <?php if ($byPlan === []): ?>
            <div class="alert alert-warning mb-0">
                Aucun rendement trouvé dans les <code style="display:inline">biomes[]</code> des JSON de plan de cet
                environnement — rien à verser.
            </div>
        <?php else: ?>
            <p class="text-muted" style="font-size: 13px;">
                <?= count($preview['rows']) ?> rendement(s), <?= count($byPlan) ?> plan(s).
            </p>

            <?= renderTable(
                ['Plan', 'Type', 'Donne', 'Épuisement', 'Repousse'],
                array_map(
                    static fn(array $row): string => '<tr>'
                        . '<td>' . e($row['plan']) . '</td>'
                        . '<td>' . e((string) $row['race_id']) . '</td>'
                        . '<td>' . e($row['item']) . '</td>'
                        . '<td>' . ($row['exhaust'] === null ? '—' : e((string) $row['exhaust'])) . '</td>'
                        . '<td>' . ($row['regrow'] === null ? '—' : e((string) $row['regrow'])) . '</td>'
                        . '</tr>',
                    array_slice($preview['rows'], 0, 200)
                )
            ) ?>

            <?php if (count($preview['rows']) > 200): ?>
                <p class="text-muted" style="font-size: 12px;">
                    Les 200 premiers seulement sont affichés ; le seed en verse <?= count($preview['rows']) ?>.
                </p>
            <?php endif; ?>

            <form method="post" class="mt-2">
                <input type="hidden" name="csrf_token" value="<?= e($csrf->generateToken()) ?>" />
                <button type="submit" name="seed_harvest" value="1" class="btn btn-primary">
                    Verser dans race_harvest
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php

echo admin_layout('Rendements', ob_get_clean());
