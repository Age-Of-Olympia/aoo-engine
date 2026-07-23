<?php
/**
 * Catalogue des types de ressources/murs (admin dashboard → Cartes ·
 * Ressources (types)) : la table resource_types, ex-constante RESOURCES_PV
 * (Version20260723120000_ResourceTypesFromConstants).
 *
 * Une seule vue : la liste entière, éditable en ligne (le catalogue tient
 * en deux colonnes — nom et pv). Sémantique du pv : négatif = ressource
 * (-1 récoltable / -2 épuisé, valeur d'instance dans map_resources.damages),
 * positif = PV des survivants destructibles (autels via destroy.php),
 * absent du catalogue = obstacle indestructible.
 *
 * Toutes les mutations POSTent vers resource-types-save.php (CSRF, PRG).
 * Cette page ne fait que rendre. Accès via layout.php (AdminMenuAccessService).
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\CsrfProtectionService;
use App\Service\ResourceTypeService;

/** Badge de la sémantique du pv, pour lire la liste d'un coup d'œil. */
function resource_type_badge(int $pv): string
{
    if ($pv === -1) {
        return '<span class="badge badge-success" title="Récoltable via la fouille (biomes des plans)">Ressource récoltable</span>';
    }
    if ($pv < 0) {
        return '<span class="badge badge-secondary" title="Ressource épuisée par défaut (pv=' . $pv . ')">Ressource épuisée</span>';
    }

    return '<span class="badge badge-warning" title="Destructible à l\'arme de mêlée (destroy.php)">Destructible (' . $pv . ' PV)</span>';
}

$csrfToken = (new CsrfProtectionService())->generateToken();

$catalog = ResourceTypeService::all();
ksort($catalog);

$rows = [];
foreach ($catalog as $name => $pv) {
    $placed = ResourceTypeService::countPlacedByName((string) $name);

    $editForm = '<form method="post" action="/admin/resource-types-save.php?action=save" class="form-inline">'
        . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
        . '<input type="hidden" name="name" value="' . e((string) $name) . '">'
        . '<input type="number" class="form-control form-control-sm mr-2" style="width:6.5em" name="pv" value="' . (int) $pv . '">'
        . '<button type="submit" class="btn btn-sm btn-outline-primary">Enregistrer</button>'
        . '</form>';

    $deleteCell = $placed > 0
        ? '<span class="text-muted" title="Suppression impossible tant que des instances sont posées">—</span>'
        : '<form method="post" action="/admin/resource-types-save.php?action=delete"'
            . ' onsubmit="return confirm(\'Supprimer le type « ' . e((string) $name) . ' » ?\');">'
            . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
            . '<input type="hidden" name="name" value="' . e((string) $name) . '">'
            . '<button type="submit" class="btn btn-sm btn-outline-danger">Supprimer</button>'
            . '</form>';

    $rows[] = '<tr>'
        . '<td><code>' . e((string) $name) . '</code></td>'
        . '<td>' . resource_type_badge((int) $pv) . '</td>'
        . '<td>' . $editForm . '</td>'
        . '<td>' . ($placed > 0 ? '<strong>' . $placed . '</strong>' : '<span class="text-muted">—</span>') . '</td>'
        . '<td>' . $deleteCell . '</td>'
        . '</tr>';
}

$addForm = '<form method="post" action="/admin/resource-types-save.php?action=save" class="form-inline">'
    . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
    . '<input type="text" class="form-control form-control-sm mr-2" name="name" required'
    . ' pattern="[a-zA-Z0-9_.-]+" placeholder="nom (ex: arbre7)" title="Même règle que les noms de tuiles/assets">'
    . '<input type="number" class="form-control form-control-sm mr-2" style="width:6.5em" name="pv" value="-1" required>'
    . '<button type="submit" class="btn btn-primary btn-sm">+ Ajouter</button>'
    . '</form>';

$content = '<div class="d-flex justify-content-between align-items-center mb-3">'
    . '<h1 class="mb-0">Types de ressources</h1>' . $addForm . '</div>'
    . '<p class="text-muted">pv <code>-1</code> = ressource récoltable (fouille, biomes des plans), '
    . '<code>-2</code> = ressource épuisée par défaut, positif = PV d\'un destructible (autels) ; '
    . 'un mur absent du catalogue est un obstacle indestructible. '
    . 'Le nom doit correspondre à une image de <code>img/walls/</code> (admin → Cartes · Tuiles &amp; images).</p>'
    . renderTable(
        ['Nom', 'Statut', 'PV', ['Posés', 'title="Instances map_resources portant ce nom"'], ''],
        $rows,
        'class="table table-striped table-sm" data-admin-list data-page-size="30"'
    );

echo admin_layout('Types de ressources', renderFlashMessage() . $content);
