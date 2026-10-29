<?php
/** Reputation settings: gains, forum cookies, reward draw, titles. */
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\CsrfProtectionService;
use App\Service\ReputationSettingsService;

$settings = new ReputationSettingsService();
$csrfToken = (new CsrfProtectionService())->generateToken();

/** Opening tag of a form posting $action to the save endpoint. */
$openForm = static function (string $action, string $attrs = '') use ($csrfToken): string {
    return '<form method="post" action="/admin/reputation-save.php"' . ($attrs !== '' ? ' ' . $attrs : '') . '>'
        . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
        . '<input type="hidden" name="action" value="' . e($action) . '">';
};

$prInput = static fn (string $name, float $value): string => formInput(
    $name,
    rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.'),
    'type="number" min="0" step="0.1" required'
);

$gains = $openForm('gains')
    . '<div class="form-row">'
    . formField('PR par post', $prInput('per_post', $settings->perPost()), 'form-group col-md-3', 'Hors HRP et missives.')
    . formField('PR par cookie', $prInput('per_cookie', $settings->perCookie()), 'form-group col-md-3')
    . formField('PR par cookie (même faction)', $prInput('per_cookie_same_faction', $settings->perCookieSameFaction()), 'form-group col-md-3')
    . formField('Récompense tous les', formInput('reward_every', (string) $settings->rewardEvery(), 'type="number" min="1" step="1" required'), 'form-group col-md-3', 'PR : une récompense forum est tirée à chaque multiple atteint.')
    . '</div><button class="btn btn-sm btn-primary" type="submit">Enregistrer</button></form>';

$cookies = $openForm('cookies')
    . '<div class="form-row">'
    . formField('Délai pour donner un cookie', formInput('cookie_max_days', (string) $settings->cookieMaxDays(), 'type="number" min="0" step="1" required'), 'form-group col-md-4', 'Jours après la publication du message.')
    . '</div>'
    . '<div class="form-group">' . formCheckbox('missive_cookies', $settings->missiveCookies(), 'Autoriser les cookies dans les missives <small class="text-muted">(sans gain de PR)</small>') . '</div>'
    . '<button class="btn btn-sm btn-primary" type="submit">Enregistrer</button></form>';

$tiers = $settings->rewardTiers();
$totalWeight = array_sum(array_column($tiers, 'weight'));
$tierRows = [];
foreach ($tiers as $tier) {
    $formId = 'tier-' . $tier['tier'];
    $chance = $totalWeight > 0 ? round($tier['weight'] / $totalWeight * 100, 1) : 0;
    $tierRows[] = '<tr><td>Palier ' . $tier['tier'] . '</td>'
        . '<td>' . formInput('pr', (string) $tier['pr'], 'type="number" min="1" step="1" required form="' . $formId . '" class="form-control form-control-sm" style="width:6rem"') . '</td>'
        . '<td>' . formInput('weight', (string) $tier['weight'], 'type="number" min="0" max="9999" step="0.01" required form="' . $formId . '" class="form-control form-control-sm" style="width:7rem"') . '</td>'
        . '<td>' . $chance . ' %</td>'
        . '<td class="text-right">' . $openForm('tier', 'id="' . $formId . '"')
        . '<input type="hidden" name="tier" value="' . $tier['tier'] . '">'
        . '<button class="btn btn-sm btn-outline-primary" type="submit">Enregistrer</button></form></td></tr>';
}
$drawHelp = '<p class="text-muted">Chaque fois qu’un joueur atteint un multiple de ' . $settings->rewardEvery()
    . ' PR (réglé dans <em>Gains</em>), il reçoit une récompense : une image à collectionner, tirée au hasard'
    . ' parmi 4 paliers. Il peut l’offrir sur un message du forum, et l’auteur du message gagne alors les PR'
    . ' de ce palier. Plus le poids d’un palier est élevé, plus il sort souvent.</p>';
$draw = $drawHelp . ($tierRows !== []
    ? renderTable(['Palier', 'PR versés', 'Poids', 'Chance', ''], $tierRows)
        . '<small class="text-muted">Un poids à 0 retire le palier du tirage.</small>'
    : '<p class="text-muted mb-0">Aucun palier : la migration de la réputation n’a pas tourné.</p>');

$titleRows = [];
foreach ($settings->titleRows() as $row) {
    $formId = 'title-' . $row['id'];
    $isBase = $row['threshold'] === 0;
    $threshold = $isBase
        ? '0 <small class="text-muted">(titre de base)</small><input type="hidden" name="threshold" value="0" form="' . $formId . '">'
        : formInput('threshold', (string) $row['threshold'], 'type="number" min="1" step="1" required form="' . $formId . '" class="form-control form-control-sm" style="width:7rem"');
    $remove = $isBase ? '' : ' ' . $openForm('title-remove', 'class="d-inline" data-confirm="' . e('Retirer le titre « ' . $row['title'] . ' » ?') . '"')
        . '<input type="hidden" name="id" value="' . $row['id'] . '">'
        . '<button class="btn btn-sm btn-outline-danger" type="submit">Retirer</button></form>';
    $titleRows[] = '<tr><td>' . $threshold . '</td>'
        . '<td>' . formInput('title', $row['title'], 'maxlength="64" required form="' . $formId . '" class="form-control form-control-sm"') . '</td>'
        . '<td class="text-right text-nowrap">' . $openForm('title-update', 'id="' . $formId . '" class="d-inline"')
        . '<input type="hidden" name="id" value="' . $row['id'] . '">'
        . '<button class="btn btn-sm btn-outline-primary" type="submit">Enregistrer</button></form>' . $remove . '</td></tr>';
}
$addTitle = $openForm('title-add', 'class="form-inline mt-3"')
    . '<label class="mr-2">À partir de ' . formInput('threshold', '', 'type="number" min="1" step="1" required class="form-control form-control-sm mx-2" style="width:7rem"') . ' PR</label>'
    . formInput('title', '', 'maxlength="64" required placeholder="Titre" class="form-control form-control-sm mr-2"')
    . '<button class="btn btn-sm btn-primary" type="submit">Ajouter le titre</button></form>';
$titles = renderTable(['Seuil (PR)', 'Titre', ''], $titleRows) . $addTitle;

$content = formCard('Gains de réputation', $gains, 'card mb-4')
    . formCard('Cookies du forum', $cookies, 'card mb-4')
    . formCard('Tirage des récompenses forum', $draw, 'card mb-4')
    . formCard('Titres', $titles, 'card mb-4');

echo admin_layout('Réputation', $content, [
    'intro' => 'Ce que rapportent les posts et les cookies du forum, les récompenses tirées aux paliers, et les titres affichés selon les PR.',
]);
