<?php
/**
 * Items — seed des stats depuis les JSON legacy (admin dashboard →
 * Objets → Seed). Pendant de admin/race-seed.php : en prod le
 * déploiement tourne sans datas/ (gitignoré), la migration n'a donc
 * rien pu recopier — cette page rejoue ItemStatsSeeder depuis la
 * racine web, où datas/ existe.
 *
 * Sans risque de re-run : les lignes déjà stats_in_db = 1 (réglages
 * admin) ne sont jamais touchées.
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Factory\EntityManagerFactory;
use App\Service\CsrfProtectionService;
use App\Service\ItemStatsSeeder;

$csrf = new CsrfProtectionService();
$content = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $csrf->validateTokenOrFail($_POST['csrf_token'] ?? null);
        $result = (new ItemStatsSeeder())->seed(
            EntityManagerFactory::getEntityManager()->getConnection(),
            rtrim($_SERVER['DOCUMENT_ROOT'], '/')
        );
        $skippedNote = $result['skipped'] !== []
            ? ' Colonnes absentes de ce schéma, ignorées : <b>' . e(implode(', ', $result['skipped'])) . '</b>.'
            : '';
        setFlash('success', 'Seed terminé : <b>' . $result['seeded'] . '</b> objets recopiés, '
            . $result['missing'] . ' sans fichier JSON, ' . $result['kept'] . ' déjà en base (préservés).'
            . $skippedNote);
    } catch (\Throwable $e) {
        setFlash('warning', 'Échec : ' . e($e->getMessage()));
    }
}

$pending = (new \Classes\Db())->exe('SELECT COUNT(*) AS n FROM items WHERE stats_in_db = 0')->fetch_object()->n;

$content .= '<div class="card"><div class="card-header">Seed des stats d\'objets (JSON → base)</div><div class="card-body">'
    . '<p><b>' . (int) $pending . '</b> objet(s) n\'ont pas encore leurs stats en base.'
    . ' Le seed lit <code>datas/[public|private]/items/{nom}.json</code> et recopie tout, sans perte'
    . ' (clés inconnues dans <code>extra</code>). Les objets déjà migrés ne sont jamais retouchés.</p>'
    . '<form method="post">'
    . '<input type="hidden" name="csrf_token" value="' . e($csrf->generateToken()) . '">'
    . '<button class="btn btn-primary" type="submit">Lancer le seed</button> '
    . '<a class="btn btn-secondary" href="/admin/items.php">Retour aux Objets</a>'
    . '</form></div></div>';

echo admin_layout('Objets — seed JSON', renderFlashMessage() . $content);
