<?php
/**
 * Détenteurs d'un objet du catalogue (lien depuis la colonne « Joueurs »
 * de admin/items.php) : détail joueur par joueur, réparti entre les
 * quatre emplacements possibles — inventaire (emplacement équipé
 * compris), banque, offres de vente en cours et échanges en cours.
 *
 * Les quatre s'additionnent sans doublon : mettre en vente ou proposer
 * en échange DÉBITE la banque, l'objet n'est donc jamais compté deux
 * fois. C'est aussi pourquoi les deux dernières colonnes sont
 * nécessaires : sans elles, un objet engagé disparaissait de la liste
 * sans que rien ne dise où il était passé.
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
    $cell = static fn(int $n): string => $n > 0 ? (string) $n : '<span class="text-muted">—</span>';

    $rows[] = '<tr>'
        . '<td>' . $owner['id'] . '</td>'
        . '<td>' . e($owner['name']) . '</td>'
        . '<td>' . e($owner['race']) . '</td>'
        . '<td>' . $inv . '</td>'
        . '<td>' . $cell($owner['bank']) . '</td>'
        . '<td>' . $cell($owner['market']) . '</td>'
        . '<td>' . $cell($owner['exchange']) . '</td>'
        . '<td><strong>' . ($owner['inv'] + $owner['bank'] + $owner['market'] + $owner['exchange']) . '</strong></td>'
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
            . '<p class="text-muted small mb-2">Mettre en vente ou proposer en échange débite la banque :'
                . ' les colonnes s\'additionnent sans doublon.</p>'
            . renderTable(
                ['Matricule', 'Nom', 'Race', 'Inventaire', 'Banque', 'En vente', 'En échange', 'Total', ''],
                $rows,
                'class="table table-striped table-hover table-sm" data-admin-list data-page-size="30"'
            ));

echo admin_layout($title, renderFlashMessage() . $content);
