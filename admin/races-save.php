<?php
/**
 * Race management — mutations (POST only). Companion to admin/races.php.
 *
 * Routed on ?action: create | update | delete. Delete is guarded: refused as
 * long as any character (player or PNJ) still has players.race = name —
 * retiring a race in use = uncheck "jouable" + check "cachée" instead.
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
use App\Service\FactionService;
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

// La suppression ne porte que le nom : traitée avant la validation des
// champs du formulaire (absents d'un POST de suppression).
if ($action === 'delete') {
    $race = $service->getRaceByName($name);
    if ($race === null) {
        setFlash('warning', 'Race introuvable.');
        redirectTo('/admin/races.php');
    }

    try {
        $service->deleteRace($race);
        setFlash('success', "Race « {$name} » supprimée (listes d'actions et de sorts comprises).");
    } catch (\RuntimeException $e) {
        setFlash('warning', $e->getMessage());
        redirectTo('/admin/races.php?action=edit&name=' . urlencode($name));
    }
    redirectTo('/admin/races.php');
}

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
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($_POST['wound_color'] ?? ''))) {
        return 'Couleur de blessure invalide (format attendu : #RRGGBB).';
    }
    foreach (array_keys(CARACS) as $key) {
        if (!isset($_POST['carac'][$key]) || !is_numeric($_POST['carac'][$key])) {
            return "Caractéristique manquante ou invalide : {$key}.";
        }
    }
    return null;
};

/**
 * Apply every form field onto the entity (shared by create and update).
 * Returns a notice appended to the success flash ('' when all clean).
 */
$applyForm = static function (Race $race): string {
    $notice = '';

    $race->setLabel(trim((string) $_POST['label']));
    $race->setDescription(trim((string) ($_POST['description'] ?? '')));
    // Sorte : personnage (défaut) ou structure. Une structure n'est jamais
    // proposée à l'inscription, quel que soit l'état de la case Jouable.
    $kind = ($_POST['kind'] ?? 'character') === 'structure' ? 'structure' : 'character';
    $race->setKind($kind);
    // Nature (structures seulement) : édifice (porte) ou obstacle (mur).
    $race->setStructureNature(($_POST['structure_nature'] ?? 'edifice') === 'obstacle' ? 'obstacle' : 'edifice');
    // Saignement : un élément de carte connu, ou rien.
    $bleeds = trim((string) ($_POST['bleeds'] ?? ''));
    $race->setBleeds($bleeds !== '' && (new \App\Service\EffectService())->exists($bleeds) ? $bleeds : '');
    $race->setPlayable($kind === 'character' && booleanCheckbox('playable'));
    $race->setHidden(booleanCheckbox('hidden'));
    $race->setBgColor((string) $_POST['bgColor']);
    $race->setColor(stringWithDefault('color', 'black'));
    $race->setWoundColor((string) $_POST['wound_color']);

    // Faction de départ : validée contre le catalogue (admin/factions.php).
    // Une valeur orpheline est conservée seulement si elle ne change pas
    // (option ⚠ du select) — jamais introduite.
    $faction = strtolower(trim((string) ($_POST['faction'] ?? '')));
    if ($faction === '' || $faction === $race->getFaction()
        || (new FactionService())->getFactionByCode($faction) !== null) {
        $race->setFaction($faction);
    } else {
        $notice = " ⚠ Faction « {$faction} » inconnue du catalogue — champ inchangé.";
    }
    $race->setPlan(trim((string) ($_POST['plan'] ?? '')));
    $race->setAnimateurId(optionalInt('animateurId'));

    foreach (array_keys(CARACS) as $key) {
        $race->setCarac($key, (int) $_POST['carac'][$key]);
    }

    return $notice;
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
    $factionNotice = $applyForm($race);
    $service->save($race);
    $starterActions = $linesToNames('starter_actions');
    $spells = $linesToNames('spells');
    // Compute the typo notice BEFORE saving: the catalog includes the race
    // list tables, so a just-saved typo would count as "known".
    $notice = $unknownNamesNotice(array_merge($starterActions, $spells));
    $service->replaceNameLists($race, $starterActions, $spells);

    setFlash('success', "Race « {$name} » créée." . $factionNotice . $notice);
    redirectTo('/admin/races.php');
}

if ($action === 'update') {
    $race = $service->getRaceByName($name);
    if ($race === null) {
        setFlash('warning', 'Race introuvable.');
        redirectTo('/admin/races.php');
    }

    $factionNotice = $applyForm($race);
    $service->save($race);
    $starterActions = $linesToNames('starter_actions');
    $spells = $linesToNames('spells');
    // Compute the typo notice BEFORE saving: the catalog includes the race
    // list tables, so a just-saved typo would count as "known".
    $notice = $unknownNamesNotice(array_merge($starterActions, $spells));
    $service->replaceNameLists($race, $starterActions, $spells);

    setFlash('success', 'Race « ' . $race->getLabel() . ' » enregistrée.' . $factionNotice . $notice);
    redirectTo('/admin/races.php?action=edit&name=' . urlencode($name));
}

setFlash('warning', 'Action inconnue.');
redirectTo('/admin/races.php');
