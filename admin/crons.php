<?php
/**
 * Manual cron launcher (admin dashboard → Outils → Crons).
 *
 * Lists the cron groups under scripts/crons/ (hourly, daily) with their
 * scripts in execution order, and lets an admin replay a whole group or a
 * single script by hand — what the console `cron` command does, from the
 * dashboard. Runs go through the same code paths as the scheduled ones
 * (CronService for a group, an include with $db in scope for one file), so
 * a manual run behaves exactly like the real thing. Output is captured,
 * stored in session and shown once after the PRG redirect; every run also
 * leaves an audit line, and the page shows the latest cron audit entries.
 *
 * Standalone CLI scripts (cron.php entry point, onesignal_sync.php) are
 * deliberately not runnable here: they define their own bootstrap and argv
 * handling, and belong to the server's crontab.
 */
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\AuditService;
use App\Service\CronService;
use App\Service\CsrfProtectionService;
use Classes\Db;

const CRONS_DIR = __DIR__ . '/../scripts/crons';

/**
 * Cron groups (subdirectories of scripts/crons) and their scripts, sorted —
 * the numeric prefixes make the sort the execution order, exactly what
 * File::scan_dir gives CronService.
 *
 * @return array<string, string[]> group => script filenames
 */
function crons_catalog(): array
{
    $groups = [];
    foreach (scandir(CRONS_DIR) ?: [] as $entry) {
        if ($entry[0] === '.' || !is_dir(CRONS_DIR . '/' . $entry)) {
            continue;
        }
        $scripts = array_values(array_filter(
            scandir(CRONS_DIR . '/' . $entry) ?: [],
            static fn (string $file): bool => str_ends_with($file, '.php')
        ));
        sort($scripts);
        $groups[$entry] = $scripts;
    }
    ksort($groups);

    return $groups;
}

$csrf = new CsrfProtectionService();
$catalog = crons_catalog();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['run_group']) || isset($_POST['run_script']))) {
    try {
        $csrf->validateTokenOrFail($_POST['csrf_token'] ?? null);

        // Names are validated against the directory listing, never trusted
        // as paths: no way to include anything outside scripts/crons/.
        $group = (string) ($_POST['group'] ?? '');
        if (!isset($catalog[$group])) {
            throw new RuntimeException("Groupe de crons inconnu : « {$group} ».");
        }

        $started = microtime(true);
        ob_start();

        if (isset($_POST['run_group'])) {
            $label = 'cron ' . $group . ' (groupe complet)';
            (new CronService())->executeCron($group);
        } else {
            $script = (string) ($_POST['script'] ?? '');
            if (!in_array($script, $catalog[$group], true)) {
                throw new RuntimeException("Script inconnu dans « {$group} » : « {$script} ».");
            }
            $label = $group . '/' . $script;
            // Same contract as CronService / the console command: the
            // script runs with $db in scope.
            $db = new Db();
            include CRONS_DIR . '/' . $group . '/' . $script;
            (new AuditService())->addAuditLog('cron manuel ' . $label . ' joué depuis l\'admin');
        }

        $_SESSION['cron_run_report'] = [
            'label'    => $label,
            'output'   => ob_get_clean(),
            'duration' => round(microtime(true) - $started, 2),
            'when'     => date('d/m/Y H:i:s'),
        ];
        setFlash('success', "« {$label} » exécuté.");
    } catch (Throwable $e) {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        setFlash('danger', $e->getMessage());
    }
    redirectTo('/admin/crons.php'); // PRG
}

// ----- Affichage -----

$content = '<div class="d-flex justify-content-between align-items-center mb-3">'
    . '<h1 class="mb-0">Crons</h1></div>'
    . '<p class="text-muted">Rejoue à la main ce que la planification exécute : un groupe entier dans'
    . ' l\'ordre, ou un script seul. Mêmes chemins de code que les exécutions planifiées — un cron'
    . ' joué ici fait exactement ce qu\'il fait la nuit.</p>';

// Report of the run just done (stored by the POST, shown once).
if (!empty($_SESSION['cron_run_report'])) {
    $report = $_SESSION['cron_run_report'];
    unset($_SESSION['cron_run_report']);

    $output = trim((string) $report['output']);
    $content .= formCard(
        'Dernière exécution — ' . e($report['label']),
        '<p class="mb-2 text-muted">' . e($report['when']) . ' · ' . e((string) $report['duration']) . ' s</p>'
        . ($output === ''
            ? '<p class="text-muted mb-0">Aucune sortie.</p>'
            : '<pre class="mb-0" style="max-height:20rem;overflow:auto">' . e($output) . '</pre>')
    );
}

foreach ($catalog as $group => $scripts) {
    $rows = [];
    foreach ($scripts as $script) {
        $rows[] = '<tr>'
            . '<td><code>' . e($script) . '</code></td>'
            . '<td class="text-right"><form method="post" action="/admin/crons.php" class="d-inline"'
            . ' onsubmit="return confirm(\'Jouer « ' . e($group . '/' . $script) . ' » maintenant ?\');">'
            . $csrf->renderTokenField()
            . '<input type="hidden" name="group" value="' . e($group) . '">'
            . '<input type="hidden" name="script" value="' . e($script) . '">'
            . '<button type="submit" name="run_script" value="1" class="btn btn-sm btn-outline-primary">Jouer</button>'
            . '</form></td>'
            . '</tr>';
    }

    $runAll = '<form method="post" action="/admin/crons.php" class="d-inline"'
        . ' onsubmit="return confirm(\'Jouer le groupe « ' . e($group) . ' » complet, dans l\\\'ordre ?\');">'
        . $csrf->renderTokenField()
        . '<input type="hidden" name="group" value="' . e($group) . '">'
        . '<button type="submit" name="run_group" value="1" class="btn btn-sm btn-primary">Tout jouer (ordre)</button>'
        . '</form>';

    $content .= '<div class="card mb-3">'
        . '<div class="card-header d-flex justify-content-between align-items-center">'
        . '<span>' . e(ucfirst($group)) . ' <small class="text-muted">(' . count($scripts) . ' scripts)</small></span>'
        . $runAll . '</div>'
        . '<div class="card-body p-0"><table class="table table-sm table-striped mb-0"><tbody>'
        . implode('', $rows)
        . '</tbody></table></div></div>';
}

// Latest cron audit entries: the scheduled runs (CronService start/done
// lines) and the manual ones logged above, newest first.
$historyRows = [];
try {
    $res = (new Db())->exe(
        "SELECT details, timestamp FROM audit
         WHERE details LIKE 'cron %' OR details LIKE 'Cron %'
         ORDER BY id DESC LIMIT 12"
    );
    while ($row = $res->fetch_object()) {
        $historyRows[] = '<tr><td class="text-nowrap">' . e((string) $row->timestamp) . '</td>'
            . '<td>' . e((string) $row->details) . '</td></tr>';
    }
} catch (Throwable $e) {
    // No audit table (fresh install): the launcher works without history.
}

$content .= formCard('Dernières traces (audit)', $historyRows === []
    ? '<p class="text-muted mb-0">Aucune trace de cron dans l\'audit.</p>'
    : '<table class="table table-sm mb-0"><tbody>' . implode('', $historyRows) . '</tbody></table>');

echo admin_layout('Crons', renderFlashMessage() . $content);
