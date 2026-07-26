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
        $e['action'] === 'à trancher' => '<span class="badge badge-danger">à trancher</span>',
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

    /* Coordonnées au format de la console : ce qui se colle derrière
     * « tp <nom> ». Aller voir la case est le premier réflexe devant
     * une ligne « à trancher », autant ne pas la recopier à la main. */
    $tp = (int) $e['x'] . ',' . (int) $e['y'] . ',' . (int) $e['z'] . ',' . $e['plan'];

    return '<tr>'
        . '<td><button type="button" class="btn btn-sm btn-outline-secondary tdm-tp"'
        . ' data-tp="' . e($tp) . '" title="Copier pour la console : tp &lt;nom&gt; ' . e($tp) . '">'
        . '<i class="fas fa-copy"></i> <code>' . e($tp) . '</code></button></td>'
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
        . '<th>Case <small class="text-muted">(clic = copie pour <code>tp</code>)</small></th>'
        . '<th>Action</th><th>Porteur</th><th>Contenu</th>'
        . '</tr></thead><tbody>' . $rows . '</tbody></table>';

    /* Copie autonome : la console d'administration ne charge pas jQuery,
     * et navigator.clipboard n'existe pas partout en http — d'où le
     * repli sur une sélection temporaire. */
    $body .= '<script>
    document.addEventListener("click", function (event) {
        var button = event.target.closest(".tdm-tp");
        if (!button) { return; }

        var value = button.getAttribute("data-tp");
        var done = function () {
            var code = button.querySelector("code");
            var before = code.textContent;
            code.textContent = "copié !";
            setTimeout(function () { code.textContent = before; }, 1200);
        };

        var fallback = function () {
            var field = document.createElement("textarea");
            field.value = value;
            field.style.position = "fixed";
            field.style.opacity = "0";
            document.body.appendChild(field);
            field.select();
            try { document.execCommand("copy"); done(); } finally { field.remove(); }
        };

        /* Le presse-papier moderne peut être REFUSÉ (permission, page non
         * sécurisée) : sans ce rattrapage, le clic ne ferait rien du tout
         * et le bouton mentirait par son silence. */
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(value).then(done, fallback);
            return;
        }

        fallback();
    });
    </script>';
}

/* Signalement à part : ce n'est pas un dialogue, mais ça se découvre en
 * lisant les cases — et une incohérence de nature laisse des objets
 * muets sans qu'on sache pourquoi. */
$incoherences = (new TileDialogMigrationService())->typeIncoherences();

if ($incoherences !== []) {
    $lines = '';
    foreach ($incoherences as $i) {
        $lines .= '<tr><td><code>' . e($i['name']) . '</code></td>'
            . '<td>' . (int) $i['pv'] . ' PV — se casse</td>'
            . '<td>' . (int) $i['rows'] . ' ligne(s) marquée(s) récoltable</td></tr>';
    }

    $body .= '<div class="card mt-4"><div class="card-body">'
        . '<h5>Types dont la nature contredit l\'état</h5>'
        . '<p class="text-muted mb-2">Le catalogue les dit <strong>destructibles</strong> (PV positifs), leurs cases'
        . ' se disent <strong>récoltables</strong>. Les cas isolés ont été redressés par migration ;'
        . ' ceux-ci portent trop de lignes pour qu\'un automate tranche — remettre leur état à zéro'
        . ' retirerait la récolte à autant de cases. C\'est la <em>nature</em> qu\'il faut sans doute'
        . ' corriger, dans la console des types de ressources.</p>'
        . '<p class="text-muted mb-2"><small>Certains types sont volontairement mis de côté et n\'apparaissent'
        . ' pas ici — les cocotiers, en attente d\'un arbitrage de jeu.</small></p>'
        . '<table class="table table-sm mb-0"><thead><tr><th>Type</th><th>Ce que dit le catalogue</th>'
        . '<th>Ce que disent les cases</th></tr></thead><tbody>' . $lines . '</tbody></table>'
        . '</div></div>';
}

echo admin_layout('Dialogues de case', renderFlashMessage() . $body);
