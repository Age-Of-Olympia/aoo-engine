<?php
/**
 * Reprise des déclencheurs de case (admin → Bâtiments → Dialogues de case).
 *
 * Ce qu'une chose a à dire appartient désormais à la CHOSE — son
 * inscription ou sa conversation — et non à la case sous elle. Cette
 * page reprend les déclencheurs `map_dialogs` posés avant ce
 * changement, et les transfère sur ce qui occupe leur case.
 *
 * Elle s'ouvre sur un PLAN qui n'écrit rien : c'est lui qui compte, il
 * montre les cas que le recensement n'avait pas prévus. L'application
 * est un second temps, sur bouton.
 *
 * Pourquoi une page et pas une migration : un déclencheur peut se
 * trouver sur une case nue, auquel cas il faut poser un support pour le
 * porter — modifier la carte n'est pas une opération qu'on lance à
 * l'aveugle depuis un déploiement.
 *
 * Les mutations POSTent vers tile-dialogs-migration-save.php
 * (CSRF, PRG). Accès contrôlé par layout.php.
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\CsrfProtectionService;
use App\Service\TileDialogMigrationService;

/** Une ligne du plan, telle qu'on la donne à lire. */
function tdm_render_row(array $e): string
{
    $badge = match (true) {
        $e['action'] === 'conversation' => '<span class="badge badge-info">conversation</span>',
        $e['action'] === 'inscription' => '<span class="badge badge-success">inscription</span>',
        str_starts_with($e['action'], 'poser') => '<span class="badge badge-warning">' . e($e['action']) . '</span>',
        $e['action'] === 'supprimer' => '<span class="badge badge-secondary">à supprimer</span>',
        $e['action'] === 'conflit' => '<span class="badge badge-danger">à trancher</span>',
        default => '<span class="badge badge-light">' . e($e['action']) . '</span>',
    };

    $content = $e['dialog'] !== ''
        ? '<code>' . e($e['dialog']) . '</code>'
        : '<span class="text-muted">' . e(mb_strimwidth($e['text'], 0, 110, '…')) . '</span>';

    $notes = '';
    foreach ($e['dropped'] as $d) {
        $notes .= '<div><small class="text-danger">écarté #' . (int) $d['id'] . ' — ' . e($d['why'])
            . ($d['params'] !== '' ? ' : <em>' . e(mb_strimwidth($d['params'], 0, 60, '…')) . '</em>' : '')
            . '</small></div>';
    }
    if ($e['warning'] !== '') {
        $notes .= '<div><small class="text-warning">' . e($e['warning']) . '</small></div>';
    }

    return '<tr>'
        . '<td><code>' . e($e['plan']) . '</code> ' . (int) $e['x'] . ',' . (int) $e['y'] . ',' . (int) $e['z'] . '</td>'
        . '<td>' . $badge . '</td>'
        . '<td>' . ($e['target_id'] !== null
            ? e((string) $e['target_race']) . ' <small class="text-muted">#' . (int) $e['target_id'] . '</small>'
            : '<span class="text-muted">case nue</span>') . '</td>'
        . '<td>' . $content . $notes . '</td>'
        . '</tr>';
}

$csrfToken = (new CsrfProtectionService())->generateToken();
$plan = (new TileDialogMigrationService())->plan();

$counts = [];
$dropped = 0;
foreach ($plan as $e) {
    $counts[$e['action']] = ($counts[$e['action']] ?? 0) + 1;
    $dropped += count($e['dropped']);
}

$summary = '';
foreach ($counts as $action => $n) {
    $summary .= '<li><strong>' . $n . '</strong> — ' . e($action) . '</li>';
}

$body = '<p class="text-muted">Un déclencheur de case s\'affiche désormais <strong>même</strong> quand un bâtiment'
    . ' l\'occupe. Transférer un texte sans retirer son déclencheur le ferait donc apparaître deux fois :'
    . ' l\'application supprime le déclencheur repris, dans la même opération.</p>';

if ($plan === []) {
    $body .= '<div class="alert alert-success">Aucun déclencheur de case à reprendre.</div>';
} else {
    $body .= '<div class="card mb-3"><div class="card-body">'
        . '<h5>Ce qui serait fait</h5><ul class="mb-2">' . $summary . '</ul>'
        . ($dropped > 0
            ? '<p class="mb-2"><strong>' . $dropped . '</strong> ligne(s) écartée(s) : lignes vides et versions'
                . ' antérieures sur une même case. La dernière édition l\'emporte — les doublons ne sont jamais'
                . ' fondus ensemble, cela inventerait un texte que personne n\'a écrit.</p>'
            : '')
        . '<form method="post" action="/admin/tile-dialogs-migration-save.php">'
        . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
        . '<button class="btn btn-primary" type="submit" name="apply" value="1"'
        . ' onclick="return confirm(\'Reprendre les ' . count($plan) . ' cases ? Les déclencheurs repris seront supprimés.\');">'
        . 'Appliquer</button>'
        . '</form></div></div>';

    $rows = '';
    foreach ($plan as $e) {
        $rows .= tdm_render_row($e);
    }

    $body .= '<table class="table table-sm"><thead><tr>'
        . '<th>Case</th><th>Action</th><th>Porteur</th><th>Contenu</th>'
        . '</tr></thead><tbody>' . $rows . '</tbody></table>';
}

echo admin_layout('Dialogues de case', renderFlashMessage() . $body);
