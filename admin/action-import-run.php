<?php
/**
 * The loading screen of a plan bundle: a progress bar calling
 * action-import-step.php until it is done.
 *
 * A whole plan does not fit in one request, so loading advances step by step,
 * each step committed. Closing the tab interrupts the load without losing
 * anything — coming back to this screen resumes where it stopped.
 */
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\CsrfProtectionService;

$json = $_SESSION['action_import_bundle'] ?? null;
if (!is_string($json) || $json === '') {
    setFlash('warning', 'Aucun bundle à charger.');
    redirectTo('/admin/action-import.php');
}

$csrf = new CsrfProtectionService();
$filename = (string) ($_SESSION['action_import_filename'] ?? 'bundle.json');

ob_start();
?>
<h1>Import en cours</h1>
<p class="text-muted"><?= e($filename) ?></p>

<div class="card mb-3"><div class="card-body">
    <div class="progress" style="height: 22px;">
        <div id="import-bar" class="progress-bar" role="progressbar" style="width: 0%;">0 %</div>
    </div>
    <p id="import-label" class="mt-2 mb-0">Démarrage…</p>
    <ul id="import-warnings" class="mt-3 text-muted" style="font-size: 13px;"></ul>
</div></div>

<p><a class="btn btn-secondary" href="/admin/action-import.php">Revenir à l'import</a></p>

<script>
(function () {
    var bar = document.getElementById('import-bar');
    var label = document.getElementById('import-label');
    var warnings = document.getElementById('import-warnings');
    var token = <?= json_encode($csrf->generateToken()) ?>;

    function show(text) { label.textContent = text; }

    function step() {
        var body = new FormData();
        body.append('csrf_token', token);

        fetch('/admin/action-import-step.php', { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data.error) {
                    show('Échec : ' + data.error);
                    bar.classList.add('bg-danger');
                    return;
                }

                (data.warnings || []).forEach(function (message) {
                    var item = document.createElement('li');
                    item.textContent = message;
                    warnings.appendChild(item);
                });

                var percent = data.total > 0 ? Math.round(data.step * 100 / data.total) : 100;
                bar.style.width = percent + '%';
                bar.textContent = percent + ' %';

                if (data.done) {
                    show('Import terminé.');
                    bar.classList.add('bg-success');
                    return;
                }

                show(data.plan + ' — ' + data.label + ' (' + data.step + '/' + data.total + ')');
                step();
            })
            .catch(function (error) {
                show('Échec : ' + error + ' — rechargez la page pour reprendre.');
                bar.classList.add('bg-danger');
            });
    }

    step();
})();
</script>
<?php
echo admin_layout('Import en cours', renderFlashMessage() . ob_get_clean());
