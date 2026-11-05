<?php
/**
 * Modules: turn each game feature on or off on this server, and pick the
 * testers who get it while it is off.
 */
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Module\Module;
use App\Module\Modules;
use App\Service\AuditService;
use App\Service\CsrfProtectionService;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handleAdminPost('/admin/modules.php', function (): string {
        $module = Module::tryFrom((string) ($_POST['module'] ?? ''))
            ?? throw new InvalidArgumentException('Module inconnu.');
        $enabled = isset($_POST['enabled']);
        $players = Modules::parsePlayers((string) ($_POST['players'] ?? ''));

        Modules::save($module, $enabled, $players);
        (new AuditService())->addAuditLog(sprintf(
            'module %s %s, testeurs : %s',
            $module->value,
            $enabled ? 'activé' : 'désactivé',
            $players === [] ? 'aucun' : implode(', ', $players)
        ));

        return $module->label() . ' : ' . ($enabled ? 'activé pour tous.' : 'désactivé.');
    });
}

$csrf = new CsrfProtectionService();

$content = '';
foreach (Module::cases() as $module) {
    $players = Modules::players($module);
    $enabled = Modules::on($module);
    $body = '<p class="text-muted">' . e($module->description()) . '</p>'
        . '<form method="post">' . $csrf->renderTokenField()
        . '<input type="hidden" name="module" value="' . e($module->value) . '">'
        . '<div class="form-group">' . formCheckbox('enabled', $enabled, '<strong>Activé pour tous</strong>') . '</div>'
        . formField(
            'Testeurs',
            formInput('players', implode(', ', $players), 'placeholder="12, 40"'),
            'form-group',
            'Matricules séparés par des virgules. Les testeurs ont le module même quand il est désactivé.'
        )
        . '<button type="submit" class="btn btn-primary">Enregistrer</button>'
        . '</form>';

    $badge = $enabled
        ? ' <span class="badge badge-success">activé</span>'
        : ' <span class="badge badge-secondary">désactivé</span>';
    $content .= '<div class="card mb-3"><div class="card-header">' . e($module->label()) . $badge . '</div>'
        . '<div class="card-body">' . $body . '</div></div>';
}

echo admin_layout('Modules', $content, [
    'intro' => 'Un module est une fonctionnalité complète du jeu. Le désactiver ne supprime aucune donnée :'
        . ' le jeu arrête juste de s\'en servir, et tout reprend à la réactivation.',
]);
