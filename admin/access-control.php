<?php
/**
 * Access control — set the required level (admin / superadmin) per admin menu.
 *
 * Superadmin-only: this page is not in the AdminMenuAccessService registry, so
 * it defaults to superadmin and can never be lowered to admin — a superadmin can
 * always reach it to undo a lockout. Enforcement happens in layout.php.
 *
 * Renders one bulk form (a level select per menu) posting to
 * access-control-save.php (CSRF-validated).
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\AdminMenuAccessService;
use App\Service\CsrfProtectionService;

$csrfToken = (new CsrfProtectionService())->generateToken();
$menus = (new AdminMenuAccessService())->getConfigurableMenus();

$levelOptions = [
    AdminMenuAccessService::LEVEL_ADMIN      => 'Admin',
    AdminMenuAccessService::LEVEL_SUPERADMIN => 'Super-admin',
];

// Group rows by their display group, preserving registry order.
$rowsByGroup = [];
foreach ($menus as $menu) {
    $rowsByGroup[$menu['group']][] = $menu;
}

$sections = '';
foreach ($rowsByGroup as $group => $rows) {
    $trs = '';
    foreach ($rows as $menu) {
        $isOverride = $menu['level'] !== $menu['default'];
        $note = $isOverride
            ? '<span class="text-muted">(défaut : ' . e($levelOptions[$menu['default']]) . ')</span>'
            : '<span class="text-muted">défaut</span>';

        $select = formSelect('level[' . $menu['page'] . ']', $levelOptions, $menu['level'], null,
            'class="form-control form-control-sm" style="max-width:12rem"');

        $trs .= '<tr>'
            . '<td>' . e($menu['label']) . '</td>'
            . '<td>' . $select . '</td>'
            . '<td>' . $note . '</td>'
            . '</tr>';
    }

    $sections .= '<div class="card mb-3">'
        . '<div class="card-header"><h3 class="card-title">' . e($group) . '</h3></div>'
        . '<div class="card-body" style="padding:0">'
        . '<table class="table table-striped mb-0"><thead><tr>'
        . '<th>Menu</th><th>Niveau requis</th><th></th></tr></thead><tbody>'
        . $trs . '</tbody></table></div></div>';
}

$content = <<<HTML
<h2 class="section-title">🔐 Contrôle d'accès des menus</h2>
<p class="text-content">Définissez le niveau requis pour chaque menu du dashboard. Les menus laissés en
« Super-admin » ne sont visibles et accessibles qu'aux super-admins. Cette page reste toujours réservée
aux super-admins.</p>
<form method="post" action="/admin/access-control-save.php">
    <input type="hidden" name="csrf_token" value="{$csrfToken}">
    {$sections}
    <button type="submit" class="btn btn-primary">Enregistrer</button>
</form>
HTML;

echo admin_layout("Contrôle d'accès", renderFlashMessage() . $content);
