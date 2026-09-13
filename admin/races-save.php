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
use App\View\Admin\TypeEditorFace;

(new AdminMenuAccessService())->enforce('races.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectTo('/admin/races.php');
}

/* Deux sections, une table : les mutations d'une sorte « structure »
 * renvoient vers Types de bâtiments, pas vers Races — messages compris
 * (chaque formulaire, suppression incluse, poste son kind). */
$face = TypeEditorFace::fromRequest($_POST);
$structureMode = $face->isStructure();
$backPage = $face->page;

try {
    (new CsrfProtectionService())->validateTokenOrFail($_POST['csrf_token'] ?? null);
} catch (\Throwable $e) {
    setFlash('warning', 'Jeton de sécurité invalide ou expiré. Rechargez la page et réessayez.');
    redirectTo($backPage);
}

$service = new RaceService();
$action = $_GET['action'] ?? '';
$name = strtolower(trim((string) ($_POST['name'] ?? '')));

// La suppression ne porte que le nom (et le kind) : traitée avant la
// validation des champs du formulaire (absents d'un POST de suppression).
if ($action === 'delete') {
    $race = $service->getRaceByName($name);
    if ($race === null) {
        setFlash('warning', $structureMode ? 'Type introuvable.' : 'Race introuvable.');
        redirectTo($backPage);
    }

    try {
        $service->deleteRace($race);
        setFlash('success', $structureMode
            ? "Type de bâtiment « {$name} » supprimé."
            : "Race « {$name} » supprimée (listes d'actions et de sorts comprises).");
    } catch (\RuntimeException $e) {
        setFlash('warning', $e->getMessage());
        redirectTo($backPage . '?action=edit&name=' . urlencode($name));
    }
    redirectTo($backPage);
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
 *
 * @return array{notice: string, newFamily: string, rawHarvestFix: array<string, int|string|null>}
 *         notice: appended to the success flash ('' when all clean).
 *         newFamily: Race::FAMILY_* the posted nature resolves to.
 *         rawHarvestFix: harvest_* columns the entity's current PHP class
 *         has no setter for (a BuildingType moving to ressource); the
 *         update branch writes them with raw SQL after the flush.
 */
$applyForm = static function (Race $race) use ($face, $action): array {
    $notice = '';

    $race->setLabel(trim((string) $_POST['label']));
    $race->setDescription(trim((string) ($_POST['description'] ?? '')));
    // Sorte : personnage (défaut) ou structure. Une structure n'est jamais
    // proposée à l'inscription, quel que soit l'état de la case Jouable.
    $kind = ($_POST['kind'] ?? 'character') === 'structure' ? 'structure' : 'character';
    $race->setKind($kind);
    /* On create the face pins the nature; on update any known nature is
     * accepted, which is how a type moves to another family. */
    $postedNature = (string) ($_POST['structure_nature'] ?? '');
    $newNature = ($action === 'update' && array_key_exists($postedNature, TypeEditorFace::natureChoices()))
        ? $postedNature
        : $face->resolveNature($postedNature !== '' ? $postedNature : null);
    $targetFace = TypeEditorFace::fromRequest(['kind' => $kind, 'nature' => $newNature]);
    $newFamily = Race::ofFamily($kind, $newNature)->familyKey();

    $race->setStructureNature($newNature);
    // Saignement : un élément de carte connu, ou rien.
    $bleeds = trim((string) ($_POST['bleeds'] ?? ''));
    $race->setBleeds($bleeds !== '' && (new \App\Service\EffectService())->exists($bleeds) ? $bleeds : '');
    /* Inscription : ce qu'un exemplaire neuf porte déjà, et jusqu'où on
     * peut le lire. Réservé aux types de DÉCOR — un personnage écrit
     * son message du jour lui-même. */
    if ($race instanceof \App\Entity\StructureType) {
        $race->setReadableFromAfar(booleanCheckbox('readable_from_afar'));
        $race->setDefaultText(trim((string) ($_POST['default_text'] ?? '')));

        /* Three states: empty means "follow my family" and must stay null.
         * Reading it as "no" would cut the type off its family on the first
         * save, silently. */
        $repairable = (string) ($_POST['repairable'] ?? '');
        $race->setRepairable($repairable === '' ? null : $repairable === '1');
    }
    /* Yield. $race keeps the PHP class Doctrine loaded until the next
     * request, so the target family decides what to write, and the current
     * class decides whether it goes through a setter or $rawHarvestFix.
     * Leaving ressource/plante clears the yield rather than leaving it
     * invisible in the row. */
    $rawHarvestFix = [];

    if ($targetFace->harvests()) {
        // Validated against the catalogue: a crafted POST must not install
        // an unknown item. Empty stays empty ("yields nothing").
        $harvestItem = trim((string) ($_POST['harvest_item'] ?? ''));

        if ($harvestItem !== '' && \Classes\Item::get_item_by_name($harvestItem) === false) {
            $notice .= " ⚠ Objet « {$harvestItem} » inconnu du catalogue — rendement inchangé.";
            $harvestItem = $race instanceof \App\Interface\HarvestableInterface ? $race->getHarvestItem() : '';
        }

        $harvestExhaust = trim((string) ($_POST['harvest_exhaust'] ?? '')) !== ''
            ? max(1, min(100, (int) $_POST['harvest_exhaust']))
            : null;
        $harvestRegrow = trim((string) ($_POST['harvest_regrow'] ?? '')) !== ''
            ? max(1, min(1000, (int) $_POST['harvest_regrow']))
            : null;

        if ($race instanceof \App\Interface\HarvestableInterface) {
            $race->setHarvestItem($harvestItem);
            $race->setHarvestExhaust($harvestExhaust);
            $race->setHarvestRegrow($harvestRegrow);
        } else {
            $rawHarvestFix['harvest_item'] = $harvestItem;
            $rawHarvestFix['harvest_exhaust'] = $harvestExhaust;
            $rawHarvestFix['harvest_regrow'] = $harvestRegrow;
        }
    } elseif ($race instanceof \App\Interface\HarvestableInterface
        && ($race->getHarvestItem() !== '' || $race->getHarvestExhaust() !== null || $race->getHarvestRegrow() !== null)
    ) {
        $race->setHarvestItem('');
        $race->setHarvestExhaust(null);
        $race->setHarvestRegrow(null);
        $notice .= ' ⚠ Catégorie changée : le rendement (récolte) de ce type a été effacé.';
    }

    /* Plant quantity: two bounds, max never below min. harvest_min/max are
     * PlantType columns only, hence the same setter/raw split. */
    if ($targetFace->key === TypeEditorFace::PLANT) {
        $min = max(1, min(99, (int) ($_POST['harvest_min'] ?? \App\Entity\PlantType::DEFAULT_MIN)));
        $max = max($min, min(99, (int) ($_POST['harvest_max'] ?? \App\Entity\PlantType::DEFAULT_MAX)));

        if ($race instanceof \App\Entity\PlantType) {
            $race->setHarvestMin($min);
            $race->setHarvestMax($max);
        } else {
            $rawHarvestFix['harvest_min'] = $min;
            $rawHarvestFix['harvest_max'] = $max;
        }
    } elseif ($race instanceof \App\Entity\PlantType) {
        $race->setHarvestMin(\App\Entity\PlantType::DEFAULT_MIN);
        $race->setHarvestMax(\App\Entity\PlantType::DEFAULT_MAX);
    }
    $race->setBlocksPassage(booleanCheckbox('blocks_passage'));
    $race->setBlocksProjectiles(booleanCheckbox('blocks_projectiles'));
    // Only the building form shows the field; an absent input must not
    // zero what another face never offered to edit.
    if (isset($_POST['build_work'])) {
        $race->setBuildWork(max(0, min(999, (int) $_POST['build_work'])));
    }
    /* The footprint: a full w×h box, only when the dimensions CHANGED —
     * a hand-tuned figure (holes, per-cell roles from Cartes → Emprises)
     * keeps its shape as long as the box stays the same. */
    if (isset($_POST['fp_w'], $_POST['fp_h'])) {
        $fpW = max(1, min(8, (int) $_POST['fp_w']));
        $fpH = max(1, min(8, (int) $_POST['fp_h']));
        /* prev 0×0 = nothing declared yet: a single cell then needs no row. */
        $undeclared = (int) ($_POST['fp_prev_w'] ?? 0) === 0;
        $changed = $fpW !== (int) ($_POST['fp_prev_w'] ?? 0) || $fpH !== (int) ($_POST['fp_prev_h'] ?? 0);
        if ($changed && !($undeclared && $fpW * $fpH === 1)) {
            $offsets = [];
            for ($dy = 0; $dy > -$fpH; $dy--) {
                for ($dx = 0; $dx < $fpW; $dx++) {
                    $offsets[] = [$dx, $dy];
                }
            }
            (new \App\Service\Map\EntityTypeFootprintService())->declare($race->getName(), $fpW, $fpH, $offsets);
            /* Placed exemplars take the new box at once, as Formes does. */
            (new \App\Service\Map\EntityCellService())->reapplyForType($race->getName());
        }
    }
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
    $race->setCapacity((int) ($_POST['capacity'] ?? 0));

    return ['notice' => $notice, 'newFamily' => $newFamily, 'rawHarvestFix' => $rawHarvestFix];
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
    redirectTo($backPage);
}

if ($action === 'create') {
    if (!preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
        setFlash('warning', ($structureMode ? 'Code de type' : 'Code de race') . ' invalide (minuscules, chiffres, _).');
        redirectTo($backPage . '?action=new');
    }
    if ($service->getRaceByName($name) !== null) {
        setFlash('warning', $structureMode
            ? "Le type « {$name} » existe déjà (ou une race porte déjà ce code)."
            : "La race « {$name} » existe déjà.");
        redirectTo($backPage);
    }

    /* The family comes from the face at creation; edit can move a type
       between structure families, never to or from character. */
    $race = Race::ofFamily(
        $face->isStructure() ? 'structure' : 'character',
        $face->nature()
    );
    $race->setName($name);
    $race->setCode(strtoupper($name));
    $factionNotice = $applyForm($race)['notice'];
    $service->save($race);
    $starterActions = $linesToNames('starter_actions');
    $spells = $linesToNames('spells');
    // Compute the typo notice BEFORE saving: the catalog includes the race
    // list tables, so a just-saved typo would count as "known".
    $notice = $unknownNamesNotice(array_merge($starterActions, $spells));
    $service->replaceNameLists($race, $starterActions, $spells);

    setFlash('success', ($structureMode ? "Type de bâtiment « {$name} » créé." : "Race « {$name} » créée.")
        . $factionNotice . $notice);
    redirectTo($backPage);
}

if ($action === 'update') {
    $race = $service->getRaceByName($name);
    if ($race === null) {
        setFlash('warning', $structureMode ? 'Type introuvable.' : 'Race introuvable.');
        redirectTo($backPage);
    }

    $formResult = $applyForm($race);
    $factionNotice = $formResult['notice'];
    $service->save($race);

    /* Doctrine writes type_kind from the loaded PHP class, i.e. the family
     * before this save (the races_type_kind_bu trigger only fills an empty
     * value). A family change, and the harvest columns that class has no
     * setter for, are written with one raw statement after the flush. */
    $rawFix = $formResult['rawHarvestFix'];
    if ($formResult['newFamily'] !== $race->familyKey()) {
        $rawFix['type_kind'] = $formResult['newFamily'];
    }
    if ($rawFix !== []) {
        $setClauses = array_map(static fn (string $column): string => "{$column} = ?", array_keys($rawFix));
        \App\Factory\EntityManagerFactory::getEntityManager()->getConnection()->executeStatement(
            'UPDATE races SET ' . implode(', ', $setClauses) . ' WHERE name = ?',
            [...array_values($rawFix), $name]
        );
        RaceService::clearCache();
    }

    $starterActions = $linesToNames('starter_actions');
    $spells = $linesToNames('spells');
    // Compute the typo notice BEFORE saving: the catalog includes the race
    // list tables, so a just-saved typo would count as "known".
    $notice = $unknownNamesNotice(array_merge($starterActions, $spells));
    $service->replaceNameLists($race, $starterActions, $spells);

    setFlash('success', ($structureMode ? 'Type « ' : 'Race « ') . $race->getLabel() . ' » enregistré'
        . ($structureMode ? '' : 'e') . '.' . $factionNotice . $notice);
    redirectTo($backPage . '?action=edit&name=' . urlencode($name));
}

setFlash('warning', 'Action inconnue.');
redirectTo($backPage);
