<?php
/**
 * Special events (admin dashboard → Outils → Évènements spéciaux).
 *
 * Writes one event line to every character concerned — the whole game, one
 * plan or an area of it, active players by default, optionally only some
 * matricules — with optional PV damage and an optional status, through
 * SpecialEventService. Two steps: the form shows who would
 * receive it, then « Publier » applies it.
 */
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\AuditService;
use App\Service\CsrfProtectionService;
use App\Service\SpecialEventService;

$csrf = new CsrfProtectionService();
$service = new SpecialEventService();
$conn = \App\Factory\EntityManagerFactory::getEntityManager()->getConnection();

$form = [
    'text' => trim((string) ($_POST['text'] ?? '')),
    'plan' => (string) ($_POST['plan'] ?? ''),
    'who' => (string) ($_POST['who'] ?? SpecialEventService::RECIPIENTS_ACTIVE),
    'damage' => max(0, (int) ($_POST['damage'] ?? 0)),
    'percent' => !empty($_POST['percent']),
    'may_kill' => !empty($_POST['may_kill']),
    'effect' => (string) ($_POST['effect'] ?? ''),
    'effect_turns' => max(1, (int) ($_POST['effect_turns'] ?? 1)),
    'area_x' => trim((string) ($_POST['area_x'] ?? '')),
    'area_y' => trim((string) ($_POST['area_y'] ?? '')),
    'area_z' => trim((string) ($_POST['area_z'] ?? '0')),
    'radius' => trim((string) ($_POST['radius'] ?? '')),
    'matricules' => trim((string) ($_POST['matricules'] ?? '')),
];

/* An area takes its centre and radius together, on a chosen plan */
$areaGiven = $form['area_x'] !== '' || $form['area_y'] !== '' || $form['radius'] !== '';
$area = $areaGiven && is_numeric($form['area_x']) && is_numeric($form['area_y']) && is_numeric($form['area_z'])
    && ctype_digit($form['radius']) && $form['plan'] !== ''
    ? ['x' => (int) $form['area_x'], 'y' => (int) $form['area_y'], 'z' => (int) $form['area_z'], 'radius' => (int) $form['radius']]
    : null;
$matricules = $form['matricules'] === '' ? [] : SpecialEventService::parseMatricules($form['matricules']);

$plans = array_keys(plans()->all());
$effects = $conn->fetchAllKeyValue('SELECT name, label FROM effects ORDER BY label');
$whoChoices = [
    SpecialEventService::RECIPIENTS_ACTIVE => 'Joueurs actifs',
    SpecialEventService::RECIPIENTS_PLAYERS => 'Tous les joueurs',
    SpecialEventService::RECIPIENTS_ALL => 'Joueurs et PNJ',
];

$valid = $form['text'] !== ''
    && ($form['plan'] === '' || in_array($form['plan'], $plans, true))
    && isset($whoChoices[$form['who']])
    && ($form['effect'] === '' || isset($effects[$form['effect']]))
    && (!$areaGiven || $area !== null)
    && $matricules !== null;

$recipients = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdminPost('/admin/special-events.php');

    if (!$valid) {
        setFlash('danger', 'Texte manquant, choix inconnu, zone incomplète (plan, x, y et rayon) ou liste de matricules illisible.');
        redirectTo('/admin/special-events.php');
    }

    $recipients = $service->recipients($form['plan'], $form['who'], $area, $matricules);

    if (isset($_POST['publish'])) {
        $result = $service->publish(
            $recipients, $form['text'], $form['damage'], $form['percent'], $form['may_kill'],
            $form['effect'], $form['effect_turns']
        );
        (new AuditService())->addAuditLog(sprintf(
            'évènement spécial publié à %d personnage(s) (%s, %s) : « %s »%s%s, %d mort(s)',
            $result['published'],
            ($form['plan'] === '' ? 'tout le jeu' : 'plan ' . $form['plan'])
                . ($area === null ? '' : sprintf(' autour de %d,%d,%d rayon %d', $area['x'], $area['y'], $area['z'], $area['radius']))
                . ($matricules === [] ? '' : ', matricules ' . $form['matricules']),
            $whoChoices[$form['who']],
            mb_strimwidth($form['text'], 0, 120, '…'),
            $form['damage'] > 0 ? ', -' . $form['damage'] . ($form['percent'] ? ' % PV' : ' PV') . ($form['may_kill'] ? ' (peut tuer)' : '') : '',
            $form['effect'] !== '' ? ', effet ' . $form['effect'] . ' ' . $form['effect_turns'] . ' tour(s)' : '',
            $result['deaths']
        ));
        setFlash('success', "Évènement publié à {$result['published']} personnage(s)"
            . ($result['deaths'] > 0 ? ", {$result['deaths']} mort(s)." : '.'));
        redirectTo('/admin/special-events.php'); // PRG
    }
}

