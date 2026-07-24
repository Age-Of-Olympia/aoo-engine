<?php
/**
 * Détenteurs d'un objet du catalogue (lien depuis la colonne « Joueurs »
 * de admin/items.php) : détail joueur par joueur — quantité en
 * inventaire, emplacement équipé éventuel, quantité en banque. Même
 * périmètre que le compteur agrégé (ItemOwnershipService).
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\ItemOwnershipService;
use Classes\Db;

$id = (int) ($_GET['id'] ?? 0);
$item = null;
if ($id > 0) {
    $res = (new Db())->exe('SELECT id, name FROM items WHERE id = ?', [$id]);
    $item = $res->fetch_object();
}
if (!$item) {
    setFlash('warning', 'Objet introuvable.');
    redirectTo('/admin/items.php');
}

$owners = (new ItemOwnershipService())->itemOwners($id);

$title = 'Joueurs détenant « ' . $item->name . ' »';

$rows = [];
foreach ($owners as $owner) {
    $inv = $owner['inv'] > 0
        ? (string) $owner['inv']
            . ($owner['equiped'] !== '' ? ' <span class="badge badge-primary">équipé : ' . e($owner['equiped']) . '</span>' : '')
        : '<span class="text-muted">—</span>';
    $bank = $owner['bank'] > 0 ? (string) $owner['bank'] : '<span class="text-muted">—</span>';

    $rows[] = '<tr>'
        . '<td>' . $owner['id'] . '</td>'
        . '<td>' . e($owner['name']) . '</td>'
        . '<td>' . e($owner['race']) . '</td>'
        . '<td>' . $inv . '</td>'
        . '<td>' . $bank . '</td>'
        . '<td><a class="btn btn-sm btn-outline-primary" href="/admin/player-edit.php?id=' . $owner['id'] . '">Fiche</a></td>'
        . '</tr>';
}

$content = '<div class="d-flex justify-content-between align-items-center mb-3">'
    . '<h1 class="mb-0">' . e($title) . '</h1>'
    . '<a class="btn btn-sm btn-outline-secondary" href="/admin/items.php">← Objets</a>'
    . '</div>'
    . ($owners === []
        ? '<p class="text-muted">Aucun joueur ne détient cet objet.</p>'
        : '<p class="text-muted mb-2">' . count($owners) . ' joueur(s)</p>'
            . renderTable(
                ['Matricule', 'Nom', 'Race', 'Inventaire', 'Banque', ''],
                $rows,
                'class="table table-striped table-hover table-sm" data-admin-list data-page-size="30"'
            ));

echo admin_layout($title, renderFlashMessage() . $content);
