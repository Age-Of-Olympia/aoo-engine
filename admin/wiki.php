<?php
/**
 * Wiki (admin dashboard) : les fiches « wiki compatibles » du catalogue,
 * générées famille par famille (WikiRendererRegistry) et servies dans
 * un textarea à coller sur le DokuWiki externe — même geste que le wiki
 * des effets, généralisé. La donnée vient des exporters de bundles
 * (même processus que l'export, autre format) : le wiki ne peut pas
 * diverger du jeu.
 */
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/helpers.php';

use App\Service\Wiki\WikiRendererRegistry;

$registry = new WikiRendererRegistry();
$titles = $registry->titles();

$type = (string) ($_GET['type'] ?? array_key_first($titles) ?? '');
$renderer = $registry->rendererFor($type);

$tabs = '';
foreach ($titles as $key => $title) {
    $active = $key === $type ? ' btn-primary' : ' btn-outline-secondary';
    $tabs .= '<a class="btn btn-sm' . $active . '" href="/admin/wiki.php?type=' . e($key) . '">'
        . e($title) . '</a>';
}

$body = '';

if ($renderer === null) {
    $body .= '<div class="alert alert-warning">Famille inconnue.</div>';
} else {
    $body .= '<textarea class="form-control font-monospace" rows="30" spellcheck="false" onclick="this.select()" readonly>'
        . e($renderer->render())
        . '</textarea>';
}

echo admin_layout('Wiki', $body, [
    'actions' => '<span class="btn-group">' . $tabs . '</span>',
    'intro' => 'Markup DokuWiki généré depuis le catalogue : cliquez dans la zone pour tout'
        . ' sélectionner, puis collez-le sur la page du wiki externe. Les coûts et les cibles sont calculés'
        . ' à partir des conditions réelles des actions.',
]);
