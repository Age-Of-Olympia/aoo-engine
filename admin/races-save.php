<?php
/**
 * Race management — mutations (POST only). Companion to admin/races.php.
 *
 * Routed on ?action: create | update. No delete: players.race references race
 * names; retiring a race = uncheck "jouable" + check "cachée".
 *
 * CSRF-validated; enforces the same access level as the races menu so a
 * direct POST can't bypass it. Redirects back (PRG) with a flash.
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Entity\Race;
use App\Service\ActionService;
use App\Service\AdminMenuAccessService;
use App\Service\CsrfProtectionService;
use App\Service\RaceService;

(new AdminMenuAccessService())->enforce('races.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectTo('/admin/races.php');
}

try {
    (new CsrfProtectionService())->validateTokenOrFail($_POST['csrf_token'] ?? null);
} catch (\Throwable $e) {
    setFlash('warning', 'Jeton de sécurité invalide ou expiré. Rechargez la page et réessayez.');
    redirectTo('/admin/races.php');
}

$service = new RaceService();
$action = $_GET['action'] ?? '';
$name = strtolower(trim((string) ($_POST['name'] ?? '')));

/**
 * Validate the shared form fields; returns an error message or null.
 * bgColor must stay hex: it feeds sscanf("#%02x%02x%02x") in the map layers.
 */
$validate = static function (): ?string {
    if (trim((string) ($_POST['label'] ?? '')) === '') {
        return 'Le nom affiché est requis.';
    }
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($_POST['bgColor'] ?? ''))) {
        return 'Couleur de fond invalide (format attendu : #RRGGBB).';
    }
    foreach (array_keys(CARACS) as $key) {
        if (!isset($_POST['carac'][$key]) || !is_numeric($_POST['carac'][$key])) {
            return "Caractéristique manquante ou invalide : {$key}.";
        }
    }
    return null;
};

/** Apply every form field onto the entity (shared by create and update). */
$applyForm = static function (Race $race): void {
    $race->setLabel(trim((string) $_POST['label']));
    $race->setDescription(trim((string) ($_POST['description'] ?? '')));
    $race->setPlayable(booleanCheckbox('playable'));
    $race->setHidden(booleanCheckbox('hidden'));
    $race->setBgColor((string) $_POST['bgColor']);
    $race->setColor(stringWithDefault('color', 'black'));
    $race->setFaction(trim((string) ($_POST['faction'] ?? '')));
    $race->setPlan(trim((string) ($_POST['plan'] ?? '')));
    $race->setAnimateurId(optionalInt('animateurId'));

    foreach (array_keys(CARACS) as $key) {
        $race->setCarac($key, (int) $_POST['carac'][$key]);
    }
};

/** One name per non-empty line. */
$linesToNames = static fn (string $field): array =>
    preg_split('/\R+/', trim((string) ($_POST[$field] ?? ''))) ?: [];

/**
 * Typo guard: names absent from the known-action catalog are saved anyway
 * (an action can be configured afterwards) but flagged in the flash so a
 * misspelled name doesn't silently fail to grant at character creation.
 */
$unknownNamesNotice = static function (array $names): string {
    $known = (new ActionService())->getKnownActionNames();
    $unknown = array_filter(array_map('trim', $names), static fn (string $n): bool => $n !== '' && !isset($known[$n]));

    return $unknown === []
        ? ''
        : ' ⚠ Noms inconnus du jeu (vérifiez l\'orthographe) : ' . implode(', ', array_unique($unknown)) . '.';
};

if ($error = $validate()) {
    setFlash('warning', $error);
    redirectTo('/admin/races.php');
}

if ($action === 'create') {
    if (!preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
        setFlash('warning', 'Code de race invalide (minuscules, chiffres, _).');
        redirectTo('/admin/races.php?action=new');
    }
    if ($service->getRaceByName($name) !== null) {
        setFlash('warning', "La race « {$name} » existe déjà.");
        redirectTo('/admin/races.php');
    }

    $race = new Race();
    $race->setName($name);
    $race->setCode(strtoupper($name));
    $applyForm($race);
    $service->save($race);
    $starterActions = $linesToNames('starter_actions');
    $spells = $linesToNames('spells');
    // Compute the typo notice BEFORE saving: the catalog includes the race
    // list tables, so a just-saved typo would count as "known".
    $notice = $unknownNamesNotice(array_merge($starterActions, $spells));
    $service->replaceNameLists($race, $starterActions, $spells);

    setFlash('success', "Race « {$name} » créée." . $notice);
    redirectTo('/admin/races.php');
}

if ($action === 'update') {
    $race = $service->getRaceByName($name);
    if ($race === null) {
        setFlash('warning', 'Race introuvable.');
        redirectTo('/admin/races.php');
    }

    $applyForm($race);
    $service->save($race);
    $starterActions = $linesToNames('starter_actions');
    $spells = $linesToNames('spells');
    // Compute the typo notice BEFORE saving: the catalog includes the race
    // list tables, so a just-saved typo would count as "known".
    $notice = $unknownNamesNotice(array_merge($starterActions, $spells));
    $service->replaceNameLists($race, $starterActions, $spells);

    setFlash('success', 'Race « ' . $race->getLabel() . ' » enregistrée.' . $notice);
    redirectTo('/admin/races.php?action=edit&name=' . urlencode($name));
}

setFlash('warning', 'Action inconnue.');
redirectTo('/admin/races.php');
