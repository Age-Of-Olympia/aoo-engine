<?php
/**
 * Admin Access Dashboard & Player Options Manager
 * -----------------------------------------------
 * Admin-dashboard page (sidebar → Joueurs → « Accès & options »). Two jobs:
 *
 *   1. DASHBOARD  — who holds admin / super-admin rights, and HOW they reach
 *                   them: directly on their character, or through a controlled
 *                   PNJ (players_pnjs). This mirrors the test-env lock-down
 *                   predicate, so the list == the accounts a lock would spare.
 *
 *   2. MANAGER    — look up any character and toggle its options (add if
 *                   missing, remove if present) — the GUI equivalent of the
 *                   `option` console command (console-commands/optioncmd.php).
 *
 * Rendering only. The option toggle POSTs to admin-access-toggle.php, which is
 * CSRF-validated and re-checks authority (super-admin required to touch
 * isSuperAdmin) before redirecting back here (PRG pattern).
 *
 * Access control: layout.php runs AdminAuthorizationService::DoAdminCheck(), so
 * only admins can open this page.
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/admin-access-options.php');

use Classes\Db;
use App\Service\CsrfProtectionService;
use App\Service\PlayerLookupService;
use App\Service\PlayerOptionsService;

$db = new Db();
$csrfToken = (new CsrfProtectionService())->generateToken();

/* -------------------------------------------------------------------------
 * DATA: admin-access dashboard
 *
 * One row per privileged real character (id > 0), showing whether the right is
 * direct and which admin PNJ(s) it controls. Same predicate as the test-env
 * lock-down UPDATE, so this list == the accounts that would keep access.
 * ---------------------------------------------------------------------- */
$dashboardSql = "
SELECT
    p.id,
    p.name,
    MAX(CASE WHEN o.name = 'isAdmin'      THEN 1 ELSE 0 END) AS direct_admin,
    MAX(CASE WHEN o.name = 'isSuperAdmin' THEN 1 ELSE 0 END) AS direct_superadmin,
    GROUP_CONCAT(
        DISTINCT CONCAT(pnj.name, ' (#', pnj.id, ':', o2.name, ')')
        ORDER BY pnj.id SEPARATOR ', '
    ) AS via_pnjs
FROM players p
LEFT JOIN players_options o
       ON o.player_id = p.id
      AND o.name IN ('isAdmin', 'isSuperAdmin')
LEFT JOIN players_pnjs pp
       ON pp.player_id = p.id
LEFT JOIN players_options o2
       ON o2.player_id = pp.pnj_id
      AND o2.name IN ('isAdmin', 'isSuperAdmin')
LEFT JOIN players pnj
       ON pnj.id = pp.pnj_id
      AND o2.name IS NOT NULL
WHERE p.id > 0
GROUP BY p.id, p.name
HAVING direct_admin = 1 OR direct_superadmin = 1 OR via_pnjs IS NOT NULL
ORDER BY p.id
";

$dashboard = [];
// GROUP_CONCAT (via_pnjs) truncates silently at group_concat_max_len (default
// 1024); raise it so a heavily-controlled account's PNJ list is never cut off.
$db->exe('SET SESSION group_concat_max_len = 1000000');
$res = $db->exe($dashboardSql);
while ($row = $res->fetch_object()) {
    $dashboard[] = $row;
}

/* -------------------------------------------------------------------------
 * DATA: options of the currently looked-up player (manager panel)
 * ---------------------------------------------------------------------- */
$lookupTerm     = trim((string) ($_GET['q'] ?? ''));
$lookupPlayer   = null;
$lookupOptions  = [];
$lookupError    = null;
$lookupDisambig = null;

if ($lookupTerm !== '') {
    // Resolve by matricule or exact name. Names are not unique across kinds, so
    // a name that matches several characters is disambiguated (choose by
    // matricule) rather than silently toggling options on the first row.
    $matches = (new PlayerLookupService())->resolve($lookupTerm);

    if (count($matches) === 1) {
        $lookupPlayer  = $matches[0];
        $lookupOptions = (new PlayerOptionsService())->getOptions($lookupPlayer['id']);
    } elseif (count($matches) > 1) {
        $lookupDisambig = $matches;
    } else {
        $lookupError = 'Aucun personnage trouvé pour « ' . e($lookupTerm) . ' ».';
    }
}

/* -------------------------------------------------------------------------
 * RENDER
 * ---------------------------------------------------------------------- */

/** A toggle form-button for (playerId, option). Posts to the save action. */
$renderToggle = static function (int $playerId, string $option, bool $active)
        use ($csrfToken, $lookupTerm): string {
    $label = $active ? '✓ ' . $option : '＋ ' . $option;
    $priv  = in_array($option, ADMIN_ACCESS_PRIVILEGED_OPTIONS, true) ? ' aa-opt--priv' : '';
    $state = $active ? ' aa-opt--on' : ' aa-opt--off';
    return '<form method="post" action="/admin/admin-access-toggle.php" class="aa-opt-form">'
        . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
        . '<input type="hidden" name="player_id" value="' . $playerId . '">'
        . '<input type="hidden" name="option" value="' . e($option) . '">'
        . '<input type="hidden" name="q" value="' . e($lookupTerm) . '">'
        . '<button type="submit" class="aa-opt' . $state . $priv . '">' . e($label) . '</button>'
        . '</form>';
};

