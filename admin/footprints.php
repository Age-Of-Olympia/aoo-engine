<?php
/**
 * Découpes des décors multi-cases (admin → Cartes · Découpes).
 *
 * Un fort, une pyramide, un géant occupent plusieurs cases, et rien dans les
 * données ne le disait. On sait deviner la forme de deux manières — la carte
 * et les images d'ensemble —, mais **elles se contredisent** : celle de
 * `geant_petrifie` annonce 1×2 cases quand quatre morceaux existent et que la
 * carte en montre une figure de 3×3 trouée.
 *
 * Cette page est l'endroit où un humain tranche, une fois. Une découpe
 * déclarée l'emporte sur ce que la carte et les images racontent : c'est ce
 * qui permet de corriger un décor mal posé au lieu de le subir.
 *
 * Chaque famille affiche sa source — déclarée, carte, image, ou aucune —, sa
 * figure dessinée case par case, et de quoi la reprendre.
 *
 * Les mutations POSTent vers footprints-save.php (CSRF, PRG). Cette page ne
 * fait que rendre.
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\CsrfProtectionService;
use App\Service\Map\EntityTypeFootprintService;

/** D'où vient la découpe, dit d'un coup d'œil. */
function footprint_source_badge(string $source): string
{
    return match ($source) {
        'declared' => '<span class="badge badge-success" title="Déclarée ici : elle fait autorité">Déclarée</span>',
        'map'      => '<span class="badge badge-secondary" title="Déduite d\'un exemplaire complet posé sur la carte">Carte</span>',
        'image'    => '<span class="badge badge-secondary" title="Déduite de l\'image d\'ensemble, divisée par 50">Image</span>',
        default    => '<span class="badge badge-warning" title="Aucune source fiable : la pose reste morceau par morceau">Inconnue</span>',
    };
}

/**
 * La figure, dessinée. Une grille w×h où les cases occupées portent leur
 * numéro de morceau — c'est ainsi qu'on VOIT un trou.
 *
 * @param array<int, array{0:int,1:int}> $offsets
 * @param array<int, string> $roles
 */
function footprint_grid(int $w, int $h, array $offsets, array $roles = []): string
{
    $xs = array_column($offsets, 0);
    $ys = array_column($offsets, 1);

    if ($xs === []) {
        return '<em>vide</em>';
    }

    $minX = min($xs);
    $maxY = max($ys);

    $byCell = [];

    foreach ($offsets as $piece => [$dx, $dy]) {
        $byCell[($maxY - $dy) . ',' . ($dx - $minX)] = $piece;
    }

    $html = '<table class="footprint-grid" style="border-collapse:collapse;display:inline-block;">';

    for ($row = 0; $row < $h; $row++) {
        $html .= '<tr>';

        for ($col = 0; $col < $w; $col++) {
            $piece = $byCell[$row . ',' . $col] ?? null;

            $html .= $piece === null
                ? '<td style="width:22px;height:22px;border:1px dashed #ccc;"></td>'
                : '<td style="width:22px;height:22px;border:1px solid #666;background:#e7ded0;'
                    . 'text-align:center;font-size:11px;" title="morceau ' . $piece
                    . (isset($roles[$piece]) ? ' — ' . e($roles[$piece]) : '') . '">'
                    . $piece . '</td>';
        }

        $html .= '</tr>';
    }

    return $html . '</table>';
}

$csrfToken = (new CsrfProtectionService())->generateToken();

$service = new EntityTypeFootprintService();

$catalogue = $service->catalogue();

ob_start();
?>

<div class="container">
    <h2 class="section-title">Découpes des décors</h2>

    <p class="text-content">
        Un décor multi-cases est posé en morceaux. Sa <em>découpe</em> dit lesquels,
        et où. On sait la deviner depuis la carte ou depuis l'image d'ensemble, mais
        les deux se contredisent parfois — une découpe déclarée ici l'emporte sur
        les deux.
    </p>

    <?= renderFlashMessage() ?>

    <table class="table">
        <thead>
            <tr>
                <th>Type</th>
                <th>Source</th>
                <th>Figure</th>
                <th>Taille</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($catalogue as $name => $footprint): ?>
            <?php $source = $service->sourceOf((string) $name); ?>
            <tr>
                <td><code style="display:inline"><?= e((string) $name) ?></code></td>
                <td><?= footprint_source_badge($source) ?></td>
                <td><?= footprint_grid(
                        (int) $footprint['w'],
                        (int) $footprint['h'],
                        $footprint['offsets'],
                        $footprint['roles']
                    ) ?></td>
                <td>
                    <?= (int) $footprint['w'] ?>×<?= (int) $footprint['h'] ?>,
                    <?= (int) $footprint['cells'] ?> case(s)
                    <?= $footprint['holed'] ? '<br /><small>figure trouée</small>' : '' ?>
                </td>
                <td>
                    <?php if ($source === 'declared'): ?>
                        <form method="post" action="footprints-save.php" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>" />
                            <input type="hidden" name="action" value="forget" />
                            <input type="hidden" name="type" value="<?= e((string) $name) ?>" />
                            <button type="submit" class="btn btn-sm btn-secondary"
                                    title="Le type retombera sur ce que la carte ou l'image disent">Oublier</button>
                        </form>
                    <?php else: ?>
                        <form method="post" action="footprints-save.php" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>" />
                            <input type="hidden" name="action" value="adopt" />
                            <input type="hidden" name="type" value="<?= e((string) $name) ?>" />
                            <button type="submit" class="btn btn-sm btn-primary"
                                    title="Fige la découpe actuellement devinée : elle cessera de dépendre de la carte">Déclarer</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="card mt-3" style="max-width: 760px;">
        <div class="card-header"><strong>Déclarer une découpe à la main</strong></div>
        <div class="card-body">
            <p class="text-muted" style="font-size: 13px;">
                Pour les figures qu'aucune source ne décrit correctement — le géant
                pétrifié, dont l'image d'ensemble ne montre que le corps. Les décalages
                s'écrivent en JSON, par morceau, relativement au premier :
                <code style="display:inline">{"0":[0,0],"1":[0,-1],"2":[-1,-2],"3":[-2,-2]}</code>
            </p>

            <form method="post" action="footprints-save.php">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>" />
                <input type="hidden" name="action" value="declare" />

                <label class="form-label mb-0">Famille</label>
                <input type="text" name="type" list="footprint-families" required
                       class="form-select" style="max-width: 340px;"
                       placeholder="geant_petrifie" />
                <datalist id="footprint-families">
                    <?php foreach (array_keys($catalogue) as $family): ?>
                        <option value="<?= e((string) $family) ?>"></option>
                    <?php endforeach; ?>
                </datalist>

                <label class="form-label mb-0 mt-2">Largeur / hauteur</label>
                <input type="number" name="w" min="1" max="32" value="1" class="form-select" style="max-width: 90px;" />
                <input type="number" name="h" min="1" max="32" value="1" class="form-select" style="max-width: 90px;" />

                <label class="form-label mb-0 mt-2">Décalages (JSON)</label>
                <input type="text" name="offsets" class="form-select" placeholder='{"0":[0,0],"1":[0,-1]}' />

                <label class="form-label mb-0 mt-2">Rôles par morceau (JSON, facultatif)</label>
                <input type="text" name="roles" class="form-select" placeholder='{"0":"block","1":"cover"}' />

                <div class="mt-2">
                    <button type="submit" class="btn btn-sm btn-primary">Déclarer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
echo admin_layout('Découpes des décors', $content);
