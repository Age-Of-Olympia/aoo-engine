<?php
/** Biomes: the types ("forêt") and the zones of each plan ("Forêt du Nord"). */
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Module\Module;
use App\Module\Modules;
use App\Service\CsrfProtectionService;
use App\Service\Map\BiomeAdminService;

$service = new BiomeAdminService();
$token = '<input type="hidden" name="csrf_token" value="' . e((new CsrfProtectionService())->generateToken()) . '">';
$plan = (string) ($_GET['plan'] ?? '');
$types = $service->types();
$typeChoices = array_column($types, 'label', 'id');

/**
 * A POST form to biomes-save.php with its action and hidden fields.
 *
 * @param array<string, int|string> $hidden
 */
function biome_form(string $token, string $action, string $body, array $hidden = [], string $attrs = ''): string
{
    $fields = '';
    foreach ($hidden + ['action' => $action] as $name => $value) {
        $fields .= '<input type="hidden" name="' . e($name) . '" value="' . e((string) $value) . '">';
    }

    return '<form method="post" action="/admin/biomes-save.php"' . ($attrs !== '' ? ' ' . $attrs : '') . '>'
        . $token . $fields . $body . '</form>';
}

$content = '';
if (!Modules::on(Module::Biomes)) {
    $content .= '<div class="alert alert-warning">Le module Biomes est désactivé : les biomes n\'ont aucun effet en jeu.'
        . ' On l\'active dans <a href="/admin/modules.php">Administration › Modules</a>.</div>';
}

// Types
$rows = [];
foreach ($types as $type) {
    $rows[] = '<tr><td><code>' . e($type['name']) . '</code></td><td>'
        . biome_form($token, 'save_type', '<div class="d-flex gap-2">'
            . formInput('label', $type['label'], 'class="form-control form-control-sm" required')
            . formInput('color', $type['color'], 'type="color" class="form-control form-control-sm" style="width:4em"')
            . '<button class="btn btn-sm btn-primary" type="submit">Enregistrer</button></div>',
            ['id' => $type['id'], 'name' => $type['name']])
        . '</td><td>' . $type['biomes'] . '</td><td>'
        . ($type['biomes'] === 0
            ? biome_form($token, 'delete_type', '<button class="btn btn-sm btn-outline-danger" type="submit">Supprimer</button>',
                ['id' => $type['id']], 'data-confirm="' . e('Supprimer le type ' . $type['label'] . ' ?') . '"')
            : '')
        . '</td></tr>';
}
$addType = biome_form($token, 'save_type', '<div class="d-flex flex-wrap gap-2 align-items-end">'
    . formField('Nom technique', formInput('name', '', 'placeholder="foret" required'), 'mb-0')
    . formField('Libellé', formInput('label', '', 'placeholder="Forêt" required'), 'mb-0')
    . formField('Couleur', formInput('color', '#3f6b4f', 'type="color" style="width:4em"'), 'mb-0')
    . '<button class="btn btn-primary" type="submit">Ajouter le type</button></div>', ['id' => 0]);
$content .= formCard('Types de biome', ($types === []
        ? '<p class="text-muted">Aucun type pour l\'instant.</p>'
        : renderTable(['Nom', 'Libellé et couleur', 'Biomes', ''], $rows))
    . '<hr>' . $addType);

// Biomes
$editId = (int) ($_GET['edit'] ?? 0);
$biomes = $service->biomes($plan);
$edited = null;
$rows = [];
foreach ($biomes as $biome) {
    if ($biome['id'] === $editId) {
        $edited = $biome;
    }
    $rows[] = '<tr><td><span class="badge" style="background:' . e($biome['color']) . '">&nbsp;</span> '
        . e($biome['name']) . '</td><td>' . e($biome['type']) . '</td><td>' . e($biome['plan']) . '</td>'
        . '<td>' . $biome['cells'] . '</td><td>' . $biome['stack_order'] . '</td><td class="text-nowrap">'
        . '<a class="btn btn-sm btn-outline-primary" href="?' . e(http_build_query(['plan' => $plan, 'edit' => $biome['id']])) . '#biome-form">Modifier</a> '
        . biome_form($token, 'delete_biome', '<button class="btn btn-sm btn-outline-danger" type="submit">Supprimer</button>',
            ['id' => $biome['id'], 'plan_filter' => $plan],
            'class="d-inline" data-confirm="' . e('Supprimer le biome ' . $biome['name'] . ' ?') . '"')
        . '</td></tr>';
}

$filter = '<form method="get" class="d-flex gap-2 align-items-center mb-2">'
    . formSelect('plan', plan_choices(), $plan !== '' ? $plan : null, 'Plan : tous', 'class="form-control form-control-sm w-auto" data-autosubmit')
    . '<small class="text-muted">' . count($biomes) . ' biome(s)</small></form>';

$form = $types === []
    ? '<p class="text-muted">Créez d\'abord un type de biome.</p>'
    : biome_form($token, 'save_biome',
        '<div class="form-row">'
        . formField('Nom', formInput('name', $edited['name'] ?? '', 'placeholder="Forêt du Nord" required'), 'form-group col-md-4')
        . formField('Type', formSelect('type_id', $typeChoices, isset($edited) ? (string) array_search($edited['type'], $typeChoices, true) : null), 'form-group col-md-3')
        . formField('Plan', formSelect('plan', plan_choices(), $edited['plan'] ?? ($plan !== '' ? $plan : null)), 'form-group col-md-3')
        . formField('Empilement', formInput('stack_order', (string) ($edited['stack_order'] ?? 0), 'type="number"'), 'form-group col-md-2',
            'Le plus grand gagne là où des zones se chevauchent.')
        . '</div>'
        . formField('Coins de la zone', formTextarea('zone', $edited['zone'] ?? '', 2, 'placeholder="0,0  10,0  10,8  0,8" required'), 'form-group',
            'Les coins, dans l\'ordre du contour, au format x,y. La case (x, y) a pour coins x,y et x+1,y+1 :'
            . ' <code>0,0 10,0 10,8 0,8</code> couvre les cases de (0, 0) à (9, 7).')
        . '<button class="btn btn-primary" type="submit">' . ($edited ? 'Enregistrer' : 'Ajouter le biome') . '</button>'
        . ($edited ? ' <a class="btn btn-outline-secondary" href="?' . e(http_build_query(['plan' => $plan])) . '">Annuler</a>' : ''),
        ['id' => $edited['id'] ?? 0, 'plan_filter' => $plan], 'id="biome-form"');

$content .= formCard('Biomes', $filter
    . ($biomes === [] ? '<p class="text-muted">Aucun biome.</p>' : renderTable(['Biome', 'Type', 'Plan', 'Cases', 'Empilement', ''], $rows))
    . '<hr><h5>' . ($edited ? 'Modifier ' . e($edited['name']) : 'Nouveau biome') . '</h5>' . $form);

echo admin_layout('Biomes', $content, [
    'intro' => 'Un biome est une zone d\'un plan avec ses propres règles, valable à tous les niveaux z.'
        . ' Enregistrer un biome recalcule les cases de son plan.',
]);