/* Dashboard rows */
$dashboardRows = '';
if (!$dashboard) {
    $dashboardRows = '<tr><td colspan="5" class="aa-muted">Aucun compte avec accès admin.</td></tr>';
}
foreach ($dashboard as $d) {
    $directBadges = '';
    if ($d->direct_superadmin) {
        $directBadges .= '<span class="badge aa-badge--sa">SUPER ADMIN</span> ';
    }
    if ($d->direct_admin) {
        $directBadges .= '<span class="badge aa-badge--a">ADMIN</span>';
    }
    if (!$d->direct_admin && !$d->direct_superadmin) {
        $directBadges = '<span class="aa-muted">—</span>';
    }

    $via = $d->via_pnjs !== null ? e($d->via_pnjs) : '<span class="aa-muted">—</span>';

    $dashboardRows .= '<tr>'
        . '<td>' . (int) $d->id . '</td>'
        . '<td>' . e($d->name) . '</td>'
        . '<td>' . $directBadges . '</td>'
        . '<td>' . $via . '</td>'
        . '<td><a class="btn btn-sm btn-outline-primary" href="/admin/admin-access.php?q='
            . (int) $d->id . '#manager">Gérer</a></td>'
        . '</tr>';
}

/* Manager panel */
$managerPanel = '';
if ($lookupError !== null) {
    $managerPanel .= '<div class="alert alert-warning">' . $lookupError . '</div>';
}
if ($lookupDisambig !== null) {
    // Several characters share this name — let the admin pick by matricule.
    $items = '';
    foreach ($lookupDisambig as $m) {
        $items .= '<li><a href="/admin/admin-access.php?q=' . (int) $m['id'] . '#manager">'
            . e($m['name']) . ' <span class="aa-muted">(#' . (int) $m['id'] . ', ' . e($m['player_type']) . ')</span>'
            . '</a></li>';
    }
    $managerPanel .= '<div class="alert alert-warning">Plusieurs personnages portent ce nom — '
        . 'choisissez par matricule :<ul style="margin:.4rem 0 0">' . $items . '</ul></div>';
}
if ($lookupPlayer !== null) {
    $toggles = '';
    foreach (ADMIN_ACCESS_VALID_OPTIONS as $opt) {
        $toggles .= $renderToggle($lookupPlayer['id'], $opt, in_array($opt, $lookupOptions, true));
    }

    // Surface any option the player has that is outside the whitelist so it is
    // at least visible (legacy/unknown), even if not toggleable here.
    $unknown = array_diff($lookupOptions, ADMIN_ACCESS_VALID_OPTIONS);
    $unknownNote = $unknown
        ? '<p class="aa-muted" style="margin-top:10px">Autres options présentes (non gérées ici) : '
            . e(implode(', ', $unknown)) . '</p>'
        : '';

    $managerPanel .= '<div class="card">'
        . '<div class="card-header"><h3 class="card-title">' . e($lookupPlayer['name'])
            . ' <span class="aa-muted">(#' . (int) $lookupPlayer['id'] . ')</span></h3></div>'
        . '<div class="card-body" style="padding:14px">'
        . '<p class="aa-muted">Cliquez pour ajouter / retirer une option. Les options en gras confèrent des droits admin.</p>'
        . '<div class="aa-opts">' . $toggles . '</div>'
        . $unknownNote
        . '</div></div>';
}

$dashboardCount    = count($dashboard);
$lookupTermEscaped = e($lookupTerm);

$content = <<<HTML
<h2 class="section-title">🛡️ Accès admin &amp; gestion des options</h2>
<p class="text-content">Vue des accès admin / super-admin (directs ou via PNJ contrôlé) et gestion des options par personnage.</p>

<div class="card" style="margin-bottom:22px">
    <div class="card-header">
        <h3 class="card-title">Accès administrateur ({$dashboardCount})</h3>
    </div>
    <div class="card-body">
        <p class="aa-muted" style="padding:10px 14px 0">Ces comptes conserveraient leur accès lors d'un verrouillage d'environnement de test.</p>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nom</th>
                        <th>Accès direct</th>
                        <th>Accès via PNJ contrôlé(s)</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    {$dashboardRows}
                </tbody>
            </table>
        </div>
    </div>
</div>

<h2 class="section-title" id="manager">Gestion des options d'un personnage</h2>
<form method="get" action="/admin/admin-access.php" class="aa-lookup">
    <input type="text" name="q" value="{$lookupTermEscaped}" placeholder="Matricule ou nom exact…">
    <button type="submit" class="btn btn-primary">Rechercher</button>
</form>
{$managerPanel}
HTML;

echo admin_layout('Accès & options', renderFlashMessage() . $content, [
    'styles' => ['/admin/css/admin-access.css'],
]);