$fields = $csrf->renderTokenField()
    . '<div class="form-group"><label>Texte affiché dans les évènements</label>'
    . formTextarea('text', $form['text'], 3, 'required placeholder="La Terre tremble. Vous êtes projeté sur le sol et vous vous blessez."') . '</div>'
    . '<div class="form-row">'
    . '<div class="form-group col-md-4"><label>Où</label>'
    . formSelect('plan', ['' => 'Tout le jeu'] + array_combine($plans, $plans), $form['plan']) . '</div>'
    . '<div class="form-group col-md-4"><label>Qui</label>'
    . formSelect('who', $whoChoices, $form['who']) . '</div>'
    . '</div>'
    . '<div class="form-row">'
    . '<div class="form-group col-md-2"><label>Zone : x</label>' . formInput('area_x', $form['area_x'], 'type="number"') . '</div>'
    . '<div class="form-group col-md-2"><label>y</label>' . formInput('area_y', $form['area_y'], 'type="number"') . '</div>'
    . '<div class="form-group col-md-2"><label>z</label>' . formInput('area_z', $form['area_z'], 'type="number"') . '</div>'
    . '<div class="form-group col-md-2"><label>Rayon (cases)</label>' . formInput('radius', $form['radius'], 'type="number" min="0"') . '</div>'
    . '<div class="col-md-4 d-flex align-items-end"><small class="form-text text-muted mb-3">Facultatif, sur le plan choisi : carré centré sur x, y.</small></div>'
    . '</div>'
    . '<div class="form-group"><label>Matricules (facultatif)</label>'
    . formInput('matricules', $form['matricules'], 'placeholder="12, 15, 20-30"') . '</div>'
    . '<div class="form-row">'
    . '<div class="form-group col-md-2"><label>Dégâts</label>'
    . formInput('damage', (string) $form['damage'], 'type="number" min="0"') . '</div>'
    . '<div class="form-group col-md-4 d-flex align-items-end">'
    . formCheckbox('percent', $form['percent'], 'en % des PV max', 'class="mr-3"')
    . formCheckbox('may_kill', $form['may_kill'], 'peut tuer (sinon 1 PV minimum)') . '</div>'
    . '</div>'
    . '<div class="form-row">'
    . '<div class="form-group col-md-4"><label>Effet</label>'
    . formSelect('effect', ['' => 'Aucun'] + $effects, $form['effect']) . '</div>'
    . '<div class="form-group col-md-2"><label>Durée (tours)</label>'
    . formInput('effect_turns', (string) $form['effect_turns'], 'type="number" min="1"') . '</div>'
    . '</div>';

$preview = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $names = $recipients === [] ? [] : $conn->fetchFirstColumn(
        'SELECT name FROM players WHERE id IN (?) ORDER BY name LIMIT 20',
        [$recipients],
        [\Doctrine\DBAL\ArrayParameterType::INTEGER]
    );
    $preview = '<div class="alert alert-info"><b>' . count($recipients) . ' personnage(s)</b> recevront cet évènement'
        . ($names === [] ? '.' : ' : ' . e(implode(', ', $names)) . (count($recipients) > 20 ? '…' : ''))
        . '</div>'
        . ($recipients === [] ? '' : '<button type="submit" name="publish" value="1" class="btn btn-danger"'
            . ' data-confirm="Publier à ' . count($recipients) . ' personnage(s) ?">Publier</button> ');
}

$content = '<form method="post" action="/admin/special-events.php">'
    . formCard('Nouvel évènement', $fields
        . $preview
        . '<button type="submit" name="preview" value="1" class="btn btn-secondary">Voir les destinataires</button>')
    . '</form>';

echo admin_layout('Évènements spéciaux', $content);
