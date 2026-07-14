<?php
/**
 * Contenu éditorial de la page d'accueil (admin dashboard → Page d'accueil).
 *
 * Trois blocs, rendus sur une seule page :
 *   - Sections de texte (landing_sections) : présentation du jeu… — le corps
 *     est du HTML confiance-admin, affiché dans la marge gauche de l'accueil ;
 *   - Chroniques (landing_news) : entrées datées, l'accueil affiche les
 *     3 plus récentes dans la marge droite ;
 *   - Galerie (landing_images) : les « planches gravées » sous la
 *     présentation, ouvertes en carrousel plein écran.
 *
 * L'accueil compose tout sur le premier écran : cette page rappelle les
 * budgets de longueur. Une ligne inactive disparaît de l'accueil sans être
 * perdue. Toutes les mutations POSTent vers landing-save.php (CSRF, PRG).
 * Cette page ne fait que rendre. Accès via layout.php (AdminMenuAccessService).
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\CsrfProtectionService;
use App\Service\DateFormatService;
use App\Service\LandingContentService;

$csrf = new CsrfProtectionService();
$service = new LandingContentService();
$dateFormat = new DateFormatService();

$sections = $service->listSections();
$news = $service->listNews();
$images = $service->listImages();

ob_start();
?>

<div class="container">
    <h3>Page d'accueil — sections éditoriales</h3>

    <?= renderFlashMessage() ?>

    <div class="alert alert-info" style="font-size: 13px; line-height: 1.5;">
        L'accueil compose tout sur le <strong>premier écran</strong> (sans défiler, sur un écran de bureau) :
        les sections de texte et la galerie dans la marge gauche, les 3 dernières chroniques dans la marge droite.
        <strong>Budgets conseillés</strong> : présentation ≈ 4-6 lignes ; chroniques de 1-2 lignes ;
        4 planches actives dans la galerie. Une ligne <em>inactive</em> disparaît de l'accueil sans être perdue.
        Base vide (nouveau déploiement) ? <a href="landing-seed.php">Semer le contenu initial</a>.
    </div>

    <!-- ============ Sections de texte ============ -->
    <div class="card mt-3">
        <div class="card-header"><strong>Sections de texte</strong> — marge gauche de l'accueil</div>
        <div class="card-body">
            <?php foreach ($sections as $section): ?>
            <form method="post" action="landing-save.php?action=section-save" class="border-bottom pb-3 mb-3">
                <?= $csrf->renderTokenField() ?>
                <input type="hidden" name="slug" value="<?= e($section->slug) ?>" />
                <div class="d-flex gap-3 flex-wrap align-items-end mb-2">
                    <div>
                        <label class="form-label mb-0">Code</label>
                        <div><code><?= e($section->slug) ?></code></div>
                    </div>
                    <div>
                        <label class="form-label mb-0">Titre</label>
                        <input type="text" name="title" class="form-control" value="<?= e($section->title) ?>" required />
                    </div>
                    <div>
                        <label class="form-label mb-0">Ordre</label>
                        <input type="number" name="position" class="form-control" style="width: 90px;" value="<?= (int) $section->position ?>" />
                    </div>
                    <label class="form-check-label mb-2">
                        <input type="checkbox" name="is_active" <?= checked((bool) $section->is_active) ?> /> visible
                    </label>
                </div>
                <label class="form-label mb-0">Corps (HTML : <code style="display:inline">&lt;p&gt;…&lt;/p&gt;</code>)</label>
                <textarea name="body" class="form-control" rows="4" style="font-family: monospace; font-size: 12px;"><?= e($section->body) ?></textarea>
                <div class="mt-2 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary">Enregistrer</button>
                    <button type="submit" class="btn btn-sm btn-outline-danger"
                        formaction="landing-save.php?action=section-delete"
                        onclick="return confirm('Supprimer la section « <?= e($section->slug) ?> » ?');">Supprimer</button>
                </div>
            </form>
            <?php endforeach; ?>

            <details<?= $sections === [] ? ' open' : '' ?>>
                <summary class="mb-2" style="cursor: pointer;">Nouvelle section</summary>
                <form method="post" action="landing-save.php?action=section-save">
                    <?= $csrf->renderTokenField() ?>
                    <div class="d-flex gap-3 flex-wrap align-items-end mb-2">
                        <div>
                            <label class="form-label mb-0">Code (minuscules, sans espace)</label>
                            <input type="text" name="slug" class="form-control" pattern="[a-z0-9_-]{1,50}" required />
                        </div>
                        <div>
                            <label class="form-label mb-0">Titre</label>
                            <input type="text" name="title" class="form-control" required />
                        </div>
                        <div>
                            <label class="form-label mb-0">Ordre</label>
                            <input type="number" name="position" class="form-control" style="width: 90px;" value="0" />
                        </div>
                        <label class="form-check-label mb-2">
                            <input type="checkbox" name="is_active" checked /> visible
                        </label>
                    </div>
                    <label class="form-label mb-0">Corps (HTML)</label>
                    <textarea name="body" class="form-control" rows="3" style="font-family: monospace; font-size: 12px;"></textarea>
                    <button type="submit" class="btn btn-sm btn-primary mt-2">Créer</button>
                </form>
            </details>
        </div>
    </div>

    <!-- ============ Chroniques ============ -->
    <div class="card mt-3">
        <div class="card-header"><strong>Dernières chroniques</strong> — marge droite, les 3 plus récentes</div>
        <div class="card-body">
            <?php foreach ($news as $entry): ?>
            <form method="post" action="landing-save.php?action=news-save" class="border-bottom pb-3 mb-3">
                <?= $csrf->renderTokenField() ?>
                <input type="hidden" name="id" value="<?= (int) $entry->id ?>" />
                <div class="d-flex gap-3 flex-wrap align-items-end mb-2">
                    <div>
                        <label class="form-label mb-0">Date (JJ/MM/AAAA)
                            <span class="text-muted" style="font-weight: normal;">
                                — affichée « <?= e($dateFormat->format($entry->news_date)) ?> »</span>
                        </label>
                        <input type="text" name="news_date" class="form-control" style="width: 130px;"
                               value="<?= e(date('d/m/Y', strtotime($entry->news_date))) ?>"
                               pattern="\d{1,2}/\d{1,2}/\d{4}" placeholder="JJ/MM/AAAA" required />
                    </div>
                    <div style="flex: 1; min-width: 220px;">
                        <label class="form-label mb-0">Titre</label>
                        <input type="text" name="title" class="form-control" value="<?= e($entry->title) ?>" required />
                    </div>
                    <label class="form-check-label mb-2">
                        <input type="checkbox" name="is_active" <?= checked((bool) $entry->is_active) ?> /> visible
                    </label>
                </div>
                <label class="form-label mb-0">Texte (1-2 lignes)</label>
                <textarea name="text" class="form-control" rows="2"><?= e($entry->text) ?></textarea>
                <div class="mt-2 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary">Enregistrer</button>
                    <button type="submit" class="btn btn-sm btn-outline-danger"
                        formaction="landing-save.php?action=news-delete"
                        onclick="return confirm('Supprimer cette chronique ?');">Supprimer</button>
                </div>
            </form>
            <?php endforeach; ?>

            <details<?= $news === [] ? ' open' : '' ?>>
                <summary class="mb-2" style="cursor: pointer;">Nouvelle chronique</summary>
                <form method="post" action="landing-save.php?action=news-save">
                    <?= $csrf->renderTokenField() ?>
                    <div class="d-flex gap-3 flex-wrap align-items-end mb-2">
                        <div>
                            <label class="form-label mb-0">Date (JJ/MM/AAAA)</label>
                            <input type="text" name="news_date" class="form-control" style="width: 130px;"
                                   value="<?= e(date('d/m/Y')) ?>"
                                   pattern="\d{1,2}/\d{1,2}/\d{4}" placeholder="JJ/MM/AAAA" required />
                        </div>
                        <div style="flex: 1; min-width: 220px;">
                            <label class="form-label mb-0">Titre</label>
                            <input type="text" name="title" class="form-control" required />
                        </div>
                        <label class="form-check-label mb-2">
                            <input type="checkbox" name="is_active" checked /> visible
                        </label>
                    </div>
                    <label class="form-label mb-0">Texte</label>
                    <textarea name="text" class="form-control" rows="2"></textarea>
                    <button type="submit" class="btn btn-sm btn-primary mt-2">Créer</button>
                </form>
            </details>
        </div>
    </div>

    <!-- ============ Galerie ============ -->
    <div class="card mt-3">
        <div class="card-header"><strong>Galerie d'aperçus</strong> — « planches gravées » sous la présentation,
            numérotées Pl. I, II… dans l'ordre ; carrousel plein écran au clic</div>
        <div class="card-body">
            <div class="d-flex flex-wrap gap-3">
                <?php foreach ($images as $image): ?>
                <form method="post" action="landing-save.php?action=image-update"
                      class="border rounded p-2" style="width: 250px;">
                    <?= $csrf->renderTokenField() ?>
                    <input type="hidden" name="id" value="<?= (int) $image->id ?>" />
                    <img src="/<?= e($image->plate_path !== '' ? $image->plate_path : $image->path) ?>" alt=""
                         style="width: 100%; aspect-ratio: 2.1 / 1; object-fit: cover;"
                         class="rounded mb-2<?= $image->is_active ? '' : ' opacity-50' ?>" />
                    <input type="text" name="caption" class="form-control form-control-sm mb-1"
                           value="<?= e($image->caption) ?>" placeholder="Légende" />
                    <div class="d-flex gap-2 align-items-center mb-2">
                        <input type="number" name="position" class="form-control form-control-sm" style="width: 70px;"
                               value="<?= (int) $image->position ?>" title="Ordre" />
                        <label class="form-check-label" style="font-size: 13px;">
                            <input type="checkbox" name="is_active" <?= checked((bool) $image->is_active) ?> /> visible
                        </label>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-sm btn-primary">Enregistrer</button>
                        <button type="submit" class="btn btn-sm btn-outline-danger"
                            formaction="landing-save.php?action=image-delete"
                            onclick="return confirm('Supprimer cet aperçu ?');">Supprimer</button>
                    </div>
                </form>
                <?php endforeach; ?>
            </div>

            <details class="mt-3"<?= $images === [] ? ' open' : '' ?>>
                <summary class="mb-2" style="cursor: pointer;">Ajouter un aperçu</summary>
                <form method="post" action="landing-save.php?action=image-add" enctype="multipart/form-data">
                    <?= $csrf->renderTokenField() ?>
                    <div class="d-flex gap-3 flex-wrap align-items-end">
                        <div>
                            <label class="form-label mb-0">Image (jpg, png, webp, gif —
                                max <?= e(iniSizeLabel((string) ini_get('upload_max_filesize'))) ?>)</label>
                            <input type="file" name="image_file" class="form-control" accept="image/*" required />
                        </div>
                        <div style="flex: 1; min-width: 220px;">
                            <label class="form-label mb-0">Légende</label>
                            <input type="text" name="caption" class="form-control" placeholder="Les plaines de Gaïa" />
                        </div>
                        <div>
                            <label class="form-label mb-0">Ordre</label>
                            <input type="number" name="position" class="form-control" style="width: 90px;" value="0" />
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary mb-1">Ajouter</button>
                    </div>
                </form>
            </details>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
echo admin_layout('Page d\'accueil', $content);
